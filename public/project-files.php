<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);

$companyId = (int) $user['company_id'];
$id        = (int) ($_GET['id'] ?? 0);

try {
    db()->exec("create table if not exists project_files (
      id int unsigned auto_increment primary key,
      company_id int unsigned not null,
      client_project_id int unsigned not null,
      uploaded_by_user_id int unsigned null,
      original_name varchar(255) not null,
      stored_name varchar(255) not null,
      file_size int unsigned not null default 0,
      mime_type varchar(100) not null default '',
      category varchar(40) not null default 'GERAL',
      created_at timestamp not null default current_timestamp,
      index pf_project_idx (client_project_id, company_id)
    ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
} catch (Throwable) {}

if ($id <= 0) {
    $params = [$companyId];
    $where = 'where p.company_id = ?';
    if ($user['role'] === 'PROJETISTA') {
        $where .= ' and p.designer_id = ?';
        $params[] = (int) $user['id'];
    }

    $projectsStmt = db()->prepare(
        "select p.id, p.client_name, p.project_name, p.current_stage, p.updated_at, u.name as designer_name,
                count(pf.id) as file_count, max(pf.created_at) as last_file_at
         from client_projects p
         left join users u on u.id = p.designer_id
         left join project_files pf on pf.client_project_id = p.id and pf.company_id = p.company_id
         {$where}
         group by p.id, p.client_name, p.project_name, p.current_stage, p.updated_at, u.name
         order by last_file_at desc, p.updated_at desc"
    );
    $projectsStmt->execute($params);
    $uploadProjects = $projectsStmt->fetchAll();

    $recentStmt = db()->prepare(
        "select pf.*, p.client_name, p.project_name
         from project_files pf
         join client_projects p on p.id = pf.client_project_id and p.company_id = pf.company_id
         where pf.company_id = ?" . ($user['role'] === 'PROJETISTA' ? ' and p.designer_id = ?' : '') . "
         order by pf.created_at desc
         limit 8"
    );
    $recentParams = [$companyId];
    if ($user['role'] === 'PROJETISTA') {
        $recentParams[] = (int) $user['id'];
    }
    $recentStmt->execute($recentParams);
    $recentFiles = $recentStmt->fetchAll();

    $pageTitle = 'Arquivos / Uploads';
    require __DIR__ . '/../app/includes/header.php';
    require __DIR__ . '/../app/includes/sidebar.php';
    ?>
<section class="grid gap-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-bold">Arquivos / Uploads</h2>
            <p class="text-sm text-slate-500">Selecione um projeto para ver, enviar ou organizar arquivos.</p>
        </div>
        <a href="/projects.php?layout=kanban" class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog">Voltar aos projetos</a>
    </div>

    <?php if ($recentFiles): ?>
        <div class="rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Uploads recentes</div>
            <div class="divide-y divide-line">
                <?php foreach ($recentFiles as $file): ?>
                    <?php $fileUrl = '/uploads/projects/' . $companyId . '/' . (int) $file['client_project_id'] . '/' . e($file['stored_name']); ?>
                    <a href="<?= $fileUrl ?>" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-fog">
                        <span class="min-w-0">
                            <strong class="block truncate"><?= e($file['original_name']) ?></strong>
                            <span class="text-xs text-slate-500"><?= e($file['client_name']) ?> · <?= e($file['project_name']) ?></span>
                        </span>
                        <span class="shrink-0 text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($file['created_at']))) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <?php if (!$uploadProjects): ?>
            <div class="rounded-lg border border-dashed border-line bg-white p-8 text-center text-sm text-slate-400">Nenhum projeto disponível para uploads.</div>
        <?php endif; ?>
        <?php foreach ($uploadProjects as $item): ?>
            <a href="/project-files.php?id=<?= (int) $item['id'] ?>" class="rounded-lg border border-line bg-white p-4 shadow-sm transition hover:border-ink/30 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <strong class="block truncate text-sm"><?= e($item['client_name']) ?></strong>
                        <span class="mt-1 block truncate text-xs text-slate-500"><?= e($item['project_name']) ?></span>
                    </div>
                    <span class="rounded-full bg-fog px-2 py-0.5 text-[10px] font-semibold text-slate-600"><?= e(stage_label($item['current_stage'])) ?></span>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                    <span><?= (int) $item['file_count'] ?> arquivo<?= (int) $item['file_count'] === 1 ? '' : 's' ?></span>
                    <span><?= $item['last_file_at'] ? e(date('d/m/Y', strtotime($item['last_file_at']))) : 'Sem uploads' ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
<?php
    exit;
}

$stmt = db()->prepare(
    'select p.*, u.name as designer_name
     from client_projects p
     left join users u on u.id = p.designer_id
     where p.id = ? and p.company_id = ? limit 1'
);
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/projects.php');
}

