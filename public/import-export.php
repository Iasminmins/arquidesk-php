<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
$companyId = (int) $user['company_id'];
$isAdmin = $user['role'] === 'ADMIN_EMPRESA';
$isDesigner = $user['role'] === 'PROJETISTA';
$canImport = $isAdmin || $isDesigner;

$type = $_POST['import_type'] ?? $_GET['type'] ?? 'Completo';
$preview = [];
$summary = [];
$errors = [];
$importSuccess = '';

$stageMap = [
    'PROJETO' => 'PROJETO', 'PROJETOS' => 'PROJETO',
    'NEGOCIACAO' => 'NEGOCIACAO', 'NEGOCIAÇÃO' => 'NEGOCIACAO',
    'CONFERENCIA' => 'CONFERENCIA', 'CONFERÊNCIA' => 'CONFERENCIA',
    'MONTAGEM' => 'MONTAGEM',
    'ASSISTENCIA' => 'ASSISTENCIA', 'ASSISTÊNCIA' => 'ASSISTENCIA',
    'FINALIZADO' => 'FINALIZADO', 'FINALIZADOS' => 'FINALIZADO',
];

function parse_csv_file(string $path): array
{
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) return [];
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $delimiter = ',';
    $firstLine = fgets($handle);
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    if ($firstLine && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
        $delimiter = ';';
    }
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) { fclose($handle); return []; }
    $headers = array_map('trim', $headers);
    while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($line) !== count($headers)) continue;
        $row = array_combine($headers, $line);
        if (implode('', $line) === '') continue;
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function csv_val(array $row, string $key): string
{
    return trim($row[$key] ?? '');
}

function csv_num(array $row, string $key): float
{
    $raw = csv_val($row, $key);
    $raw = str_replace(['R$', ' ', '.'], '', $raw);
    $raw = str_replace(',', '.', $raw);
    return (float) $raw;
}

function csv_date(array $row, string $key): ?string
{
    $raw = csv_val($row, $key);
    if (!$raw) return null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    $t = strtotime($raw);
    return $t ? date('Y-m-d', $t) : substr($raw, 0, 10);
}

function build_designer_map(int $companyId): array
{
    $stmt = db()->prepare("select id, name from users where company_id = ? and active = 1");
    $stmt->execute([$companyId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[mb_strtolower(trim($row['name']))] = (int) $row['id'];
    }
    return $map;
}

$requiredFields = [
    'Projetos' => ['Nome do cliente', 'Telefone', 'Nome do projeto'],
    'Vendas' => ['Cliente', 'Projeto', 'Valor vendido', 'Forma de pagamento', 'Data da venda'],
    'Pagamentos' => ['Cliente', 'Projeto', 'Valor pago', 'Data de pagamento'],
    'Metas' => ['Projetista', 'Mes', 'Ano', 'Valor da meta'],
];

// Handle file upload and preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK && $canImport) {
    $tmpPath = $_FILES['import_file']['tmp_name'];
    $preview = parse_csv_file($tmpPath);
    $importType = $_POST['import_type'] ?? 'Projetos';

    if (!$preview) {
        $errors[] = 'Arquivo vazio ou formato invalido. Use CSV com cabecalhos.';
    } else {
        $required = $requiredFields[$importType] ?? $requiredFields['Projetos'];
        $headers = array_keys($preview[0]);
        foreach ($required as $field) {
            if (!in_array($field, $headers, true)) {
                $errors[] = "Coluna obrigatoria ausente: {$field}";
            }
        }
        foreach ($preview as $i => $row) {
            foreach ($required as $field) {
                if (empty(csv_val($row, $field))) {
                    $errors[] = "Linha " . ($i + 2) . ": campo obrigatorio vazio: {$field}";
                }
            }
        }
        $summary[] = ['label' => $importType, 'count' => count($preview)];

        // Save to temp for confirm step
        $tempFile = sys_get_temp_dir() . '/arquidesk-import-' . session_id() . '.csv';
        copy($tmpPath, $tempFile);
        $_SESSION['import_temp'] = $tempFile;
        $_SESSION['import_type'] = $importType;
        $_SESSION['import_count'] = count($preview);
    }
}

