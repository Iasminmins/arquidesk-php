<?php
require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA', 'CONFERENTE'], true)) { redirect('/'); }
$canWriteFinance = in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true);

$companyId = (int) $user['company_id'];
$now = new DateTimeImmutable();
$month = max(1, min(12, (int) ($_GET['month'] ?? $now->format('n'))));
$year = (int) ($_GET['year'] ?? $now->format('Y'));
$designerFilter = $_GET['designer_id'] ?? '';
if ($user['role'] === 'PROJETISTA') { $designerFilter = (string) $user['id']; }
[$start, $end] = month_range($year, $month);

// POST: save sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWriteFinance && isset($_POST['save_sale'])) {
    $saleId = (int) ($_POST['id'] ?? 0);
    $designerId = (int) ($_POST['designer_id'] ?? 0);
    if ($user['role'] === 'PROJETISTA') { $designerId = (int) $user['id']; }
    $payload = [
        null_if_empty($_POST['client_project_id'] ?? ''), $designerId ?: null,
        trim($_POST['client_name'] ?? ''), trim($_POST['project_name'] ?? ''),
        (float) ($_POST['sold_value'] ?? 0), trim($_POST['payment_method'] ?? 'Pix'),
        $_POST['sale_date'] ?: date('Y-m-d'), null_if_empty($_POST['notes'] ?? ''),
    ];
    if ($saleId) {
        db()->prepare('update financial_sales set client_project_id=?, designer_id=?, client_name=?, project_name=?, sold_value=?, payment_method=?, sale_date=?, notes=? where id=? and company_id=?')->execute([...$payload, $saleId, $companyId]);
    } else {
        db()->prepare('insert into financial_sales (company_id, client_project_id, designer_id, client_name, project_name, sold_value, payment_method, sale_date, notes) values (?,?,?,?,?,?,?,?,?)')->execute([$companyId, ...$payload]);
        $saleId = (int) db()->lastInsertId();
    }
    db()->prepare('delete from financial_payments where financial_sale_id=? and company_id=?')->execute([$saleId, $companyId]);
    $amounts = $_POST['payment_amount'] ?? []; $dates = $_POST['payment_date'] ?? [];
    foreach ($amounts as $i => $amt) {
        if ($amt === '' || empty($dates[$i])) continue;
        db()->prepare('insert into financial_payments (company_id, financial_sale_id, payment_number, amount, payment_date) values (?,?,?,?,?)')->execute([$companyId, $saleId, $i + 1, (float) $amt, $dates[$i]]);
    }
    redirect('/finance.php?month=' . $month . '&year=' . $year . '&designer_id=' . urlencode($designerFilter) . '&ok=1');
}
if (!empty($_GET['delete']) && $canWriteFinance) {
    db()->prepare('delete from financial_sales where id=? and company_id=?')->execute([(int) $_GET['delete'], $companyId]);
    redirect('/finance.php?month=' . $month . '&year=' . $year . '&designer_id=' . urlencode($designerFilter) . '&ok=1');
}

// Load edit data
$edit = null; $editPayments = [];
if (!empty($_GET['edit']) && $canWriteFinance) {
    $s = db()->prepare('select * from financial_sales where id=? and company_id=?'); $s->execute([(int) $_GET['edit'], $companyId]); $edit = $s->fetch();
    if ($edit) { $p = db()->prepare('select * from financial_payments where financial_sale_id=? order by payment_number'); $p->execute([$edit['id']]); $editPayments = $p->fetchAll(); }
}
$showModal = $edit || isset($_GET['new']);

// Load data
$designersStmt = db()->prepare("select id, name from users where company_id = ? and role in ('ADMIN_EMPRESA','PROJETISTA') and active = 1 order by name");
$designersStmt->execute([$companyId]); $designers = $designersStmt->fetchAll();

$projectsStmt = db()->prepare('select id, client_name, project_name, designer_id, closed_value from client_projects where company_id = ? order by updated_at desc');
$projectsStmt->execute([$companyId]); $projects = $projectsStmt->fetchAll();

