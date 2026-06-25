<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA', 'CONFERENTE'], true)) {
    redirect('/');
}
$canWriteFinance = in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true);

$companyId = (int) $user['company_id'];
$now = new DateTimeImmutable();
$month = max(1, min(12, (int) ($_GET['month'] ?? $now->format('n'))));
$year = (int) ($_GET['year'] ?? $now->format('Y'));
$designerFilter = $_GET['designer_id'] ?? '';
if ($user['role'] === 'PROJETISTA') {
    $designerFilter = (string) $user['id'];
}
[$start, $end] = month_range($year, $month);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWriteFinance) {
    $saleId = (int) ($_POST['id'] ?? 0);
    $designerId = (int) ($_POST['designer_id'] ?? 0);
    if ($user['role'] === 'PROJETISTA') {
        $designerId = (int) $user['id'];
    }

    $payload = [
        'client_project_id' => null_if_empty($_POST['client_project_id'] ?? null),
        'designer_id' => $designerId ?: null,
        'client_name' => trim($_POST['client_name'] ?? ''),
        'project_name' => trim($_POST['project_name'] ?? ''),
        'sold_value' => (float) ($_POST['sold_value'] ?? 0),
        'payment_method' => trim($_POST['payment_method'] ?? 'Parcelado'),
        'sale_date' => $_POST['sale_date'] ?: date('Y-m-d'),
        'notes' => null_if_empty($_POST['notes'] ?? ''),
    ];

    if ($saleId) {
        $stmt = db()->prepare('update financial_sales set client_project_id = ?, designer_id = ?, client_name = ?, project_name = ?, sold_value = ?, payment_method = ?, sale_date = ?, notes = ? where id = ? and company_id = ?');
        $stmt->execute([$payload['client_project_id'], $payload['designer_id'], $payload['client_name'], $payload['project_name'], $payload['sold_value'], $payload['payment_method'], $payload['sale_date'], $payload['notes'], $saleId, $companyId]);
    } else {
        $stmt = db()->prepare('insert into financial_sales (company_id, client_project_id, designer_id, client_name, project_name, sold_value, payment_method, sale_date, notes) values (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$companyId, $payload['client_project_id'], $payload['designer_id'], $payload['client_name'], $payload['project_name'], $payload['sold_value'], $payload['payment_method'], $payload['sale_date'], $payload['notes']]);
        $saleId = (int) db()->lastInsertId();
    }

    db()->prepare('delete from financial_payments where financial_sale_id = ? and company_id = ?')->execute([$saleId, $companyId]);
    $amounts = $_POST['payment_amount'] ?? [];
    $dates = $_POST['payment_date'] ?? [];
    foreach ($amounts as $index => $amount) {
        if ($amount === '' || empty($dates[$index])) {
            continue;
        }
        $stmt = db()->prepare('insert into financial_payments (company_id, financial_sale_id, payment_number, amount, payment_date) values (?, ?, ?, ?, ?)');
        $stmt->execute([$companyId, $saleId, $index + 1, (float) $amount, $dates[$index]]);
    }

    redirect('/finance.php?month=' . $month . '&year=' . $year . '&designer_id=' . urlencode((string) $designerFilter) . '&ok=1');
}

if (!empty($_GET['delete'])) {
    db()->prepare('delete from financial_sales where id = ? and company_id = ?')->execute([(int) $_GET['delete'], $companyId]);
    redirect('/finance.php?month=' . $month . '&year=' . $year . '&designer_id=' . urlencode((string) $designerFilter) . '&ok=1');
}

$edit = null;
$editPayments = [];
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('select * from financial_sales where id = ? and company_id = ?');
    $stmt->execute([(int) $_GET['edit'], $companyId]);
    $edit = $stmt->fetch();
    if ($edit) {
        $paymentsEditStmt = db()->prepare('select * from financial_payments where financial_sale_id = ? and company_id = ? order by payment_number asc');
        $paymentsEditStmt->execute([(int) $edit['id'], $companyId]);
        $editPayments = $paymentsEditStmt->fetchAll();
    }
}