// Confirm import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import']) && $canImport) {
    $importType = $_SESSION['import_type'] ?? 'Projetos';
    $tempFile = $_SESSION['import_temp'] ?? '';
    if (!$tempFile || !is_readable($tempFile)) {
        $errors[] = 'Sessao expirada. Envie o arquivo novamente.';
    } else {
        $rows = parse_csv_file($tempFile);
        $designerMap = build_designer_map($companyId);
        $successCount = 0;
        $errorCount = 0;

        try {
            db()->beginTransaction();

            if ($importType === 'Projetos') {
                foreach ($rows as $row) {
                    $designerName = mb_strtolower(trim(csv_val($row, 'Projetista responsavel')));
                    $designerId = $isDesigner ? (int) $user['id'] : ($designerMap[$designerName] ?? null);
                    $wantedStage = strtoupper(csv_val($row, 'Etapa desejada'));
                    $stage = $stageMap[$wantedStage] ?? 'PROJETO';

                    $stmt = db()->prepare('insert into client_projects (company_id, designer_id, client_name, client_address, client_phone, project_name, current_stage, project_status, entry_date, presentation_date, negotiation_status, new_proposal_value, closed_value, closing_date, conference_status, sent_to_factory_date, billing_date, assembly_status, assembly_started_date, assembly_finished_date, assistance_status, assistance_date, order_date, notes) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([
                        $companyId, $designerId,
                        csv_val($row, 'Nome do cliente'),
                        csv_val($row, 'Endereco do cliente') ?: null,
                        csv_val($row, 'Telefone'),
                        csv_val($row, 'Nome do projeto'),
                        $stage,
                        csv_val($row, 'Status') ?: null,
                        csv_date($row, 'Data de entrada'),
                        csv_date($row, 'Data de apresentacao'),
                        csv_val($row, 'Status da negociacao') ?: null,
                        csv_num($row, 'Valor nova proposta') ?: null,
                        csv_num($row, 'Valor fechado') ?: null,
                        csv_date($row, 'Data de fechamento'),
                        csv_val($row, 'Status conferencia') ?: null,
                        csv_date($row, 'Data envio fabrica'),
                        csv_date($row, 'Data faturamento'),
                        csv_val($row, 'Status montagem') ?: null,
                        csv_date($row, 'Data inicio montagem'),
                        csv_date($row, 'Data fim montagem'),
                        csv_val($row, 'Status assistencia') ?: null,
                        csv_date($row, 'Data assistencia'),
                        csv_date($row, 'Data pedido'),
                        csv_val($row, 'Observacoes') ?: null,
                    ]);
                    $successCount++;
                }
            } elseif ($importType === 'Vendas') {
                foreach ($rows as $row) {
                    $designerName = mb_strtolower(trim(csv_val($row, 'Projetista')));
                    $designerId = $isDesigner ? (int) $user['id'] : ($designerMap[$designerName] ?? null);
                    $stmt = db()->prepare('insert into financial_sales (company_id, designer_id, client_name, project_name, sold_value, payment_method, sale_date, notes) values (?,?,?,?,?,?,?,?)');
                    $stmt->execute([
                        $companyId, $designerId,
                        csv_val($row, 'Cliente'),
                        csv_val($row, 'Projeto'),
                        csv_num($row, 'Valor vendido'),
                        csv_val($row, 'Forma de pagamento'),
                        csv_date($row, 'Data da venda'),
                        csv_val($row, 'Observacoes') ?: null,
                    ]);
                    $successCount++;
                }
            } elseif ($importType === 'Pagamentos') {
                // Build sale lookup
                $salesStmt = db()->prepare('select id, client_name, project_name from financial_sales where company_id = ?');
                $salesStmt->execute([$companyId]);
                $saleMap = [];
                foreach ($salesStmt->fetchAll() as $s) {
                    $key = mb_strtolower(trim($s['client_name'])) . '|' . mb_strtolower(trim($s['project_name']));
                    $saleMap[$key] = (int) $s['id'];
                }
                foreach ($rows as $i => $row) {
                    $key = mb_strtolower(trim(csv_val($row, 'Cliente'))) . '|' . mb_strtolower(trim(csv_val($row, 'Projeto')));
                    $saleId = $saleMap[$key] ?? null;
                    if (!$saleId) { $errorCount++; continue; }
                    $stmt = db()->prepare('insert into financial_payments (company_id, financial_sale_id, payment_number, amount, payment_date) values (?,?,?,?,?)');
                    $stmt->execute([
                        $companyId, $saleId,
                        (int) (csv_num($row, 'Numero do pagamento') ?: ($i + 1)),
                        csv_num($row, 'Valor pago'),
                        csv_date($row, 'Data de pagamento'),
                    ]);
                    $successCount++;
                }
            } elseif ($importType === 'Metas' && $isAdmin) {
                foreach ($rows as $row) {
                    $designerName = mb_strtolower(trim(csv_val($row, 'Projetista')));
                    $designerId = $designerMap[$designerName] ?? null;
                    if (!$designerId) { $errorCount++; continue; }
                    $m = (int) csv_num($row, 'Mes');
                    $y = (int) csv_num($row, 'Ano');
                    $amount = csv_num($row, 'Valor da meta');
                    $existsStmt = db()->prepare('select id from designer_goals where company_id = ? and designer_id = ? and month = ? and year = ?');
                    $existsStmt->execute([$companyId, $designerId, $m, $y]);
                    $goalId = $existsStmt->fetchColumn();
                    if ($goalId) {
                        db()->prepare('update designer_goals set goal_amount = ? where id = ?')->execute([$amount, $goalId]);
                    } else {
                        db()->prepare('insert into designer_goals (company_id, designer_id, month, year, goal_amount) values (?,?,?,?,?)')->execute([$companyId, $designerId, $m, $y, $amount]);
                    }
                    $successCount++;
                }
            }

            db()->commit();
            $totalRows = $successCount + $errorCount;
            db()->prepare('insert into import_batches (company_id, type, file_name, status, total_rows, success_rows, error_rows, created_by_user_id) values (?,?,?,?,?,?,?,?)')->execute([
                $companyId, $importType, $_SESSION['import_file_name'] ?? 'upload.csv', 'COMPLETED', $totalRows, $successCount, $errorCount, (int) $user['id'],
            ]);
            $importSuccess = "Importacao concluida: {$successCount} registros inseridos" . ($errorCount ? ", {$errorCount} ignorados." : '.');
        } catch (Throwable $ex) {
            db()->rollBack();
            $errors[] = 'Erro ao importar: ' . $ex->getMessage();
        }

        @unlink($tempFile);
        unset($_SESSION['import_temp'], $_SESSION['import_type'], $_SESSION['import_count'], $_SESSION['import_file_name']);
    }
}