// Sales (all, not filtered by month - table shows all)
$sWhere = ['s.company_id = ?']; $sParams = [$companyId];
if ($designerFilter !== '') { $sWhere[] = 's.designer_id = ?'; $sParams[] = (int) $designerFilter; }
$salesStmt = db()->prepare('select s.*, u.name as designer_name, coalesce(sum(p.amount),0) as received from financial_sales s left join users u on u.id=s.designer_id left join financial_payments p on p.financial_sale_id=s.id where ' . implode(' and ', $sWhere) . ' group by s.id order by s.sale_date desc, s.id desc');
$salesStmt->execute($sParams); $sales = $salesStmt->fetchAll();

// Month sales/payments for cards
$monthSales = array_filter($sales, fn($s) => $s['sale_date'] >= $start && $s['sale_date'] <= $end);
$pWhere = ['p.company_id = ?', 'p.payment_date between ? and ?']; $pParams = [$companyId, $start, $end];
if ($designerFilter !== '') { $pWhere[] = 's.designer_id = ?'; $pParams[] = (int) $designerFilter; }
$paymentsStmt = db()->prepare('select p.*, s.client_name, s.project_name, s.payment_method, u.name as designer_name from financial_payments p join financial_sales s on s.id=p.financial_sale_id left join users u on u.id=s.designer_id where ' . implode(' and ', $pWhere) . ' order by p.payment_date desc');
$paymentsStmt->execute($pParams); $payments = $paymentsStmt->fetchAll();

$totalSold = array_sum(array_map(fn($s) => (float) $s['sold_value'], $monthSales));
$totalReceived = array_sum(array_map(fn($p) => (float) $p['amount'], $payments));
$commissionPercent = $totalReceived <= 100000 ? 5 : ($totalReceived <= 150000 ? 6 : 7);
$commStmt = db()->prepare('select * from financial_commission_settings where company_id=? and month=? and year=? and (designer_id=? or designer_id is null) order by designer_id desc limit 1');
$commStmt->execute([$companyId, $month, $year, $designerFilter ?: null]);
$comm = $commStmt->fetch();
if ($comm && (float) $comm['commission_percent'] > 0) $commissionPercent = (float) $comm['commission_percent'];

