<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
$companyId = (int) $user['company_id'];
$mode = $_GET['mode'] ?? match ($user['role']) {
    'PROJETISTA' => 'my-goal',
    'CONFERENTE' => 'team-goals',
    default => 'goals',
};
$now = new DateTimeImmutable();
$month = max(1, min(12, (int) ($_GET['month'] ?? $now->format('n'))));
$year = (int) ($_GET['year'] ?? $now->format('Y'));
[$start, $end] = month_range($year, $month);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'ADMIN_EMPRESA') {
    $designerId = (int) ($_POST['designer_id'] ?? 0);
    $amount = (float) ($_POST['goal_amount'] ?? 0);

    if ($designerId > 0) {
        $stmt = db()->prepare('select id from designer_goals where company_id = ? and designer_id = ? and month = ? and year = ?');
        $stmt->execute([$companyId, $designerId, $month, $year]);
        $goalId = $stmt->fetchColumn();

        if ($goalId) {
            db()->prepare('update designer_goals set goal_amount = ? where id = ? and company_id = ?')->execute([$amount, $goalId, $companyId]);
        } else {
            db()->prepare('insert into designer_goals (company_id, designer_id, month, year, goal_amount) values (?, ?, ?, ?, ?)')->execute([$companyId, $designerId, $month, $year, $amount]);
        }
    }

    redirect('/goals.php?month=' . $month . '&year=' . $year . '&ok=1');
}

if (!empty($_GET['delete']) && $user['role'] === 'ADMIN_EMPRESA') {
    db()->prepare('delete from designer_goals where id = ? and company_id = ?')->execute([(int) $_GET['delete'], $companyId]);
    redirect('/goals.php?month=' . $month . '&year=' . $year . '&ok=1');
}

$designersStmt = db()->prepare("select id, name, email from users where company_id = ? and role in ('PROJETISTA','ADMIN_EMPRESA') and active = 1 order by name");
$designersStmt->execute([$companyId]);
$designers = $designersStmt->fetchAll();

$rows = [];
foreach ($designers as $designer) {
    $goalStmt = db()->prepare('select * from designer_goals where company_id = ? and designer_id = ? and month = ? and year = ? limit 1');
    $goalStmt->execute([$companyId, (int) $designer['id'], $month, $year]);
    $goal = $goalStmt->fetch();

    $salesStmt = db()->prepare('select count(*) as count_sales, coalesce(sum(sold_value), 0) as closed from financial_sales where company_id = ? and designer_id = ? and sale_date between ? and ?');
    $salesStmt->execute([$companyId, (int) $designer['id'], $start, $end]);
    $sales = $salesStmt->fetch();

    $goalAmount = (float) ($goal['goal_amount'] ?? 0);
    $closed = (float) ($sales['closed'] ?? 0);
    $count = (int) ($sales['count_sales'] ?? 0);
    $rows[] = [
        'designer' => $designer,
        'goal_id' => $goal['id'] ?? null,
        'goal' => $goalAmount,
        'closed' => $closed,
        'missing' => max(0, $goalAmount - $closed),
        'percent' => $goalAmount > 0 ? min(100, ($closed / $goalAmount) * 100) : 0,
        'count' => $count,
        'ticket' => $count > 0 ? $closed / $count : 0,
    ];
}

usort($rows, fn($a, $b) => $b['closed'] <=> $a['closed']);
$myRow = null;
foreach ($rows as $row) {
    if ((int) $row['designer']['id'] === (int) $user['id']) {
        $myRow = $row;
        break;
    }
}
$myRow ??= [
    'goal' => 0,
    'closed' => 0,
    'missing' => 0,
    'percent' => 0,
    'count' => 0,
    'ticket' => 0,
    'designer' => ['name' => $user['name']],
];

