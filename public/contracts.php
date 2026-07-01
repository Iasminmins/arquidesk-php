<?php

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/contracts.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true)) {
    redirect('/');
}

$companyId = (int) $user['company_id'];
$isDesigner = $user['role'] === 'PROJETISTA';
contracts_bootstrap($companyId);

$companyStmt = db()->prepare('select * from companies where id = ? limit 1');
$companyStmt->execute([$companyId]);
$company = $companyStmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $body = "Contrato anexado pela empresa.\n\nUse o arquivo original anexado para assinatura via gov.br ou substitua este texto pelo contrato editavel.";
        }
        $uploadedTemplateFile = null;
        if (!empty($_FILES['source_file']) && $_FILES['source_file']['error'] === UPLOAD_ERR_OK) {
            $maxBytes = 15 * 1024 * 1024;
            $originalName = basename($_FILES['source_file']['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'doc', 'docx'];
            $allowedMime = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['source_file']['tmp_name']);
            if (
                $_FILES['source_file']['size'] <= $maxBytes
                && in_array($ext, $allowedExt, true)
                && in_array($mimeType, $allowedMime, true)
            ) {
                $storedName = sprintf('modelo_%d_%s.%s', $companyId, bin2hex(random_bytes(8)), $ext);
                $uploadDir = __DIR__ . '/../uploads/contracts/templates/' . $companyId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['source_file']['tmp_name'], $uploadDir . $storedName)) {
                    $uploadedTemplateFile = [
                        'original' => $originalName,
                        'stored' => $storedName,
                        'size' => (int) $_FILES['source_file']['size'],
                        'mime' => $mimeType ?: '',
                    ];
                }
            }
        }

        if ($title !== '') {
            if ($templateId > 0) {
                db()->prepare('update contract_templates set title = ?, body = ?, active = ? where id = ? and company_id = ?')
                    ->execute([$title, $body, !empty($_POST['active']) ? 1 : 0, $templateId, $companyId]);
                if ($uploadedTemplateFile) {
                    db()->prepare('update contract_templates set source_file_original = ?, source_file_stored = ?, source_file_size = ?, source_file_mime = ? where id = ? and company_id = ?')
                        ->execute([$uploadedTemplateFile['original'], $uploadedTemplateFile['stored'], $uploadedTemplateFile['size'], $uploadedTemplateFile['mime'], $templateId, $companyId]);
                }
            } else {
                db()->prepare('insert into contract_templates (company_id, title, body, source_file_original, source_file_stored, source_file_size, source_file_mime, active) values (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $companyId,
                        $title,
                        $body,
                        $uploadedTemplateFile['original'] ?? null,
                        $uploadedTemplateFile['stored'] ?? null,
                        $uploadedTemplateFile['size'] ?? null,
                        $uploadedTemplateFile['mime'] ?? null,
                        1,
                    ]);
            }
        }
        redirect('/contracts.php?view=models&ok=1');
    }

    if ($action === 'delete_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $stmt = db()->prepare('select * from contract_templates where id = ? and company_id = ? limit 1');
        $stmt->execute([$templateId, $companyId]);
        $templateToDelete = $stmt->fetch();

        if ($templateToDelete) {
            $storedFile = $templateToDelete['source_file_stored'] ?? '';
            db()->prepare('delete from contract_templates where id = ? and company_id = ?')->execute([$templateId, $companyId]);

            if ($storedFile !== '') {
                $stillUsedStmt = db()->prepare(
                    'select
                        (select count(*) from contract_templates where company_id = ? and source_file_stored = ?) +
                        (select count(*) from project_contracts where company_id = ? and source_file_stored = ?)
                     as total'
                );
                $stillUsedStmt->execute([$companyId, $storedFile, $companyId, $storedFile]);
                if ((int) $stillUsedStmt->fetchColumn() === 0) {
                    $filePath = __DIR__ . '/../uploads/contracts/templates/' . $companyId . '/' . $storedFile;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }

        redirect('/contracts.php?view=models&ok=1');
    }

    if ($action === 'create_contract') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $title = trim($_POST['contract_title'] ?? '');

        $projectSql = 'select p.*, u.name as designer_name from client_projects p left join users u on u.id = p.designer_id where p.id = ? and p.company_id = ?';
        $projectParams = [$projectId, $companyId];
        if ($isDesigner) {
            $projectSql .= ' and p.designer_id = ?';
            $projectParams[] = (int) $user['id'];
        }
        $projectSql .= ' limit 1';
        $projectStmt = db()->prepare($projectSql);
        $projectStmt->execute($projectParams);
        $project = $projectStmt->fetch();

        $templateStmt = db()->prepare('select * from contract_templates where id = ? and company_id = ? and active = 1 limit 1');
        $templateStmt->execute([$templateId, $companyId]);
        $template = $templateStmt->fetch();

        if ($project && $template) {
            $body = render_contract_body($template['body'], $project, $company);
            $publicToken = bin2hex(random_bytes(32));
            $contractTitle = $title !== '' ? $title : 'Contrato - ' . $project['client_name'] . ' - ' . $project['project_name'];
            db()->prepare(
                'insert into project_contracts (company_id, client_project_id, template_id, title, body, source_file_original, source_file_stored, source_file_size, source_file_mime, status, signature_method, public_token, created_by_user_id)
                 values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $companyId,
                $projectId,
                $templateId,
                $contractTitle,
                $body,
                $template['source_file_original'] ?? null,
                $template['source_file_stored'] ?? null,
                $template['source_file_size'] ?? null,
                $template['source_file_mime'] ?? null,
                'SENT',
                'PENDING',
                $publicToken,
                (int) $user['id'],
            ]);
        }
        redirect('/contracts.php?ok=1');
    }

    if ($action === 'cancel_contract') {
        $contractId = (int) ($_POST['contract_id'] ?? 0);
        $sql = 'update project_contracts pc
                join client_projects p on p.id = pc.client_project_id and p.company_id = pc.company_id
                set pc.status = ?
                where pc.id = ? and pc.company_id = ?';
        $params = ['CANCELED', $contractId, $companyId];
        if ($isDesigner) {
            $sql .= ' and p.designer_id = ?';
            $params[] = (int) $user['id'];
        }
        db()->prepare($sql)->execute($params);
        redirect('/contracts.php?ok=1');
    }

    if ($action === 'delete_contract') {
        $contractId = (int) ($_POST['contract_id'] ?? 0);
        $sql = 'select pc.*
                from project_contracts pc
                join client_projects p on p.id = pc.client_project_id and p.company_id = pc.company_id
                where pc.id = ? and pc.company_id = ?';
        $params = [$contractId, $companyId];
        if ($isDesigner) {
            $sql .= ' and p.designer_id = ?';
            $params[] = (int) $user['id'];
        }
        $sql .= ' limit 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $contractToDelete = $stmt->fetch();

        if ($contractToDelete) {
            if (!empty($contractToDelete['signed_file_stored'])) {
                $signedPath = __DIR__ . '/../uploads/contracts/' . $companyId . '/' . (int) $contractToDelete['client_project_id'] . '/' . (int) $contractToDelete['id'] . '/' . $contractToDelete['signed_file_stored'];
                if (is_file($signedPath)) {
                    @unlink($signedPath);
                }
            }
            db()->prepare('delete from project_contracts where id = ? and company_id = ?')->execute([$contractId, $companyId]);
        }

        redirect('/contracts.php?ok=1');
    }
}