if ($user['role'] === 'PROJETISTA' && (int) $project['designer_id'] !== (int) $user['id']) {
    redirect('/projects.php');
}

// Carrega arquivos
$filesStmt = db()->prepare(
    'select pf.*, u.name as uploaded_by_name
     from project_files pf
     left join users u on u.id = pf.uploaded_by_user_id
     where pf.client_project_id = ? and pf.company_id = ?
     order by pf.created_at desc'
);
$filesStmt->execute([$id, $companyId]);
$files = $filesStmt->fetchAll();

$categoryLabels = [
    'GERAL'    => 'Geral',
    'PLANTA'   => 'Planta',
    'CONTRATO' => 'Contrato',
    'MEDICAO'  => 'Medição',
    'MONTAGEM' => 'Montagem',
    'FOTO'     => 'Foto',
    'OUTRO'    => 'Outro',
];

$canEdit   = $user['role'] !== 'CONFERENTE';
$canDelete = in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true);

$pageTitle = 'Arquivos — ' . $project['project_name'];
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-4">

    
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <a href="/projects.php?layout=kanban" class="hover:text-ink">Projetos</a>
                <span>›</span>
                <span><?= e($project['client_name']) ?></span>
                <span>›</span>
                <span class="font-semibold text-ink">Arquivos</span>
            </div>
            <h1 class="mt-1 text-xl font-bold"><?= e($project['project_name']) ?></h1>
            <p class="text-sm text-slate-500">
                <?= e(stage_label($project['current_stage'])) ?>
                <?= $project['designer_name'] ? '· ' . e($project['designer_name']) : '' ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/project-history.php?id=<?= $id ?>" class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog">Histórico</a>
            <?php if (can_write_project($user, $project['current_stage'])): ?>
                <a href="/project-form.php?id=<?= $id ?>" class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog">Editar projeto</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canEdit): ?>
    
    <div id="drop-zone"
         class="relative flex min-h-40 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-line bg-white p-6 text-center transition hover:border-ink/40 hover:bg-fog/50">
        <input type="file" id="file-input" class="absolute inset-0 cursor-pointer opacity-0" multiple
               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <div>
            <p class="font-semibold text-slate-700">Arraste arquivos aqui ou clique para selecionar</p>
            <p class="mt-1 text-xs text-slate-400">Imagens, PDF, Word, Excel, ZIP · Máximo 10 MB por arquivo</p>
        </div>
        <div class="flex flex-wrap justify-center gap-2">
            <?php foreach ($categoryLabels as $val => $label): ?>
                <label class="flex cursor-pointer items-center gap-1.5 rounded-full border border-line bg-fog px-3 py-1 text-xs font-semibold has-[:checked]:border-ink has-[:checked]:bg-ink has-[:checked]:text-white">
                    <input type="radio" name="category" value="<?= e($val) ?>" class="sr-only" <?= $val === 'GERAL' ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    
    <div id="upload-progress-bar" class="hidden">
        <div class="flex items-center justify-between text-sm font-semibold">
            <span id="upload-label">Enviando...</span>
            <span id="upload-percent">0%</span>
        </div>
        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-stone-100">
            <div id="upload-bar" class="h-2 rounded-full bg-emerald-600 transition-all" style="width:0%"></div>
        </div>
    </div>
    <?php endif; ?>

    
    <div id="category-filters" class="flex flex-wrap gap-2">
        <button type="button" class="js-cat-filter inline-flex min-h-8 items-center rounded-full border border-line bg-white px-3 text-xs font-semibold active-filter" data-cat="">Todos</button>
        <?php foreach ($categoryLabels as $val => $label): ?>
            <button type="button" class="js-cat-filter inline-flex min-h-8 items-center rounded-full border border-line bg-white px-3 text-xs font-semibold" data-cat="<?= e($val) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>

    
    <div id="files-grid" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php if (!$files): ?>
            <div id="empty-state" class="col-span-full rounded-lg border border-dashed border-line bg-white p-8 text-center text-sm text-slate-400">
                Nenhum arquivo ainda. <?= $canEdit ? 'Arraste arquivos acima para começar.' : '' ?>
            </div>
        <?php endif; ?>
        <?php foreach ($files as $file):
            $isImage  = str_starts_with($file['mime_type'], 'image/');
            $fileUrl  = '/uploads/projects/' . $companyId . '/' . $id . '/' . e($file['stored_name']);
            $sizeKb   = $file['file_size'] >= 1048576
                ? number_format($file['file_size'] / 1048576, 1, ',', '.') . ' MB'
                : number_format($file['file_size'] / 1024, 0, ',', '.') . ' KB';
        ?>
            <article class="js-file-card group relative flex flex-col overflow-hidden rounded-lg border border-line bg-white shadow-sm transition hover:shadow-md"
                     data-cat="<?= e($file['category']) ?>">
                
                <a href="<?= $fileUrl ?>" target="_blank" rel="noopener"
                   class="block h-36 overflow-hidden bg-fog">
                    <?php if ($isImage): ?>
                        <img src="<?= $fileUrl ?>" alt="<?= e($file['original_name']) ?>"
                             class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                    <?php else: ?>
                        <div class="flex h-full items-center justify-center text-4xl text-slate-300">
                            <?php
                            $icon = '📄';
                            if (str_contains($file['mime_type'], 'pdf')) $icon = '📕';
                            elseif (str_contains($file['mime_type'], 'word')) $icon = '📘';
                            elseif (str_contains($file['mime_type'], 'excel') || str_contains($file['mime_type'], 'spreadsheet')) $icon = '📗';
                            elseif (str_contains($file['mime_type'], 'zip')) $icon = '🗜️';
                            echo $icon;
                            ?>
                        </div>
                    <?php endif; ?>
                </a>
                
                <div class="flex flex-1 flex-col gap-1 p-3">
                    <a href="<?= $fileUrl ?>" target="_blank" rel="noopener"
                       class="block truncate text-sm font-semibold leading-snug hover:underline"
                       title="<?= e($file['original_name']) ?>">
                        <?= e($file['original_name']) ?>
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-fog px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                            <?= e($categoryLabels[$file['category']] ?? $file['category']) ?>
                        </span>
                        <span class="text-[11px] text-slate-400"><?= $sizeKb ?></span>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        <?= e(date('d/m/Y H:i', strtotime($file['created_at']))) ?>
                        <?= $file['uploaded_by_name'] ? '· ' . e($file['uploaded_by_name']) : '' ?>
                    </p>
                </div>
                <?php if ($canDelete): ?>
                <button type="button"
                        class="js-delete-file absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-white/90 text-red-400 opacity-0 shadow-sm transition hover:bg-white hover:text-red-600 group-hover:opacity-100"
                        data-id="<?= (int) $file['id'] ?>"
                        aria-label="Excluir arquivo">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

