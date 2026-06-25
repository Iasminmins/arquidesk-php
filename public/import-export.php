<?php

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/xlsx-reader.php';

$user = require_auth();
require_active_subscription($user);
$companyId = (int) $user['company_id'];
$isAdmin = $user['role'] === 'ADMIN_EMPRESA';
$isDesigner = $user['role'] === 'PROJETISTA';
$canImport = $isAdmin || $isDesigner;

$stageMap = [
    'PROJETO' => 'PROJETO', 'PROJETOS' => 'PROJETO',
    'NEGOCIACAO' => 'NEGOCIACAO', 'NEGOCIAÇÃO' => 'NEGOCIACAO',
    'CONFERENCIA' => 'CONFERENCIA', 'CONFERÊNCIA' => 'CONFERENCIA',
    'MONTAGEM' => 'MONTAGEM',
    'ASSISTENCIA' => 'ASSISTENCIA', 'ASSISTÊNCIA' => 'ASSISTENCIA',
    'FINALIZADO' => 'FINALIZADO', 'FINALIZADOS' => 'FINALIZADO',
];

$preview = [];
$allPreviews = [];
$summary = [];
$errors = [];
$importSuccess = '';
$importType = $_POST['import_type'] ?? 'Completo';

function val(array $row, string $key): string {
    // Try exact match first, then alternative names
    $alts = [
        'Cliente' => ['cliente', 'client_name', 'Nome do cliente'],
        'Projeto' => ['projeto', 'project_name', 'Nome do projeto'],
        'Projetista' => ['projetista', 'designer_name', 'Projetista responsavel'],
        'Valor vendido' => ['valor', 'sold_value', 'valor_vendido'],
        'Forma de pagamento' => ['forma', 'payment_method', 'forma_pagamento'],
        'Data da venda' => ['data', 'sale_date', 'data_venda'],
        'Observacoes' => ['observacoes', 'notes', 'obs'],
        'Valor pago' => ['valor', 'amount', 'valor_pago'],
        'Data de pagamento' => ['data', 'payment_date', 'data_pagamento'],
        'Numero do pagamento' => ['numero', 'payment_number'],
        'Nome do cliente' => ['cliente', 'client_name'],
        'Telefone' => ['telefone', 'phone', 'client_phone'],
        'Nome do projeto' => ['projeto', 'project_name'],
        'Status' => ['status', 'project_status'],
        'Data de entrada' => ['data', 'entry_date'],
        'Projetista responsavel' => ['projetista', 'designer_name'],
        'Etapa desejada' => ['etapa', 'current_stage'],
    ];
    if (isset($row[$key]) && trim((string) $row[$key]) !== '') return trim((string) $row[$key]);
    foreach ($alts[$key] ?? [] as $alt) {
        if (isset($row[$alt]) && trim((string) $row[$alt]) !== '') return trim((string) $row[$alt]);
    }
    return '';
}
function num(array $row, string $key): float {
    $v = val($row, $key);
    if ($v === '') $v = '0';
    if (is_numeric($v)) return (float) $v;
    return xlsx_number_value($v);
}
function dt(array $row, string $key): ?string {
    $v = val($row, $key);
    if ($v === '') return null;
    return xlsx_date_value($v);
}

function normalize_name(string $name): string {
    $name = mb_strtolower(trim($name));
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
    return strtr($name, $map);
}

function build_designer_map(int $companyId): array {
    $stmt = db()->prepare("select id, name from users where company_id = ? and active = 1");
    $stmt->execute([$companyId]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) { $map[normalize_name($r['name'])] = (int) $r['id']; }
    return $map;
}