$designersStmt = db()->prepare("select id, name from users where company_id = ? and role in ('ADMIN_EMPRESA','PROJETISTA') and active = 1 order by name");
$designersStmt->execute([$companyId]);
$designers = $designersStmt->fetchAll();

$projectsStmt = db()->prepare('select id, client_name, project_name, designer_id, closed_value from client_projects where company_id = ? order by updated_at desc, created_at desc');
$projectsStmt->execute([$companyId]);
$projects = $projectsStmt->fetchAll();

$where = ['s.company_id = ?', 's.sale_date between ? and ?'];
$params = [$companyId, $start, $end];
if ($designerFilter !== '') {
    $where[] = 's.designer_id = ?';
    $params[] = (int) $designerFilter;
}

$salesStmt = db()->prepare(
    'select s.*, u.name as designer_name, coalesce(sum(p.amount), 0) as received
     from financial_sales s
     left join users u on u.id = s.designer_id
     left join financial_payments p on p.financial_sale_id = s.id
     where ' . implode(' and ', $where) . '
     group by s.id
     order by s.sale_date desc, s.id desc'
);
$salesStmt->execute($params);
$sales = $salesStmt->fetchAll();

$paymentsWhere = ['p.company_id = ?', 'p.payment_date between ? and ?'];
$paymentsParams = [$companyId, $start, $end];
if ($designerFilter !== '') {
    $paymentsWhere[] = 's.designer_id = ?';
    $paymentsParams[] = (int) $designerFilter;
}

$paymentsStmt = db()->prepare(
    'select p.*, s.client_name, s.project_name, s.payment_method, u.name as designer_name
     from financial_payments p
     join financial_sales s on s.id = p.financial_sale_id
     left join users u on u.id = s.designer_id
     where ' . implode(' and ', $paymentsWhere) . '
     order by p.payment_date desc, p.id desc'
);
$paymentsStmt->execute($paymentsParams);
$payments = $paymentsStmt->fetchAll();

$totalSold = array_sum(array_map(fn($sale) => (float) $sale['sold_value'], $sales));
$totalReceived = array_sum(array_map(fn($payment) => (float) $payment['amount'], $payments));
$commissionPercent = $totalReceived <= 100000 ? 5 : ($totalReceived <= 150000 ? 6 : 7);

$commissionStmt = db()->prepare('select * from financial_commission_settings where company_id = ? and (designer_id = ? or (designer_id is null and ? = \'\')) and month = ? and year = ? limit 1');
$commissionStmt->execute([$companyId, $designerFilter ?: null, $designerFilter, $month, $year]);
$commission = $commissionStmt->fetch();
if ($commission && (float) $commission['commission_percent'] > 0) {
    $commissionPercent = (float) $commission['commission_percent'];
}