$view = $_GET['view'] ?? 'contracts';
$search = trim($_GET['q'] ?? '');

$projectSql = "select p.id, p.client_name, p.project_name, p.current_stage, u.name as designer_name
               from client_projects p
               left join users u on u.id = p.designer_id
               where p.company_id = ?";
$projectParams = [$companyId];
if ($isDesigner) {
    $projectSql .= ' and p.designer_id = ?';
    $projectParams[] = (int) $user['id'];
}
$projectSql .= ' order by p.updated_at desc, p.created_at desc';
$projectsStmt = db()->prepare($projectSql);
$projectsStmt->execute($projectParams);
$projects = $projectsStmt->fetchAll();

$templatesStmt = db()->prepare('select * from contract_templates where company_id = ? order by active desc, updated_at desc, created_at desc');
$templatesStmt->execute([$companyId]);
$templates = $templatesStmt->fetchAll();

$contractsSql = "select pc.*, p.client_name, p.project_name, p.current_stage, u.name as designer_name
                 from project_contracts pc
                 join client_projects p on p.id = pc.client_project_id and p.company_id = pc.company_id
                 left join users u on u.id = p.designer_id
                 where pc.company_id = ?";
$contractsParams = [$companyId];
if ($isDesigner) {
    $contractsSql .= ' and p.designer_id = ?';
    $contractsParams[] = (int) $user['id'];
}
if ($search !== '') {
    $contractsSql .= ' and (pc.title like ? or p.client_name like ? or p.project_name like ?)';
    $term = "%{$search}%";
    $contractsParams[] = $term;
    $contractsParams[] = $term;
    $contractsParams[] = $term;
}
$contractsSql .= ' order by pc.created_at desc';
$contractsStmt = db()->prepare($contractsSql);
$contractsStmt->execute($contractsParams);
$contracts = $contractsStmt->fetchAll();