// ============ UPLOAD & PREVIEW ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK && $canImport) {
    $tmpPath = $_FILES['import_file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

    if ($ext === 'xlsx' || $ext === 'xls') {
        $rawSheets = read_xlsx($tmpPath);
        if ($importType === 'Completo') {
            foreach (['Projetos', 'Vendas financeiras', 'Pagamentos financeiros', 'Metas dos projetistas', 'Funcionarios'] as $tab) {
                if (isset($rawSheets[$tab])) {
                    $data = xlsx_to_assoc($rawSheets[$tab]);
                    if ($data) { $allPreviews[$tab] = $data; $summary[] = ['label' => $tab, 'count' => count($data)]; }
                }
            }
            $preview = $allPreviews['Projetos'] ?? [];
        } else {
            $firstSheet = reset($rawSheets);
            if ($firstSheet) { $preview = xlsx_to_assoc($firstSheet); $summary[] = ['label' => $importType, 'count' => count($preview)]; }
        }
    } elseif ($ext === 'csv') {
        $preview = parse_csv_file($tmpPath);
        if ($preview) { $summary[] = ['label' => $importType, 'count' => count($preview)]; }
    }

    if (!$preview && !$allPreviews) {
        $errors[] = 'Arquivo vazio ou formato inválido. Use XLSX ou CSV.';
    }

    // Save temp
    $tempFile = sys_get_temp_dir() . '/arquidesk-import-' . session_id() . '.' . $ext;
    copy($tmpPath, $tempFile);
    $_SESSION['import_temp'] = $tempFile;
    $_SESSION['import_type'] = $importType;
    $_SESSION['import_ext'] = $ext;
}