$imports = db()->prepare('select * from import_batches where company_id = ? order by created_at desc limit 10');
$imports->execute([$companyId]);
$importHistory = $imports->fetchAll();

$exports = db()->prepare('select * from export_logs where company_id = ? order by created_at desc limit 10');
$exports->execute([$companyId]);
$exportHistory = $exports->fetchAll();

$pageTitle = 'Importar / Exportar';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if ($importSuccess): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?= e($importSuccess) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <?php foreach (array_slice($errors, 0, 8) as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
            <?php if (count($errors) > 8): ?><p class="mt-1 font-semibold">...e mais <?= count($errors) - 8 ?> erro(s).</p><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="rounded-lg border border-line bg-white p-5">
        <h2 class="text-lg font-bold">Importação e exportação</h2>
        <p class="mt-1 text-sm text-slate-500">Importe dados por CSV com validação e prévia. Exporte projetos, vendas, pagamentos, metas e funcionários.</p>
        <p class="mt-2 text-sm text-slate-500">As permissões respeitam o perfil do usuário logado.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <?php if ($canImport): ?>
        <section class="rounded-lg border border-line bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">Importar</h3>
                    <p class="mt-1 text-sm text-slate-500">Envie um arquivo CSV com cabeçalhos.</p>
                </div>
                <a class="inline-flex min-h-10 items-center gap-2 rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog" href="/templates/modelo_importacao_projetos.csv" download>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Modelo CSV
                </a>
            </div>
            <form method="post" enctype="multipart/form-data" class="mt-5 grid gap-4">
                <label class="grid gap-1 text-sm font-semibold">Tipo de importação
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="import_type">
                        <option value="Projetos" <?= ($type ?? '') === 'Projetos' ? 'selected' : '' ?>>Projetos</option>
                        <option value="Vendas" <?= ($type ?? '') === 'Vendas' ? 'selected' : '' ?>>Vendas financeiras</option>
                        <option value="Pagamentos" <?= ($type ?? '') === 'Pagamentos' ? 'selected' : '' ?>>Pagamentos financeiros</option>
                        <?php if ($isAdmin): ?><option value="Metas" <?= ($type ?? '') === 'Metas' ? 'selected' : '' ?>>Metas dos projetistas</option><?php endif; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Arquivo CSV
                    <input class="min-h-10 rounded-md border border-line px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white" type="file" accept=".csv" name="import_file" required>
                </label>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" type="submit">
                    Enviar e validar
                </button>
            </form>

            <?php if ($summary): ?>
                <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <p class="font-semibold text-emerald-800">Arquivo validado</p>
                    <?php foreach ($summary as $item): ?><p class="mt-1 text-emerald-700"><?= e($item['label']) ?>: <strong><?= $item['count'] ?></strong> linha(s)</p><?php endforeach; ?>
                    <?php if ($isDesigner): ?><p class="mt-2 text-xs text-emerald-600">Como projetista, os registros importados entram vinculados ao seu usuário.</p><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($preview && !$errors): ?>
                <form method="post" class="mt-4">
                    <input type="hidden" name="confirm_import" value="1">
                    <button class="min-h-10 w-full rounded-md bg-emerald-700 px-4 text-sm font-bold text-white hover:bg-emerald-800" type="submit">
                        Confirmar importação (<?= count($preview) ?> linhas)
                    </button>
                </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="rounded-lg border border-line bg-white p-5">
            <h3 class="text-lg font-bold">Exportar</h3>
            <p class="mt-1 text-sm text-slate-500">Baixe seus dados em formato CSV.</p>
            <form method="get" action="/export.php" class="mt-5 grid gap-4">
                <label class="grid gap-1 text-sm font-semibold">Tipo de dados
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="type">
                        <option value="projects">Projetos</option>
                        <option value="finance">Financeiro (vendas)</option>
                        <option value="payments">Pagamentos</option>
                        <option value="goals">Metas</option>
                        <option value="employees">Funcionários</option>
                        <option value="history">Histórico de movimentações</option>
                    </select>
                </label>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" type="submit">
                    <span class="inline-flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Baixar CSV
                    </span>
                </button>
            </form>
        </section>
    </div>

    <?php if ($preview && !$errors): ?>
    <section class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="border-b border-line bg-fog/50 p-4">
            <h3 class="font-bold">Prévia da importação</h3>
            <p class="mt-1 text-xs text-slate-500">Mostrando as primeiras 10 linhas do arquivo.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3 text-center">#</th><?php foreach (array_keys($preview[0]) as $col): ?><th class="p-3"><?= e($col) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php foreach (array_slice($preview, 0, 10) as $i => $row): ?>
                        <tr class="border-t border-line hover:bg-fog/40"><td class="p-3 text-center text-xs text-slate-400"><?= $i + 1 ?></td><?php foreach ($row as $val): ?><td class="p-3"><?= e((string) $val) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <?php if (count($preview) > 10): ?>
                        <tr class="border-t border-line"><td colspan="<?= count(array_keys($preview[0])) + 1 ?>" class="p-3 text-center text-sm text-slate-500">...e mais <?= count($preview) - 10 ?> linha(s) não exibidas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Últimas importações</div>
            <table class="w-full text-left text-sm"><thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Tipo</th><th class="p-3">Status</th><th class="p-3">Linhas</th><th class="p-3">Data</th></tr></thead><tbody>
                <?php if (!$importHistory): ?><tr><td colspan="4" class="p-4 text-center text-slate-500">Nenhuma importação</td></tr><?php endif; ?>
                <?php foreach ($importHistory as $row): ?><tr class="border-t border-line"><td class="p-3"><?= e($row['type']) ?></td><td class="p-3"><?= e($row['status']) ?></td><td class="p-3"><?= (int) $row['success_rows'] ?>/<?= (int) $row['total_rows'] ?></td><td class="p-3"><?= e($row['created_at']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </section>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Últimas exportações</div>
            <table class="w-full text-left text-sm"><thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Tipo</th><th class="p-3">Formato</th><th class="p-3">Data</th></tr></thead><tbody>
                <?php if (!$exportHistory): ?><tr><td colspan="3" class="p-4 text-center text-slate-500">Nenhuma exportação</td></tr><?php endif; ?>
                <?php foreach ($exportHistory as $row): ?><tr class="border-t border-line"><td class="p-3"><?= e($row['type']) ?></td><td class="p-3"><?= e($row['format']) ?></td><td class="p-3"><?= e($row['created_at']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </section>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