$editTemplate = null;
if (isset($_GET['template'])) {
    $templateId = (int) $_GET['template'];
    if ($templateId > 0) {
        $editStmt = db()->prepare('select * from contract_templates where id = ? and company_id = ? limit 1');
        $editStmt->execute([$templateId, $companyId]);
        $editTemplate = $editStmt->fetch();
    }
}

$pageTitle = 'Contratos';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">Operacao concluida.</div>
    <?php endif; ?>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-xl font-bold">Contratos</h2>
            <p class="text-sm text-slate-500">Gere contratos por projeto, envie para aceite eletronico ou assinatura via gov.br.</p>
        </div>
        <div class="grid gap-2 rounded-lg border border-line bg-white p-2 text-sm font-semibold sm:inline-grid sm:grid-cols-2">
            <a class="rounded-md px-4 py-2 text-center <?= $view !== 'models' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/contracts.php">Contratos</a>
            <a class="rounded-md px-4 py-2 text-center <?= $view === 'models' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/contracts.php?view=models">Modelos</a>
        </div>
    </div>

    <?php if ($view === 'models'): ?>

        <!-- FORM NOVO/EDITAR MODELO -->
        <section class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-line bg-fog/50 px-6 py-4">
                <div>
                    <h3 class="font-bold text-ink"><?= $editTemplate ? 'Editar modelo' : 'Novo modelo' ?></h3>
                    <p class="mt-0.5 text-xs text-slate-500">Cadastre um texto editavel ou anexe o contrato pronto da empresa (PDF ou Word).</p>
                </div>
                <?php if ($editTemplate): ?>
                    <a href="/contracts.php?view=models" class="inline-flex items-center gap-1.5 rounded-md border border-line bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-fog">
                        + Novo modelo
                    </a>
                <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="template_id" value="<?= (int) ($editTemplate['id'] ?? 0) ?>">

                <!-- Nome + status (linha única) -->
                <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                    <label class="grid flex-1 gap-1.5 text-sm font-semibold">
                        Nome do modelo
                        <input class="min-h-10 rounded-lg border border-line bg-white px-3 text-sm outline-none transition focus:border-ink focus:ring-2 focus:ring-ink/10"
                               name="title" required
                               value="<?= e($editTemplate['title'] ?? '') ?>"
                               placeholder="Ex: Contrato de fechamento residencial">
                    </label>
                    <?php if ($editTemplate): ?>
                        <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-line bg-fog px-4 py-2.5 text-sm font-semibold select-none">
                            <input type="checkbox" name="active" value="1" class="h-4 w-4 accent-ink" <?= !empty($editTemplate['active']) ? 'checked' : '' ?>>
                            Modelo ativo
                        </label>
                    <?php endif; ?>
                </div>

                <!-- Grid principal: textarea | sidebar -->
                <div class="grid gap-6 xl:grid-cols-[1fr_340px]">

                    <!-- Coluna esquerda: textarea -->
                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold">Texto do contrato</span>
                            <span class="text-xs text-slate-400">Use os campos automaticos ao lado para personalizar</span>
                        </div>
                        <textarea class="h-[480px] w-full resize-y rounded-lg border border-line bg-white px-4 py-3 font-mono text-sm leading-relaxed outline-none transition focus:border-ink focus:ring-2 focus:ring-ink/10"
                                  name="body" spellcheck="false"><?= e($editTemplate['body'] ?? default_contract_template_body()) ?></textarea>
                    </div>

                    <!-- Coluna direita: sidebar -->
                    <aside class="grid content-start gap-4">

                        <!-- Campos automáticos -->
                        <div class="rounded-xl border border-line bg-white p-4">
                            <p class="mb-3 text-sm font-bold">Campos automaticos</p>
                            <p class="mb-3 text-xs text-slate-500">Clique para copiar e cole no texto.</p>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ([
                                    '{{cliente}}', '{{telefone}}', '{{endereco}}',
                                    '{{projeto}}', '{{projetista}}', '{{valor_fechado}}',
                                    '{{data_fechamento}}', '{{observacoes}}', '{{empresa}}',
                                    '{{empresa_documento}}', '{{empresa_email}}',
                                    '{{empresa_telefone}}', '{{empresa_endereco}}', '{{data_hoje}}'
                                ] as $field): ?>
                                    <button type="button"
                                            onclick="copyField(this)"
                                            data-field="<?= e($field) ?>"
                                            class="cursor-pointer rounded-md border border-line bg-fog px-2 py-1 font-mono text-xs text-slate-700 transition hover:border-ink hover:bg-ink hover:text-white active:scale-95">
                                        <?= e($field) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <p id="copy-msg" class="mt-2 hidden text-xs font-semibold text-green-600">Copiado!</p>
                        </div>

                        <!-- Arquivo da empresa -->
                        <div class="rounded-xl border border-line bg-white p-4">
                            <p class="mb-1 text-sm font-bold">Contrato pronto da empresa</p>
                            <p class="mb-3 text-xs text-slate-500">Anexe o PDF ou Word oficial. O cliente vai visualizar e assinar esse arquivo.</p>
                            <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-line bg-fog p-5 text-center transition hover:border-ink hover:bg-ink/5">
                                <svg class="h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                </svg>
                                <span class="text-xs font-semibold text-slate-600">Clique para escolher arquivo</span>
                                <span class="text-[11px] text-slate-400">PDF ou Word · max 15 MB</span>
                                <input class="hidden" type="file" name="source_file" accept="application/pdf,.pdf,.doc,.docx" id="source-file-input">
                            </label>
                            <p id="file-name-display" class="mt-2 hidden truncate rounded-md bg-fog px-3 py-2 text-xs font-semibold text-ink"></p>

                            <?php if (!empty($editTemplate['source_file_stored'])): ?>
                                <?php
                                $templateFileUrl = contract_template_file_url($editTemplate);
                                $isPdfTemplateFile = ($editTemplate['source_file_mime'] ?? '') === 'application/pdf'
                                    || strtolower(pathinfo($editTemplate['source_file_original'] ?? '', PATHINFO_EXTENSION)) === 'pdf';
                                ?>
                                <div class="mt-4 overflow-hidden rounded-lg border border-line bg-white">
                                    <div class="flex items-center justify-between gap-3 border-b border-line bg-fog/60 px-3 py-2">
                                        <div class="min-w-0">
                                            <strong class="block truncate text-xs"><?= e($editTemplate['source_file_original']) ?></strong>
                                            <span class="text-[11px] text-slate-400"><?= $isPdfTemplateFile ? 'PDF anexado' : 'Word anexado' ?></span>
                                        </div>
                                        <a class="shrink-0 rounded-md border border-line bg-white px-3 py-1 text-xs font-semibold hover:bg-fog"
                                           href="<?= e($templateFileUrl) ?>" target="_blank" rel="noopener">Abrir</a>
                                    </div>
                                    <?php if ($isPdfTemplateFile): ?>
                                        <iframe class="h-56 w-full bg-white" src="<?= e($templateFileUrl) ?>#toolbar=0" title="Previa"></iframe>
                                    <?php else: ?>
                                        <div class="px-4 py-5 text-xs text-slate-500">Previa disponivel apenas para PDF.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </aside>
                </div>

                <!-- Rodapé do form -->
                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
                    <a class="inline-flex min-h-10 items-center rounded-lg border border-line bg-white px-5 text-sm font-semibold text-slate-600 hover:bg-fog"
                       href="/contracts.php?view=models">Limpar</a>
                    <button class="min-h-10 rounded-lg bg-ink px-6 text-sm font-bold text-white hover:opacity-90 transition" type="submit">
                        <?= $editTemplate ? 'Salvar alteracoes' : 'Salvar modelo' ?>
                    </button>
                </div>
            </form>
        </section>

        <!-- LISTA DE MODELOS -->
        <section class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
            <div class="border-b border-line bg-fog/50 px-6 py-4">
                <h3 class="font-bold text-ink">Modelos cadastrados</h3>
                <p class="mt-0.5 text-xs text-slate-500"><?= count($templates) ?> modelo<?= count($templates) !== 1 ? 's' : '' ?> no total</p>
            </div>
            <?php if (!$templates): ?>
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-14 text-center text-slate-400">
                    <svg class="h-10 w-10 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                    <p class="text-sm font-semibold">Nenhum modelo cadastrado ainda.</p>
                    <p class="text-xs">Crie o primeiro modelo acima.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-line">
                    <?php foreach ($templates as $template): ?>
                        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="truncate text-sm"><?= e($template['title']) ?></strong>
                                    <?php if (!empty($template['active'])): ?>
                                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-700 ring-1 ring-green-200">Ativo</span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">Inativo</span>
                                    <?php endif; ?>
                                    <?php if (!empty($template['source_file_stored'])): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 ring-1 ring-blue-200">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
                                            Arquivo anexado
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Criado em <?= e(date('d/m/Y', strtotime($template['created_at']))) ?>
                                    <?= !empty($template['updated_at']) ? ' · Atualizado em ' . e(date('d/m/Y', strtotime($template['updated_at']))) : '' ?>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <?php if (!empty($template['source_file_stored'])): ?>
                                    <a class="inline-flex min-h-9 items-center rounded-lg border border-line bg-white px-3 text-xs font-semibold hover:bg-fog"
                                       href="<?= e(contract_template_file_url($template)) ?>" target="_blank" rel="noopener">
                                        Ver arquivo
                                    </a>
                                <?php endif; ?>
                                <a class="inline-flex min-h-9 items-center rounded-lg border border-line bg-white px-3 text-xs font-semibold hover:bg-fog"
                                   href="/contracts.php?view=models&template=<?= (int) $template['id'] ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Excluir este modelo? Contratos ja gerados nao serao apagados.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                                    <button class="inline-flex min-h-9 items-center rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <script>
        function copyField(btn) {
            const field = btn.dataset.field;
            navigator.clipboard.writeText(field).then(() => {
                const msg = document.getElementById('copy-msg');
                msg.textContent = '"' + field + '" copiado!';
                msg.classList.remove('hidden');
                setTimeout(() => msg.classList.add('hidden'), 2000);
            });
        }
        document.getElementById('source-file-input')?.addEventListener('change', function () {
            const display = document.getElementById('file-name-display');
            if (this.files && this.files[0]) {
                display.textContent = '📎 ' + this.files[0].name;
                display.classList.remove('hidden');
            } else {
                display.classList.add('hidden');
            }
        });
        </script>

    <?php else: ?>

        <!-- PASSO A PASSO -->
        <details class="group overflow-hidden rounded-xl border border-line bg-white shadow-sm" <?= !$contracts ? 'open' : '' ?>>
            <summary class="flex cursor-pointer list-none items-center justify-between px-6 py-4 hover:bg-fog/50">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-white">?</span>
                    <div>
                        <p class="font-bold text-ink">Como funciona o modulo de contratos?</p>
                        <p class="text-xs text-slate-500">Entenda o fluxo completo em 4 passos simples</p>
                    </div>
                </div>
                <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>

            <div class="border-t border-line px-6 pb-6 pt-5">
                <div class="grid gap-6 md:grid-cols-4">

                    <div class="flex gap-3 md:flex-col md:gap-2">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-white">1</div>
                        <div>
                            <p class="font-bold text-sm text-ink">Crie um modelo</p>
                            <p class="mt-1 text-xs text-slate-500 leading-5">
                                Va em <strong>Modelos</strong> e cadastre o texto do contrato usando os campos automaticos
                                (<code class="rounded bg-fog px-1 font-mono text-[11px]">{{cliente}}</code>,
                                <code class="rounded bg-fog px-1 font-mono text-[11px]">{{projeto}}</code> etc.)
                                — ou anexe o PDF/Word da empresa ja pronto.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 md:flex-col md:gap-2">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-white">2</div>
                        <div>
                            <p class="font-bold text-sm text-ink">Gere o contrato</p>
                            <p class="mt-1 text-xs text-slate-500 leading-5">
                                Selecione o projeto, escolha o modelo e clique em <strong>Gerar</strong>.
                                O Arquidesk preenche automaticamente nome do cliente, projeto, valor, data e muito mais.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 md:flex-col md:gap-2">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-white">3</div>
                        <div>
                            <p class="font-bold text-sm text-ink">Envie o link ao cliente</p>
                            <p class="mt-1 text-xs text-slate-500 leading-5">
                                Copie o <strong>Link cliente</strong> e mande por WhatsApp, e-mail ou onde preferir.
                                O cliente abre no celular ou computador sem precisar de cadastro.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 md:flex-col md:gap-2">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-white">4</div>
                        <div>
                            <p class="font-bold text-sm text-ink">Cliente assina</p>
                            <p class="mt-1 text-xs text-slate-500 leading-5">O cliente escolhe como assinar:</p>
                            <ul class="mt-2 grid gap-1.5 text-xs text-slate-500">
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">✓</span>
                                    <span><strong>Desenhar</strong> — assina com o dedo ou mouse. A assinatura e gravada no PDF do contrato.</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700">✓</span>
                                    <span><strong>Gov.br</strong> — baixa o PDF, assina no portal oficial e sobe o arquivo assinado.</span>
                                </li>
                            </ul>
                            <p class="mt-2 text-xs text-slate-500">Apos assinar o status muda e voce pode baixar o <strong>comprovante</strong>.</p>
                        </div>
                    </div>

                </div>

                <div class="mt-5 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <p class="text-xs text-amber-800 leading-5">
                        <strong>Dica:</strong> A assinatura desenhada registra nome, CPF/CNPJ, e-mail, IP e horario do assinante — gerando um comprovante de aceite eletronico.
                        Para contratos que exigem validade juridica ICP-Brasil, oriente o cliente a usar a opcao <strong>gov.br</strong>.
                    </p>
                </div>
            </div>
        </details>

        <!-- FORM GERAR CONTRATO -->
        <section class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
            <div class="border-b border-line bg-fog/50 px-6 py-4">
                <h3 class="font-bold text-ink">Gerar contrato para projeto</h3>
                <p class="mt-0.5 text-xs text-slate-500">Selecione o projeto e o modelo. Os dados do cliente serao preenchidos automaticamente.</p>
            </div>
            <form method="post" class="grid gap-4 p-6 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_contract">
                <label class="grid gap-1 text-sm font-semibold">Projeto
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="project_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>"><?= e($project['client_name'] . ' - ' . $project['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Modelo
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="template_id" required>
                        <?php foreach ($templates as $template): ?>
                            <?php if (empty($template['active'])) continue; ?>
                            <option value="<?= (int) $template['id'] ?>"><?= e($template['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Titulo opcional
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="contract_title" placeholder="Contrato de fechamento">
                </label>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Gerar</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-line bg-fog/50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-bold text-ink">Contratos gerados</h3>
                    <p class="mt-0.5 text-xs text-slate-500"><?= count($contracts) ?> contrato<?= count($contracts) !== 1 ? 's' : '' ?> no total</p>
                </div>
                <form method="get" class="flex gap-2">
                    <input class="min-h-9 w-full rounded-lg border border-line bg-white px-3 text-sm outline-none focus:border-ink sm:w-64" name="q" value="<?= e($search) ?>" placeholder="Buscar cliente, projeto...">
                    <button class="min-h-9 rounded-lg border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Buscar</button>
                    <?php if ($search): ?>
                        <a class="inline-flex min-h-9 items-center rounded-lg border border-line bg-white px-3 text-sm font-semibold text-slate-500 hover:bg-fog" href="/contracts.php">✕</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="grid gap-3 p-4">
                <?php if (!$contracts): ?>
                    <div class="flex flex-col items-center justify-center gap-2 py-12 text-center text-slate-400">
                        <svg class="h-10 w-10 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                        <p class="text-sm font-semibold"><?= $search ? 'Nenhum contrato encontrado para esta busca.' : 'Nenhum contrato gerado ainda.' ?></p>
                        <?php if (!$search): ?><p class="text-xs">Use o formulario acima para gerar o primeiro contrato.</p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($contracts as $contract): ?>
                    <?php
                    $publicUrl = '/contract-public.php?t=' . $contract['public_token'];
                    $printUrl = '/contract-print.php?t=' . $contract['public_token'];
                    $proofUrl = '/contract-proof.php?t=' . $contract['public_token'];
                    $hasSourceFile = !empty($contract['source_file_stored']);
                    $hasUploadedSignedFile = !empty($contract['signed_file_stored']);
                    $hasVisualSignature = in_array($contract['status'], ['ACCEPTED', 'SIGNED_MANUAL'], true);
                    $isCompleted = $hasUploadedSignedFile || $hasVisualSignature;
                    ?>
                    <article class="rounded-lg border border-line bg-white p-4">
                        <div class="grid gap-4 xl:grid-cols-[1fr_auto] xl:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="max-w-full truncate text-base" title="<?= e($contract['title']) ?>"><?= e($contract['title']) ?></strong>
                                    <span class="inline-flex rounded-full bg-fog px-2 py-1 text-xs font-semibold"><?= e(contract_status_label($contract['status'])) ?></span>
                                    <span class="inline-flex rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-500 ring-1 ring-line"><?= e(contract_method_label($contract['signature_method'])) ?></span>
                                </div>
                                <div class="mt-2 grid gap-2 text-sm text-slate-600 md:grid-cols-3">
                                    <div class="min-w-0">
                                        <span class="block text-xs font-semibold uppercase text-slate-400">Cliente</span>
                                        <span class="block truncate"><?= e($contract['client_name']) ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-xs font-semibold uppercase text-slate-400">Projeto</span>
                                        <span class="block truncate"><?= e($contract['project_name']) ?></span>
                                        <span class="block text-xs text-slate-400"><?= e(stage_label($contract['current_stage'])) ?></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-semibold uppercase text-slate-400">Criado em</span>
                                        <span><?= e(date('d/m/Y H:i', strtotime($contract['created_at']))) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 xl:max-w-[620px] xl:justify-end">
                                <?php if (!$hasSourceFile): ?>
                                    <a class="inline-flex min-h-10 items-center rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" href="<?= e($printUrl) ?>" target="_blank" rel="noopener">PDF</a>
                                <?php endif; ?>
                                <?php if ($hasSourceFile): ?>
                                    <a class="inline-flex min-h-10 items-center rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" href="<?= e(contract_template_file_url($contract)) ?>" target="_blank" rel="noopener">Original</a>
                                <?php endif; ?>
                                <a class="inline-flex min-h-10 items-center rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Link cliente</a>
                                <?php if ($hasUploadedSignedFile): ?>
                                    <a class="inline-flex min-h-10 items-center rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" href="<?= e(contract_signed_file_url($contract)) ?>" target="_blank" rel="noopener">Assinado</a>
                                <?php elseif ($hasVisualSignature): ?>
                                    <a class="inline-flex min-h-10 items-center rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" href="<?= e($printUrl) ?>" target="_blank" rel="noopener">Assinado</a>
                                <?php endif; ?>
                                <?php if ($isCompleted): ?>
                                    <a class="inline-flex min-h-10 items-center rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" href="<?= e($proofUrl) ?>" target="_blank" rel="noopener">Comprovante</a>
                                <?php endif; ?>
                                <?php if ($contract['status'] !== 'CANCELED'): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel_contract">
                                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                        <button class="inline-flex min-h-10 items-center rounded-md border border-red-200 px-3 text-xs font-semibold text-red-600 hover:bg-red-50" type="submit">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('Excluir este contrato definitivamente?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_contract">
                                    <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                    <button class="inline-flex min-h-10 items-center rounded-md border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