function parse_csv_file(string $path): array {
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) return [];
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle); rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    $delimiter = ($firstLine && substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
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

// ============ CONFIRM IMPORT ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import']) && $canImport) {
    $tempFile = $_SESSION['import_temp'] ?? '';
    $importType = $_SESSION['import_type'] ?? 'Projetos';
    $ext = $_SESSION['import_ext'] ?? 'csv';

    if (!$tempFile || !is_readable($tempFile)) {
        $errors[] = 'Sessão expirada. Envie o arquivo novamente.';
    } else {
        $designerMap = build_designer_map($companyId);
        $successCount = 0;
        $errorCount = 0;

        // Parse file
        if ($ext === 'xlsx' || $ext === 'xls') {
            $rawSheets = read_xlsx($tempFile);
        }

        try {
            db()->beginTransaction();

            // ---- FUNCIONARIOS (first, so designer map is updated) ----
            if ($importType === 'Completo' && $isAdmin && isset($rawSheets['Funcionarios'])) {
                $funcRows = xlsx_to_assoc($rawSheets['Funcionarios']);
                foreach ($funcRows as $row) {
                    $fname = val($row, 'Nome'); $femail = val($row, 'E-mail');
                    if (!$fname || !$femail) { $errorCount++; continue; }
                    if (isset($designerMap[mb_strtolower($fname)])) continue; // already exists
                    $frole = strtoupper(val($row, 'Permissao'));
                    if (!in_array($frole, ['PROJETISTA', 'CONFERENTE', 'ADMIN_EMPRESA'], true)) $frole = 'PROJETISTA';
                    $factive = !in_array(strtoupper(val($row, 'Ativo')), ['NAO', 'NÃO', 'FALSE', '0', 'Nao'], true);
                    $tempPass = password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
                    db()->prepare('insert into users (company_id, name, email, password_hash, role, active) values (?,?,?,?,?,?)')->execute([$companyId, $fname, $femail, $tempPass, $frole, $factive ? 1 : 0]);
                    $designerMap[mb_strtolower($fname)] = (int) db()->lastInsertId();
                    $successCount++;
                }
            }

            // ---- PROJETOS ----
            $projectRows = [];
            if ($importType === 'Completo' && isset($rawSheets['Projetos'])) {
                $projectRows = xlsx_to_assoc($rawSheets['Projetos']);
            } elseif ($importType === 'Projetos') {
                $projectRows = ($ext === 'csv') ? parse_csv_file($tempFile) : (isset($rawSheets) ? xlsx_to_assoc(reset($rawSheets)) : []);
            }
            $projectMap = [];
            foreach ($projectRows as $row) {
                $clientName = val($row, 'Nome do cliente');
                if (!$clientName) { $errorCount++; continue; }
                $dName = mb_strtolower(trim(val($row, 'Projetista responsavel')));
                $dId = $isDesigner ? (int) $user['id'] : ($designerMap[$dName] ?? null);
                $wStage = strtoupper(val($row, 'Etapa desejada'));
                $stage = $stageMap[$wStage] ?? 'PROJETO';
                $ins = db()->prepare('insert into client_projects (company_id, designer_id, client_name, client_address, client_phone, project_name, current_stage, project_status, entry_date, presentation_date, negotiation_status, new_proposal_value, closed_value, closing_date, conference_status, sent_to_factory_date, billing_date, assembly_status, assembly_started_date, assembly_finished_date, assistance_status, assistance_date, order_date, notes) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $ins->execute([
                    $companyId, $dId, $clientName,
                    val($row, 'Endereco do cliente') ?: null,
                    val($row, 'Telefone'),
                    val($row, 'Nome do projeto'),
                    $stage,
                    val($row, 'Status') ?: null,
                    dt($row, 'Data de entrada'),
                    dt($row, 'Data de apresentacao'),
                    val($row, 'Status da negociacao') ?: null,
                    num($row, 'Valor nova proposta') ?: null,
                    num($row, 'Valor fechado') ?: null,
                    dt($row, 'Data de fechamento'),
                    val($row, 'Status conferencia') ?: null,
                    dt($row, 'Data envio fabrica'),
                    dt($row, 'Data faturamento'),
                    val($row, 'Status montagem') ?: null,
                    dt($row, 'Data inicio montagem'),
                    dt($row, 'Data fim montagem'),
                    val($row, 'Status assistencia') ?: null,
                    dt($row, 'Data assistencia'),
                    dt($row, 'Data pedido'),
                    val($row, 'Observacoes') ?: null,
                ]);
                $pKey = mb_strtolower($clientName) . '|' . mb_strtolower(val($row, 'Nome do projeto'));
                $projectMap[$pKey] = (int) db()->lastInsertId();
                $successCount++;
            }

            // ---- VENDAS ----
            $saleRows = [];
            if ($importType === 'Completo' && isset($rawSheets['Vendas financeiras'])) {
                $saleRows = xlsx_to_assoc($rawSheets['Vendas financeiras']);
            } elseif ($importType === 'Vendas') {
                $saleRows = ($ext === 'csv') ? parse_csv_file($tempFile) : (isset($rawSheets) ? xlsx_to_assoc(reset($rawSheets)) : []);
            }
            $saleMap = [];
            foreach ($saleRows as $row) {
                $cName = val($row, 'Cliente'); $pName = val($row, 'Projeto');
                if (!$cName) { $errorCount++; continue; }
                $dName = mb_strtolower(trim(val($row, 'Projetista')));
                $dId = $isDesigner ? (int) $user['id'] : ($designerMap[$dName] ?? null);
                $pKey = mb_strtolower($cName) . '|' . mb_strtolower($pName);
                $linkedProjectId = $projectMap[$pKey] ?? null;
                $ins = db()->prepare('insert into financial_sales (company_id, client_project_id, designer_id, client_name, project_name, sold_value, payment_method, sale_date, notes) values (?,?,?,?,?,?,?,?,?)');
                $ins->execute([$companyId, $linkedProjectId, $dId, $cName, $pName, num($row, 'Valor vendido'), val($row, 'Forma de pagamento') ?: 'Pix', dt($row, 'Data da venda') ?? date('Y-m-d'), val($row, 'Observacoes') ?: null]);
                $saleMap[$pKey] = (int) db()->lastInsertId();
                $successCount++;
            }

            // ---- PAGAMENTOS ----
            $payRows = [];
            if ($importType === 'Completo' && isset($rawSheets['Pagamentos financeiros'])) {
                $payRows = xlsx_to_assoc($rawSheets['Pagamentos financeiros']);
            } elseif ($importType === 'Pagamentos') {
                $payRows = ($ext === 'csv') ? parse_csv_file($tempFile) : (isset($rawSheets) ? xlsx_to_assoc(reset($rawSheets)) : []);
            }
            // Also load existing sales for payment linking
            $existSales = db()->prepare('select id, client_name, project_name from financial_sales where company_id = ?');
            $existSales->execute([$companyId]);
            foreach ($existSales->fetchAll() as $s) {
                $k = mb_strtolower(trim($s['client_name'])) . '|' . mb_strtolower(trim($s['project_name']));
                if (!isset($saleMap[$k])) $saleMap[$k] = (int) $s['id'];
            }
            foreach ($payRows as $i => $row) {
                $k = mb_strtolower(trim(val($row, 'Cliente'))) . '|' . mb_strtolower(trim(val($row, 'Projeto')));
                $saleId = $saleMap[$k] ?? null;
                if (!$saleId) { $errorCount++; continue; }
                $ins = db()->prepare('insert into financial_payments (company_id, financial_sale_id, payment_number, amount, payment_date) values (?,?,?,?,?)');
                $ins->execute([$companyId, $saleId, (int) (num($row, 'Numero do pagamento') ?: ($i + 1)), num($row, 'Valor pago'), dt($row, 'Data de pagamento') ?? date('Y-m-d')]);
                $successCount++;
            }

            // ---- METAS ----
            $goalRows = [];
            if ($importType === 'Completo' && $isAdmin && isset($rawSheets['Metas dos projetistas'])) {
                $goalRows = xlsx_to_assoc($rawSheets['Metas dos projetistas']);
            } elseif ($importType === 'Metas' && $isAdmin) {
                $goalRows = ($ext === 'csv') ? parse_csv_file($tempFile) : (isset($rawSheets) ? xlsx_to_assoc(reset($rawSheets)) : []);
            }
            foreach ($goalRows as $row) {
                $dName = mb_strtolower(trim(val($row, 'Projetista')));
                $dId = $designerMap[$dName] ?? null;
                if (!$dId) { $errorCount++; continue; }
                $m = (int) num($row, 'Mes'); $y = (int) num($row, 'Ano'); $amt = num($row, 'Valor da meta');
                $ex = db()->prepare('select id from designer_goals where company_id=? and designer_id=? and month=? and year=?');
                $ex->execute([$companyId, $dId, $m, $y]);
                if ($gid = $ex->fetchColumn()) {
                    db()->prepare('update designer_goals set goal_amount=? where id=?')->execute([$amt, $gid]);
                } else {
                    db()->prepare('insert into designer_goals (company_id,designer_id,month,year,goal_amount) values(?,?,?,?,?)')->execute([$companyId,$dId,$m,$y,$amt]);
                }
                $successCount++;
            }

            db()->commit();
            $totalRows = $successCount + $errorCount;
            db()->prepare('insert into import_batches (company_id,type,file_name,status,total_rows,success_rows,error_rows,created_by_user_id) values(?,?,?,?,?,?,?,?)')->execute([
                $companyId, $importType, $_FILES['import_file']['name'] ?? 'upload', 'COMPLETED', $totalRows, $successCount, $errorCount, (int) $user['id'],
            ]);
            $importSuccess = "Importação concluída: {$successCount} registros inseridos" . ($errorCount ? ", {$errorCount} ignorados." : '.');
            if ($importType === 'Completo' && $isAdmin) {
                $importSuccess .= ' Funcionários importados precisam ter suas senhas definidas na tela Funcionários.';
            }
        } catch (Throwable $ex) {
            db()->rollBack();
            $errors[] = 'Erro ao importar: ' . $ex->getMessage();
        }

        @unlink($tempFile);
        unset($_SESSION['import_temp'], $_SESSION['import_type'], $_SESSION['import_ext']);
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
        </div>
    <?php endif; ?>

    <div class="rounded-lg border border-line bg-white p-5">
        <h2 class="text-lg font-bold">Importação e exportação</h2>
        <p class="mt-1 text-sm text-slate-500">Importe dados por XLSX ou CSV. Use a opção "Completo" para importar tudo de uma vez (Projetos, Vendas, Pagamentos, Metas e Funcionários).</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <?php if ($canImport): ?>
        <section class="rounded-lg border border-line bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">Importar</h3>
                    <p class="mt-1 text-sm text-slate-500">Envie um arquivo XLSX ou CSV.</p>
                </div>
                <a class="inline-flex min-h-10 items-center gap-2 rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog" href="/templates/modelo_importacao_projetos.csv" download>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Modelo CSV
                </a>
            </div>
            <form method="post" enctype="multipart/form-data" class="mt-5 grid gap-4">
                <label class="grid gap-1 text-sm font-semibold">Tipo de importação
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="import_type">
                        <option value="Completo" <?= $importType === 'Completo' ? 'selected' : '' ?>>Completo (todas as abas)</option>
                        <option value="Projetos" <?= $importType === 'Projetos' ? 'selected' : '' ?>>Apenas Projetos</option>
                        <option value="Vendas" <?= $importType === 'Vendas' ? 'selected' : '' ?>>Apenas Vendas financeiras</option>
                        <option value="Pagamentos" <?= $importType === 'Pagamentos' ? 'selected' : '' ?>>Apenas Pagamentos</option>
                        <?php if ($isAdmin): ?><option value="Metas" <?= $importType === 'Metas' ? 'selected' : '' ?>>Apenas Metas</option><?php endif; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Arquivo XLSX ou CSV
                    <input class="min-h-10 rounded-md border border-line px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white" type="file" accept=".xlsx,.xls,.csv" name="import_file" required>
                </label>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" type="submit">Enviar e validar</button>
            </form>

            <?php if ($summary): ?>
                <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <p class="font-semibold text-emerald-800">Arquivo validado</p>
                    <?php foreach ($summary as $item): ?>
                        <p class="mt-1 text-emerald-700"><?= e($item['label']) ?>: <strong><?= $item['count'] ?></strong> linha(s)</p>
                    <?php endforeach; ?>
                    <?php if ($importType === 'Completo' && $isAdmin): ?>
                        <p class="mt-2 text-xs text-emerald-600">Funcionários importados terão senhas temporárias. Defina as senhas na tela Funcionários.</p>
                    <?php endif; ?>
                    <?php if ($isDesigner): ?>
                        <p class="mt-2 text-xs text-emerald-600">Como projetista, os registros importados entram vinculados ao seu usuário.</p>
                    <?php endif; ?>
                </div>
                <form method="post" class="mt-4">
                    <input type="hidden" name="confirm_import" value="1">
                    <button class="min-h-10 w-full rounded-md bg-emerald-700 px-4 text-sm font-bold text-white hover:bg-emerald-800" type="submit">
                        Confirmar importação (<?= array_sum(array_column($summary, 'count')) ?> registros)
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
                        <option value="all">Exportar tudo (completo)</option>
                        <option value="projects">Projetos</option>
                        <option value="future_clients">Clientes Futuros</option>
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

    <?php if ($preview): ?>
    <section class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="border-b border-line bg-fog/50 p-4">
            <h3 class="font-bold">Prévia da importação</h3>
            <p class="mt-1 text-xs text-slate-500">Mostrando as primeiras 10 linhas<?= $importType === 'Completo' ? ' (aba Projetos)' : '' ?>.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">#</th><?php foreach (array_keys($preview[0]) as $col): ?><th class="p-3"><?= e($col) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php foreach (array_slice($preview, 0, 10) as $i => $row): ?>
                        <tr class="border-t border-line hover:bg-fog/40"><td class="p-3 text-center text-xs text-slate-400"><?= $i + 1 ?></td><?php foreach ($row as $val): ?><td class="p-3"><?= e((string) $val) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <?php if (count($preview) > 10): ?>
                        <tr class="border-t border-line"><td colspan="<?= count(array_keys($preview[0])) + 1 ?>" class="p-3 text-center text-sm text-slate-500">...e mais <?= count($preview) - 10 ?> linha(s).</td></tr>
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
                <?php foreach ($importHistory as $row): ?><tr class="border-t border-line"><td class="p-3"><?= e($row['type']) ?></td><td class="p-3"><?= e($row['status']) ?></td><td class="p-3"><?= (int) $row['success_rows'] ?>/<?= (int) $row['total_rows'] ?></td><td class="p-3"><?= e(date_br($row['created_at'])) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </section>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Últimas exportações</div>
            <table class="w-full text-left text-sm"><thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Tipo</th><th class="p-3">Formato</th><th class="p-3">Data</th></tr></thead><tbody>
                <?php if (!$exportHistory): ?><tr><td colspan="3" class="p-4 text-center text-slate-500">Nenhuma exportação</td></tr><?php endif; ?>
                <?php foreach ($exportHistory as $row): ?><tr class="border-t border-line"><td class="p-3"><?= e($row['type']) ?></td><td class="p-3"><?= e($row['format']) ?></td><td class="p-3"><?= e(date_br($row['created_at'])) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </section>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