$pageTitle = 'Financeiro';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Financeiro atualizado.</div><?php endif; ?>

    <div class="grid gap-3 md:grid-cols-4">
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Total de venda mês</span><strong class="mt-2 block text-2xl"><?= money_br($totalSold) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Total que entrou mês</span><strong class="mt-2 block text-2xl"><?= money_br($totalReceived) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Percentual de comissão do mês</span><strong class="mt-2 block text-2xl"><?= number_format($commissionPercent, 2, ',', '.') ?>%</strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Valor da comissão</span><strong class="mt-2 block text-2xl"><?= money_br($totalReceived * $commissionPercent / 100) ?></strong></article>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-line bg-white p-4 md:flex-row md:items-end">
        <form method="get" class="flex flex-col gap-3 md:flex-row md:items-end">
            <label class="grid gap-1 text-sm font-semibold">Mês
                <select class="min-h-10 rounded-md border border-line px-3" name="month">
                    <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>" <?= $month === $i ? 'selected' : '' ?>><?= $i ?> - <?= month_name_pt($i) ?></option><?php endfor; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Ano
                <input class="min-h-10 w-24 rounded-md border border-line px-3" type="number" name="year" value="<?= $year ?>">
            </label>
            <?php if ($user['role'] !== 'PROJETISTA'): ?>
            <label class="grid gap-1 text-sm font-semibold">Projetista
                <select class="min-h-10 rounded-md border border-line px-3" name="designer_id">
                    <option value="">Todos</option>
                    <?php foreach ($designers as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $designerFilter === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php else: ?>
            <label class="grid gap-1 text-sm font-semibold">Projetista
                <input class="min-h-10 rounded-md border border-line px-3 bg-fog" value="<?= e($user['name']) ?>" disabled>
            </label>
            <?php endif; ?>
            <button class="min-h-10 rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Filtrar</button>
        </form>
        <?php if ($canWriteFinance): ?>
        <form method="post" action="/commission-save.php" class="flex items-end gap-3">
            <input type="hidden" name="month" value="<?= $month ?>"><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="designer_id" value="<?= e($designerFilter) ?>">
            <label class="grid gap-1 text-sm font-semibold">Comissão do mês (%)
                <input class="min-h-10 w-24 rounded-md border border-line px-3" type="number" step="0.01" name="commission_percent" value="<?= e((string) $commissionPercent) ?>">
            </label>
            <button class="min-h-10 rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Salvar comissão</button>
        </form>
        <a class="min-h-10 inline-flex items-center gap-2 rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95 md:ml-auto" href="/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e($designerFilter) ?>&new=1">+ Cadastrar venda</a>
        <?php endif; ?>
    </div>

    <section class="overflow-hidden rounded-lg border border-line bg-white">
        <div class="border-b border-line p-4 font-bold">Tabela de vendas</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500">
                    <tr><th class="p-3">Cliente</th><th class="p-3">Projeto</th><th class="p-3">Projetista</th><th class="p-3">Valor vendido</th><th class="p-3">Forma</th><th class="p-3">Data</th><th class="p-3">Total recebido</th><th class="p-3">Em aberto</th><th class="p-3">Status</th><?php if ($canWriteFinance): ?><th class="p-3 text-right">Ações</th><?php endif; ?></tr>
                </thead>
                <tbody>
                    <?php if (!$sales): ?><tr><td colspan="10" class="p-8 text-center text-slate-500">Nenhuma venda</td></tr><?php endif; ?>
                    <?php foreach ($sales as $sale): ?>
                        <?php $status = payment_status((float) $sale['sold_value'], (float) $sale['received']); ?>
                        <tr class="border-t border-line">
                            <td class="p-3"><?= e($sale['client_name']) ?></td>
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
                                <div class="flex justify-end gap-2">
                                    <a class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" href="/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e($designerFilter) ?>&edit=<?= (int) $sale['id'] ?>">Editar</a>
                                    <a class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-600" href="/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e($designerFilter) ?>&delete=<?= (int) $sale['id'] ?>" onclick="return confirm('Excluir?')">Excluir</a>
                                </div>
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
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Cliente</th><th class="p-3">Projeto</th><th class="p-3">Projetista</th><th class="p-3">Nº</th><th class="p-3">Valor pago</th><th class="p-3">Data</th><th class="p-3">Forma</th></tr></thead>
                <tbody>
                    <?php if (!$payments): ?><tr><td colspan="7" class="p-8 text-center text-slate-500">Nenhum pagamento no mês</td></tr><?php endif; ?>
                    <?php foreach ($payments as $pay): ?>
                        <tr class="border-t border-line"><td class="p-3"><?= e($pay['client_name']) ?></td><td class="p-3"><?= e($pay['project_name']) ?></td><td class="p-3"><?= e($pay['designer_name'] ?: '-') ?></td><td class="p-3"><?= (int) $pay['payment_number'] ?></td><td class="p-3"><?= money_br($pay['amount']) ?></td><td class="p-3"><?= date_br($pay['payment_date']) ?></td><td class="p-3"><?= e($pay['payment_method']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($showModal && $canWriteFinance): ?>
    <div class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" onclick="if(event.target===this)location.href='/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e($designerFilter) ?>'">
        <section class="w-full max-w-3xl rounded-lg border border-line bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-line p-4">
                <h3 class="font-bold"><?= $edit ? 'Editar venda' : 'Cadastrar venda' ?></h3>
                <a href="/finance.php?month=<?= $month ?>&year=<?= $year ?>&designer_id=<?= e($designerFilter) ?>" class="grid h-9 w-9 place-items-center rounded-md hover:bg-fog text-lg">✕</a>
            </div>
            <form method="post" class="max-h-[75vh] overflow-y-auto p-5">
                <input type="hidden" name="save_sale" value="1">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1 text-sm font-semibold">Vincular a projeto existente
                        <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="client_project_id">
                            <option value="">Venda avulsa</option>
                            <?php foreach ($projects as $proj): ?><option value="<?= (int) $proj['id'] ?>" <?= (int) ($edit['client_project_id'] ?? 0) === (int) $proj['id'] ? 'selected' : '' ?>><?= e($proj['client_name'] . ' - ' . $proj['project_name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Projetista responsável
                        <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="designer_id" required <?= $user['role'] === 'PROJETISTA' ? 'disabled' : '' ?>>
                            <option value="">Selecione</option>
                            <?php foreach ($designers as $d): ?>
                                <?php $selD = (int) ($edit['designer_id'] ?? ($user['role'] === 'PROJETISTA' ? $user['id'] : 0)); ?>
                                <option value="<?= (int) $d['id'] ?>" <?= $selD === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user['role'] === 'PROJETISTA'): ?><input type="hidden" name="designer_id" value="<?= (int) $user['id'] ?>"><?php endif; ?>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Cliente
                        <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="client_name" required value="<?= e($edit['client_name'] ?? '') ?>">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Projeto
                        <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="project_name" required value="<?= e($edit['project_name'] ?? '') ?>">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Valor vendido
                        <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="sold_value" required value="<?= e((string) ($edit['sold_value'] ?? '')) ?>">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Forma de pagamento
                        <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="payment_method" required value="<?= e($edit['payment_method'] ?? 'Pix') ?>">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Data da venda
                        <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="sale_date" required value="<?= e($edit['sale_date'] ?? date('Y-m-d')) ?>">
                    </label>
                </div>
                <label class="mt-4 grid gap-1 text-sm font-semibold">Observações
                    <textarea class="min-h-20 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="notes"><?= e($edit['notes'] ?? '') ?></textarea>
                </label>
                <section class="mt-5 grid gap-3 rounded-lg border border-line p-4">
                    <h3 class="font-bold">Pagamentos recebidos</h3>
                    <div id="payments-container">
                        <?php $payCount = max(1, count($editPayments)); ?>
                        <?php for ($i = 0; $i < $payCount; $i++): $pay = $editPayments[$i] ?? null; ?>
                            <div class="grid gap-3 md:grid-cols-[100px_1fr_1fr_auto] md:items-end mt-2">
                                <strong class="text-sm"><?= $i + 1 ?>º pagamento</strong>
                                <label class="grid gap-1 text-sm font-semibold">Valor
                                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="payment_amount[]" placeholder="Valor" value="<?= e((string) ($pay['amount'] ?? '')) ?>">
                                </label>
                                <label class="grid gap-1 text-sm font-semibold">Data
                                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="payment_date[]" value="<?= e($pay['payment_date'] ?? '') ?>">
                                </label>
                                <?php if ($i > 0): ?><button type="button" onclick="this.parentElement.remove()" class="min-h-10 rounded-md border border-red-200 px-3 text-xs font-semibold text-red-600 hover:bg-red-50">Remover</button><?php else: ?><div></div><?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" onclick="addPayment()" class="mt-2 min-h-10 rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog">+ Adicionar pagamento</button>
                </section>
                <script>
                var payIndex = <?= $payCount ?>;
                function addPayment() {
                    payIndex++;
                    var html = '<div class="grid gap-3 md:grid-cols-[100px_1fr_1fr_auto] md:items-end mt-2">'
                        + '<strong class="text-sm">' + payIndex + 'º pagamento</strong>'
                        + '<label class="grid gap-1 text-sm font-semibold">Valor<input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="payment_amount[]" placeholder="Valor"></label>'
                        + '<label class="grid gap-1 text-sm font-semibold">Data<input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="payment_date[]"></label>'
                        + '<button type="button" onclick="this.parentElement.remove()" class="min-h-10 rounded-md border border-red-200 px-3 text-xs font-semibold text-red-600 hover:bg-red-50">Remover</button>'
                        + '</div>';
                    document.getElementById('payments-container').insertAdjacentHTML('beforeend', html);
                }
                </script>
                <div class="mt-5 flex justify-end">
                    <button class="min-h-10 rounded-md bg-ink px-5 text-sm font-bold text-white" type="submit"><?= $edit ? 'Salvar alterações' : 'Salvar venda' ?></button>
                </div>
            </form>
        </section>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