</section>

<div id="toast-root" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script>
(function () {
    const projectId  = <?= $id ?>;
    const csrf       = <?= json_encode(csrf_token()) ?>;
    const canEdit    = <?= $canEdit ? 'true' : 'false' ?>;
    const canDelete  = <?= $canDelete ? 'true' : 'false' ?>;
    const toastRoot  = document.getElementById('toast-root');

    // ── Toast ──────────────────────────────────────────────────────────────────
    function showToast(msg, type = 'default') {
        const el = document.createElement('div');
        el.className = 'flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-white shadow-lg '
            + (type === 'error' ? 'bg-red-600' : 'bg-ink');
        el.style.minWidth = '220px';
        el.textContent = msg;
        toastRoot.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    // ── Filtros de categoria ───────────────────────────────────────────────────
    document.querySelectorAll('.js-cat-filter').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.js-cat-filter').forEach(b => b.classList.remove('bg-ink', 'text-white', 'border-ink'));
            btn.classList.add('bg-ink', 'text-white', 'border-ink');
            const cat = btn.dataset.cat;
            document.querySelectorAll('.js-file-card').forEach(function (card) {
                card.style.display = (!cat || card.dataset.cat === cat) ? '' : 'none';
            });
            const empty = document.getElementById('empty-state');
            if (empty) return;
            const visible = document.querySelectorAll('.js-file-card:not([style*="none"])').length;
            document.querySelectorAll('.js-no-cat').forEach(e => e.remove());
            if (!visible) {
                const msg = document.createElement('div');
                msg.className = 'js-no-cat col-span-full rounded-lg border border-dashed border-line bg-white p-8 text-center text-sm text-slate-400';
                msg.textContent = 'Nenhum arquivo nesta categoria.';
                document.getElementById('files-grid').appendChild(msg);
            }
        });
    });

    // ── Exclusão de arquivo ────────────────────────────────────────────────────
    document.querySelectorAll('.js-delete-file').forEach(attachDelete);

    function attachDelete(btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Excluir "' + (btn.closest('article')?.querySelector('a')?.textContent?.trim() || 'este arquivo') + '"?')) return;
            btn.disabled = true;
            try {
                const body = new URLSearchParams({ csrf_token: csrf, file_id: btn.dataset.id });
                const resp = await fetch('/project-file-delete.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body,
                });
                const data = await resp.json();
                if (data.ok) {
                    const card = btn.closest('article');
                    card.style.transition = 'opacity .25s';
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.remove();
                        checkEmpty();
                    }, 260);
                    showToast('Arquivo excluído.');
                } else {
                    showToast(data.error || 'Erro ao excluir.', 'error');
                    btn.disabled = false;
                }
            } catch (e) {
                showToast('Erro de conexão.', 'error');
                btn.disabled = false;
            }
        });
    }

    function checkEmpty() {
        const grid  = document.getElementById('files-grid');
        const cards = grid.querySelectorAll('.js-file-card');
        if (!cards.length && !grid.querySelector('#empty-state')) {
            const msg = document.createElement('div');
            msg.id = 'empty-state';
            msg.className = 'col-span-full rounded-lg border border-dashed border-line bg-white p-8 text-center text-sm text-slate-400';
            msg.textContent = 'Nenhum arquivo ainda.' + (canEdit ? ' Arraste arquivos acima para começar.' : '');
            grid.appendChild(msg);
        }
    }

    if (!canEdit) return;

    // ── Upload ─────────────────────────────────────────────────────────────────
    const dropZone  = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const progBar   = document.getElementById('upload-progress-bar');
    const progFill  = document.getElementById('upload-bar');
    const progLabel = document.getElementById('upload-label');
    const progPct   = document.getElementById('upload-percent');

    function getCategory() {
        return document.querySelector('input[name="category"]:checked')?.value || 'GERAL';
    }

    // Destaque ao arrastar sobre a zona
    ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, function (e) {
        e.preventDefault(); dropZone.classList.add('border-ink', 'bg-emerald-50/40');
    }));
    ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, function (e) {
        e.preventDefault(); dropZone.classList.remove('border-ink', 'bg-emerald-50/40');
    }));

    dropZone.addEventListener('drop', function (e) {
        const files = Array.from(e.dataTransfer.files);
        if (files.length) uploadFiles(files);
    });

    fileInput.addEventListener('change', function () {
        if (fileInput.files.length) uploadFiles(Array.from(fileInput.files));
        fileInput.value = '';
    });

    async function uploadFiles(files) {
        progBar.classList.remove('hidden');
        let done = 0;
        const total = files.length;

        for (const file of files) {
            progLabel.textContent = 'Enviando ' + file.name + '…';
            const pct = Math.round((done / total) * 100);
            progFill.style.width = pct + '%';
            progPct.textContent  = pct + '%';

            const formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('project_id', projectId);
            formData.append('category', getCategory());
            formData.append('file', file);

            try {
                const resp = await fetch('/project-file-upload.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: formData,
                });
                const data = await resp.json();
                if (data.ok) {
                    done++;
                    appendFileCard(data.file);
                } else {
                    showToast(data.error || 'Erro ao enviar ' + file.name, 'error');
                }
            } catch (e) {
                showToast('Erro de conexão ao enviar ' + file.name, 'error');
            }
        }

        progFill.style.width = '100%';
        progPct.textContent  = '100%';
        progLabel.textContent = done + ' de ' + total + ' enviado(s).';
        setTimeout(() => { progBar.classList.add('hidden'); progFill.style.width = '0%'; }, 2000);

        if (done > 0) showToast(done + (done === 1 ? ' arquivo enviado.' : ' arquivos enviados.'));
    }

    const categoryLabels = <?= json_encode($categoryLabels) ?>;

    function appendFileCard(f) {
        const empty = document.getElementById('empty-state');
        if (empty) empty.remove();

        const isImage = f.mime_type.startsWith('image/');
        const sizeLabel = f.file_size >= 1048576
            ? (f.file_size / 1048576).toFixed(1).replace('.', ',') + ' MB'
            : Math.round(f.file_size / 1024) + ' KB';

        const article = document.createElement('article');
        article.className = 'js-file-card group relative flex flex-col overflow-hidden rounded-lg border border-line bg-white shadow-sm transition hover:shadow-md';
        article.dataset.cat = f.category;

        const previewHtml = isImage
            ? '<img src="' + f.url + '" alt="" class="h-full w-full object-cover transition group-hover:scale-[1.02]">'
            : '<div class="flex h-full items-center justify-center text-4xl text-slate-300">📄</div>';

        const catLabel = categoryLabels[f.category] || f.category;
        const deleteBtn = canDelete
            ? '<button type="button" class="js-delete-file absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-white/90 text-red-400 opacity-0 shadow-sm transition hover:bg-white hover:text-red-600 group-hover:opacity-100" data-id="' + f.id + '" aria-label="Excluir arquivo"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg></button>'
            : '';

        article.innerHTML =
            '<a href="' + f.url + '" target="_blank" rel="noopener" class="block h-36 overflow-hidden bg-fog">' + previewHtml + '</a>' +
            '<div class="flex flex-1 flex-col gap-1 p-3">' +
                '<a href="' + f.url + '" target="_blank" rel="noopener" class="block truncate text-sm font-semibold leading-snug hover:underline" title="' + f.original_name + '">' + f.original_name + '</a>' +
                '<div class="flex items-center gap-2">' +
                    '<span class="rounded-full bg-fog px-2 py-0.5 text-[10px] font-semibold text-slate-600">' + catLabel + '</span>' +
                    '<span class="text-[11px] text-slate-400">' + sizeLabel + '</span>' +
                '</div>' +
                '<p class="text-[11px] text-slate-400">' + f.created_at + (f.uploaded_by ? ' · ' + f.uploaded_by : '') + '</p>' +
            '</div>' + deleteBtn;

        document.getElementById('files-grid').prepend(article);

        if (canDelete) {
            const btn = article.querySelector('.js-delete-file');
            if (btn) attachDelete(btn);
        }
    }
})();
</script>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