$titleMap = [
    'my-goal' => ['Minha Meta', 'Apenas seus próprios resultados'],
    'team-goals' => ['Metas da Equipe', 'Desempenho operacional dos projetistas'],
    'goals' => ['Metas dos Projetistas', 'Controle de metas e desempenho comercial'],
];
[$pageTitle, $subtitle] = $titleMap[$mode] ?? $titleMap['goals'];

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Meta atualizada.</div>
    <?php endif; ?>

    <form method="get" class="flex flex-col gap-3 rounded-lg border border-line bg-white p-4 md:flex-row md:items-end">
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
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
        <button class="min-h-10 rounded-md border border-line bg-white px-4 text-sm font-semibold" type="submit">Filtrar</button>
    </form>

    <?php if ($mode === 'my-goal'): ?>
        <div class="grid gap-4 md:grid-cols-3">
            <article class="rounded-lg border border-line bg-white p-6">
                <p class="text-sm text-slate-500">Minha meta do mês</p>
                <strong class="mt-2 block text-3xl"><?= money_br($myRow['goal']) ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-6">
                <p class="text-sm text-slate-500">Meu total fechado</p>
                <strong class="mt-2 block text-3xl"><?= money_br($myRow['closed']) ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-6">
                <p class="text-sm text-slate-500">Quanto falta</p>
                <strong class="mt-2 block text-3xl"><?= money_br($myRow['missing']) ?></strong>
            </article>
        </div>

        <section class="rounded-lg border border-line bg-white p-6">
            <p class="text-sm text-slate-500">Percentual atingido</p>
            <div class="mt-3 flex items-center gap-4">
                <div class="h-3 flex-1 rounded-full bg-slate-100">
                    <div class="h-3 rounded-full bg-ink" style="width: <?= number_format($myRow['percent'], 1, '.', '') ?>%"></div>
                </div>
                <strong><?= number_format($myRow['percent'], 1, ',', '.') ?>%</strong>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-lg border border-line bg-white p-6">
                <p class="text-sm text-slate-500">Projetos fechados</p>
                <strong class="mt-2 block text-2xl"><?= (int) $myRow['count'] ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-6">
                <p class="text-sm text-slate-500">Ticket médio</p>
                <strong class="mt-2 block text-2xl"><?= money_br($myRow['ticket']) ?></strong>
            </article>
        </div>
    <?php else: ?>
        <?php if ($user['role'] === 'ADMIN_EMPRESA' && $mode === 'goals'): ?>
            <form method="post" class="grid gap-3 rounded-lg border border-line bg-white p-4 md:grid-cols-[1fr_180px_auto] md:items-end">
                <label class="grid gap-1 text-sm font-semibold">Projetista
                    <select class="min-h-10 rounded-md border border-line px-3" name="designer_id" required>
                        <option value="">Selecionar</option>
                        <?php foreach ($designers as $designer): ?>
                            <option value="<?= (int) $designer['id'] ?>"><?= e($designer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Valor da meta
                    <input class="min-h-10 rounded-md border border-line px-3" type="number" step="0.01" name="goal_amount" required>
                </label>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar meta</button>
            </form>
        <?php endif; ?>

        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold"><?= $mode === 'team-goals' ? 'Metas da Equipe' : 'Metas dos Projetistas' ?></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[940px] text-left text-sm">
                    <thead class="bg-fog text-xs uppercase text-slate-500">
                        <tr>
                            <th class="p-3">Projetista</th>
                            <th class="p-3">Meta</th>
                            <th class="p-3">Fechado no mês</th>
                            <th class="p-3">Falta para meta</th>
                            <th class="p-3">Percentual atingido</th>
                            <th class="p-3">Projetos fechados</th>
                            <th class="p-3">Ticket médio</th>
                            <?php if ($user['role'] === 'ADMIN_EMPRESA' && $mode === 'goals'): ?><th class="p-3 text-right">Ações</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="8" class="p-8 text-center text-slate-500">Nenhuma meta cadastrada</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="border-t border-line hover:bg-fog/60">
                                <td class="p-3 font-semibold"><?= e($row['designer']['name']) ?></td>
                                <td class="p-3"><?= money_br($row['goal']) ?></td>
                                <td class="p-3"><?= money_br($row['closed']) ?></td>
                                <td class="p-3"><?= money_br($row['missing']) ?></td>
                                <td class="p-3">
                                    <div class="w-32">
                                        <span class="text-xs"><?= number_format($row['percent'], 1, ',', '.') ?>%</span>
                                        <div class="mt-1 h-2 rounded-full bg-slate-100">
                                            <div class="h-2 rounded-full bg-ink" style="width: <?= number_format($row['percent'], 1, '.', '') ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3"><?= (int) $row['count'] ?></td>
                                <td class="p-3"><?= money_br($row['ticket']) ?></td>
                                <?php if ($user['role'] === 'ADMIN_EMPRESA' && $mode === 'goals'): ?>
                                    <td class="p-3 text-right">
                                        <?php if ($row['goal_id']): ?>
                                            <a class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-600" href="/goals.php?month=<?= $month ?>&year=<?= $year ?>&delete=<?= (int) $row['goal_id'] ?>" onclick="return confirm('Excluir meta?')">Excluir</a>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