$pageTitle = 'Financeiro';
$subtitle = 'Vendas, pagamentos e comissão';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Financeiro atualizado.</div>
    <?php endif; ?>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form method="get" class="flex flex-col gap-3 rounded-lg border border-line bg-white p-4 md:flex-row md:items-end">
            <label class="grid gap-1 text-sm font-semibold">Mês
                <select class="min-h-10 rounded-md border border-line px-3" name="month">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $month === $i ? 'selected' : '' ?>><?= $i ?> - <?= month_name_pt($i) ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Ano
                <input class="min-h-10 rounded-md border border-line px-3" type="number" name="year" value="<?= $year ?>">
            </label>
            <?php if ($user['role'] === 'ADMIN_EMPRESA'): ?>
                <label class="grid gap-1 text-sm font-semibold">Projetista
                    <select class="min-h-10 rounded-md border border-line px-3" name="designer_id">
                        <option value="">Todos os projetistas</option>
                        <?php foreach ($designers as $designer): ?>
                            <option value="<?= (int) $designer['id'] ?>" <?= (string) $designerFilter === (string) $designer['id'] ? 'selected' : '' ?>><?= e($designer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button class="min-h-10 rounded-md border border-line bg-white px-4 text-sm font-semibold" type="submit">Filtrar</button>
        </form>
        <a class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-semibold" href="/export.php?type=finance&month=<?= $month ?>&year=<?= $year ?>">Exportar</a>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Total vendido mês</span><strong class="mt-2 block text-2xl"><?= money_br($totalSold) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Total recebido mês</span><strong class="mt-2 block text-2xl"><?= money_br($totalReceived) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Comissão aplicada</span><strong class="mt-2 block text-2xl"><?= number_format($commissionPercent, 2, ',', '.') ?>%</strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Valor comissão</span><strong class="mt-2 block text-2xl"><?= money_br($totalReceived * $commissionPercent / 100) ?></strong></article>
    </div>

    <?php if ($canWriteFinance): ?>
        <form method="post" action="/commission-save.php" class="flex flex-col gap-3 rounded-lg border border-line bg-white p-4 md:flex-row md:items-end">
            <input type="hidden" name="month" value="<?= $month ?>">
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="designer_id" value="<?= e((string) $designerFilter) ?>">
            <label class="grid gap-1 text-sm font-semibold">Comissão do mês (%) <?= $designerFilter ? '— projetista selecionado' : '— geral' ?>
                <input class="min-h-10 rounded-md border border-line px-3" type="number" step="0.01" name="commission_percent" value="<?= e((string) $commissionPercent) ?>">
            </label>
            <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar comissão</button>
        </form>
    <?php endif; ?>

    <?php if ($canWriteFinance): ?>
    <form method="post" class="grid gap-4 rounded-lg border border-line bg-white p-4">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <h2 class="font-bold"><?= $edit ? 'Editar venda' : 'Nova venda' ?></h2>
        <div class="grid gap-4 md:grid-cols-3">
            <label class="grid gap-1 text-sm font-semibold">Projeto vinculado
                <select class="min-h-10 rounded-md border border-line px-3" name="client_project_id">
                    <option value="">Venda avulsa</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project['id'] ?>" <?= (int) ($edit['client_project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['client_name'] . ' - ' . $project['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Projetista
                <select class="min-h-10 rounded-md border border-line px-3" name="designer_id" <?= $user['role'] === 'PROJETISTA' ? 'disabled' : '' ?> required>
                    <option value="">Selecionar</option>
                    <?php foreach ($designers as $designer): ?>
                        <?php $selectedDesigner = (int) ($edit['designer_id'] ?? ($user['role'] === 'PROJETISTA' ? $user['id'] : 0)); ?>
                        <option value="<?= (int) $designer['id'] ?>" <?= $selectedDesigner === (int) $designer['id'] ? 'selected' : '' ?>><?= e($designer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($user['role'] === 'PROJETISTA'): ?><input type="hidden" name="designer_id" value="<?= (int) $user['id'] ?>"><?php endif; ?>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Data da venda
                <input class="min-h-10 rounded-md border border-line px-3" type="date" name="sale_date" value="<?= e($edit['sale_date'] ?? date('Y-m-d')) ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Cliente
                <input class="min-h-10 rounded-md border border-line px-3" name="client_name" required value="<?= e($edit['client_name'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Projeto
                <input class="min-h-10 rounded-md border border-line px-3" name="project_name" value="<?= e($edit['project_name'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Valor vendido
                <input class="min-h-10 rounded-md border border-line px-3" type="number" step="0.01" name="sold_value" required value="<?= e((string) ($edit['sold_value'] ?? '')) ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Forma de pagamento
                <select class="min-h-10 rounded-md border border-line px-3" name="payment_method">
                    <?php foreach (['Parcelado', 'À vista', 'Financiamento', 'Pix', 'Cartão', 'Boleto'] as $method): ?>
                        <option value="<?= e($method) ?>" <?= ($edit['payment_method'] ?? 'Parcelado') === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid gap-3 rounded-md bg-fog p-3 md:grid-cols-3">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <?php $payment = $editPayments[$i] ?? null; ?>
                <div class="grid gap-2">
                    <strong class="text-sm"><?= $i + 1 ?>º pagamento</strong>
                    <input class="min-h-10 rounded-md border border-line px-3" type="number" step="0.01" name="payment_amount[]" placeholder="Valor" value="<?= e((string) ($payment['amount'] ?? '')) ?>">
                    <input class="min-h-10 rounded-md border border-line px-3" type="date" name="payment_date[]" value="<?= e($payment['payment_date'] ?? date('Y-m-d')) ?>">
                </div>
            <?php endfor; ?>
        </div>
        <textarea class="min-h-20 rounded-md border border-line px-3 py-2" name="notes" placeholder="Observações"><?= e($edit['notes'] ?? '') ?></textarea>
        <div class="flex justify-end"><button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar venda</button></div>
    </form>
    <?php endif; ?>

    <section class="overflow-hidden rounded-lg border border-line bg-white">
        <div class="border-b border-line p-4 font-bold">Tabela de vendas</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500">
                    <tr><th class="p-3">Cliente</th><th class="p-3">Projeto</th><th class="p-3">Projetista</th><th class="p-3">Valor vendido</th><th class="p-3">Forma</th><th class="p-3">Data</th><th class="p-3">Recebido</th><th class="p-3">Em aberto</th><th class="p-3">Status</th><?php if ($canWriteFinance): ?><th class="p-3 text-right">Ações</th><?php endif; ?></tr>
                </thead>
                <tbody>
                    <?php if (!$sales): ?><tr><td colspan="10" class="p-8 text-center text-slate-500">Nenhuma venda</td></tr><?php endif; ?>
                    <?php foreach ($sales as $sale): ?>
                        <?php $status = payment_status((float) $sale['sold_value'], (float) $sale['received']); ?>
                        <tr class="border-t border-line hover:bg-fog/60">
                            <td class="p-3 font-semibold"><?= e($sale['client_name']) ?></td>
                            <td class="p-3"><?= e($sale['project_name'] ?: '-') ?></td>
                            <td class="p-3"><?= e($sale['designer_name'] ?: '-') ?></td>
                            <td class="p-3"><?= money_br($sale['sold_value']) ?></td>
                            <td class="p-3"><?= e($sale['payment_method']) ?></td>
                            <td class="p-3"><?= date_br($sale['sale_date']) ?></td>
                            <td class="p-3"><?= money_br($sale['received']) ?></td>
                            <td class="p-3"><?= money_br(max(0, $sale['sold_value'] - $sale['received'])) ?></td>
                            <td class="p-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $status === 'Pago' ? 'bg-emerald-100 text-emerald-700' : ($status === 'Parcial' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>"><?= $status ?></span></td>
                            <?php if ($canWriteFinance): ?>
                            <td class="p-3 text-right">
                                <a class="rounded-md border border-line px-3 py-2 text-xs font-semibold" href="/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e((string) $designerFilter) ?>&edit=<?= (int) $sale['id'] ?>">Editar</a>
                                <?php if ($user['role'] === 'ADMIN_EMPRESA'): ?>
                                <a class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-600" href="/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e((string) $designerFilter) ?>&delete=<?= (int) $sale['id'] ?>" onclick="return confirm('Excluir venda de <?= e($sale['client_name']) ?>?')">Excluir</a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-line bg-white">
        <div class="border-b border-line p-4 font-bold">Pagamentos do mês</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Cliente</th><th class="p-3">Projeto</th><th class="p-3">Projetista</th><th class="p-3">Nº</th><th class="p-3">Valor</th><th class="p-3">Data</th><th class="p-3">Forma</th></tr></thead>
                <tbody>
                    <?php if (!$payments): ?><tr><td colspan="7" class="p-8 text-center text-slate-500">Nenhum pagamento</td></tr><?php endif; ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr class="border-t border-line hover:bg-fog/60"><td class="p-3 font-semibold"><?= e($payment['client_name']) ?></td><td class="p-3"><?= e($payment['project_name'] ?: '-') ?></td><td class="p-3"><?= e($payment['designer_name'] ?: '-') ?></td><td class="p-3"><?= (int) $payment['payment_number'] ?></td><td class="p-3"><?= money_br($payment['amount']) ?></td><td class="p-3"><?= date_br($payment['payment_date']) ?></td><td class="p-3"><?= e($payment['payment_method']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
