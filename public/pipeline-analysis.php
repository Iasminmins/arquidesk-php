<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA', 'CONFERENTE'], true)) {
    redirect('/');
}

$companyId = (int) $user['company_id'];
$now       = new DateTimeImmutable();
$months    = max(1, min(24, (int) ($_GET['months'] ?? 3)));

$periodStart = $now->modify("-{$months} months")->format('Y-m-d');
$periodEnd   = $now->format('Y-m-d');

$designerFilter = '';
$designerParams = [];
if ($user['role'] === 'PROJETISTA') {
    $designerFilter = ' and p.designer_id = ?';
    $designerParams = [(int) $user['id']];
} elseif (!empty($_GET['designer_id'])) {
    $designerFilter = ' and p.designer_id = ?';
    $designerParams = [(int) $_GET['designer_id']];
}

// ── 1. Tempo médio por etapa ─────────────────────────────────────────────────
// Para cada par (from_stage → to_stage) no flow_history calcula o tempo médio
// de permanência como: data da saída (to) - data da entrada (from)
// Usa uma self-join no flow_history para parear entrada e saída de cada etapa.
$stageDurationSql = "
    select
        h_in.to_stage as stage,
        count(*)                                                    as transitions,
        round(avg(timestampdiff(hour, h_in.created_at, h_out.created_at) / 24.0), 1) as avg_days,
        round(min(timestampdiff(hour, h_in.created_at, h_out.created_at) / 24.0), 1) as min_days,
        round(max(timestampdiff(hour, h_in.created_at, h_out.created_at) / 24.0), 1) as max_days
    from flow_history h_in
    join flow_history h_out
        on  h_out.client_project_id = h_in.client_project_id
        and h_out.company_id        = h_in.company_id
        and h_out.from_stage        = h_in.to_stage
        and h_out.created_at        > h_in.created_at
    join client_projects p
        on  p.id         = h_in.client_project_id
        and p.company_id = h_in.company_id
    where h_in.company_id = ?
      and date(h_in.created_at) between ? and ?
      and h_in.to_stage != 'FINALIZADO'
      {$designerFilter}
    group by h_in.to_stage
    order by field(h_in.to_stage, 'PROJETO','NEGOCIACAO','CONFERENCIA','MONTAGEM','ASSISTENCIA')
";
$stageDurationStmt = db()->prepare($stageDurationSql);
$stageDurationStmt->execute(array_merge([$companyId, $periodStart, $periodEnd], $designerParams));
$stageDurations = [];
foreach ($stageDurationStmt->fetchAll() as $row) {
    $stageDurations[$row['stage']] = $row;
}

// ── 2. Tempo total médio do ciclo completo (entrada → finalizado) ─────────────
$cycleSql = "
    select
        count(*)                                                                 as total,
        round(avg(timestampdiff(hour, h_first.created_at, h_last.created_at) / 24.0), 1) as avg_days,
        round(min(timestampdiff(hour, h_first.created_at, h_last.created_at) / 24.0), 1) as min_days,
        round(max(timestampdiff(hour, h_first.created_at, h_last.created_at) / 24.0), 1) as max_days
    from (
        select client_project_id, min(created_at) as created_at
        from flow_history
        where company_id = ? and to_stage = 'PROJETO'
          and date(created_at) between ? and ?
        group by client_project_id
    ) h_first
    join (
        select client_project_id, max(created_at) as created_at
        from flow_history
        where company_id = ? and to_stage = 'FINALIZADO'
        group by client_project_id
    ) h_last on h_last.client_project_id = h_first.client_project_id
    join client_projects p on p.id = h_first.client_project_id and p.company_id = ?
    where 1=1 {$designerFilter}
";
$cycleStmt = db()->prepare($cycleSql);
$cycleStmt->execute(array_merge([$companyId, $periodStart, $periodEnd, $companyId, $companyId], $designerParams));
$cycleData = $cycleStmt->fetch();

// ── 3. Projetos que estão parados agora (tempo atual em cada etapa) ───────────
$staleSql = "
    select
        p.current_stage as stage,
        count(*) as total,
        round(avg(
            timestampdiff(hour,
                coalesce((
                    select max(fh2.created_at) from flow_history fh2
                    where fh2.client_project_id = p.id and fh2.company_id = p.company_id and fh2.to_stage = p.current_stage
                ), p.created_at),
            now()) / 24.0
        ), 1) as avg_days_stuck
    from client_projects p
    where p.company_id = ?
      and p.current_stage != 'FINALIZADO'
      and (p.negotiation_status is null or p.negotiation_status != 'Desistida')
      {$designerFilter}
    group by p.current_stage
    order by field(p.current_stage,'PROJETO','NEGOCIACAO','CONFERENCIA','MONTAGEM','ASSISTENCIA')
";
$staleStmt = db()->prepare($staleSql);
$staleStmt->execute(array_merge([$companyId], $designerParams));
$currentStuck = [];
foreach ($staleStmt->fetchAll() as $row) {
    $currentStuck[$row['stage']] = $row;
}

// ── 4. Top 10 projetos mais lentos finalizados no período ─────────────────────
$slowestSql = "
    select
        p.client_name, p.project_name,
        u.name as designer_name,
        date(h_first.created_at)  as started_at,
        date(h_last.created_at)   as finished_at,
        timestampdiff(day, h_first.created_at, h_last.created_at) as total_days
    from (
        select client_project_id, min(created_at) as created_at
        from flow_history where company_id = ? and to_stage = 'PROJETO'
        group by client_project_id
    ) h_first
    join (
        select client_project_id, max(created_at) as created_at
        from flow_history where company_id = ? and to_stage = 'FINALIZADO'
          and date(created_at) between ? and ?
        group by client_project_id
    ) h_last on h_last.client_project_id = h_first.client_project_id
    join client_projects p on p.id = h_first.client_project_id and p.company_id = ?
    left join users u on u.id = p.designer_id
    where 1=1 {$designerFilter}
    order by total_days desc
    limit 10
";
$slowestStmt = db()->prepare($slowestSql);
$slowestStmt->execute(array_merge([$companyId, $companyId, $periodStart, $periodEnd, $companyId], $designerParams));
$slowestProjects = $slowestStmt->fetchAll();

// Designers para filtro (só admin vê)
$designers = [];
if ($user['role'] === 'ADMIN_EMPRESA') {
    $dStmt = db()->prepare("select id, name from users where company_id = ? and role in ('ADMIN_EMPRESA','PROJETISTA') and active = 1 order by name");
    $dStmt->execute([$companyId]);
    $designers = $dStmt->fetchAll();
}

$stages = ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA'];
$maxAvg = max(1, ...array_map(fn($s) => (float) ($stageDurations[$s]['avg_days'] ?? 0), $stages));

$pageTitle = 'Tempo por etapa';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-6">

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-xl font-bold">Análise de tempo por etapa</h1>
            <p class="text-sm text-slate-500">Quanto tempo os projetos ficam em cada fase do fluxo.</p>
        </div>
        <form method="get" class="flex flex-wrap gap-2">
            <select name="months" class="min-h-10 rounded-md border border-line bg-white px-3 text-sm" onchange="this.form.submit()">
                <?php foreach ([1 => '1 mês', 3 => '3 meses', 6 => '6 meses', 12 => '12 meses'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $months === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($user['role'] === 'ADMIN_EMPRESA' && $designers): ?>
                <select name="designer_id" class="min-h-10 rounded-md border border-line bg-white px-3 text-sm" onchange="this.form.submit()">
                    <option value="">Todos os projetistas</option>
                    <?php foreach ($designers as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= ($_GET['designer_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($cycleData && $cycleData['total'] > 0): ?>
    <div class="grid gap-3 sm:grid-cols-4">
        <article class="rounded-lg border border-line bg-white p-4">
            <span class="text-sm text-slate-500">Ciclo médio completo</span>
            <strong class="mt-2 block text-3xl"><?= $cycleData['avg_days'] ?? '-' ?> <span class="text-lg font-normal text-slate-500">dias</span></strong>
            <span class="text-xs text-slate-400">do Projeto ao Finalizado</span>
        </article>
        <article class="rounded-lg border border-line bg-white p-4">
            <span class="text-sm text-slate-500">Mais rápido</span>
            <strong class="mt-2 block text-3xl text-emerald-700"><?= $cycleData['min_days'] ?? '-' ?> <span class="text-lg font-normal text-slate-500">dias</span></strong>
        </article>
        <article class="rounded-lg border border-line bg-white p-4">
            <span class="text-sm text-slate-500">Mais lento</span>
            <strong class="mt-2 block text-3xl text-red-600"><?= $cycleData['max_days'] ?? '-' ?> <span class="text-lg font-normal text-slate-500">dias</span></strong>
        </article>
        <article class="rounded-lg border border-line bg-white p-4">
            <span class="text-sm text-slate-500">Projetos finalizados</span>
            <strong class="mt-2 block text-3xl"><?= (int) $cycleData['total'] ?></strong>
            <span class="text-xs text-slate-400">no período</span>
        </article>
    </div>
    <?php else: ?>
    <div class="rounded-md border border-line bg-fog p-4 text-sm text-slate-500">Ainda não há projetos finalizados no período para calcular o ciclo completo.</div>
    <?php endif; ?>

    <div class="grid gap-4 xl:grid-cols-2">

        <section class="rounded-lg border border-line bg-white p-5">
            <h2 class="font-bold">Tempo médio por etapa</h2>
            <p class="text-sm text-slate-500">Média de dias que projetos ficaram em cada etapa antes de avançar.</p>
            <div class="mt-5 grid gap-4">
                <?php foreach ($stages as $stage): ?>
                    <?php $row = $stageDurations[$stage] ?? null; ?>
                    <?php $avg = $row ? (float) $row['avg_days'] : 0; ?>
                    <?php $barWidth = $maxAvg > 0 ? max(4, (int) round(($avg / $maxAvg) * 100)) : 0; ?>
                    <div class="grid gap-1">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold"><?= e(stage_label($stage)) ?></span>
                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                <?php if ($row): ?>
                                    <span>min <?= $row['min_days'] ?>d</span>
                                    <span class="font-bold text-ink"><?= $avg ?>d média</span>
                                    <span>máx <?= $row['max_days'] ?>d</span>
                                    <span class="text-slate-400"><?= (int) $row['transitions'] ?> transições</span>
                                <?php else: ?>
                                    <span class="text-slate-400">sem dados</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="h-2 rounded-full bg-stone-100">
                            <div class="h-2 rounded-full <?= $avg > 15 ? 'bg-red-400' : ($avg > 7 ? 'bg-amber-400' : 'bg-emerald-600') ?> transition-all"
                                style="width: <?= $barWidth ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 text-xs text-slate-400">Verde = até 7 dias · Amarelo = 8–15 dias · Vermelho = mais de 15 dias</p>
        </section>

        <section class="rounded-lg border border-line bg-white p-5">
            <h2 class="font-bold">Projetos parados agora</h2>
            <p class="text-sm text-slate-500">Tempo médio atual em cada etapa para projetos ainda em aberto.</p>
            <div class="mt-5 grid gap-3">
                <?php foreach ($stages as $stage): ?>
                    <?php $row = $currentStuck[$stage] ?? null; ?>
                    <div class="flex items-center justify-between rounded-md border border-line p-3 text-sm">
                        <span class="font-semibold"><?= e(stage_label($stage)) ?></span>
                        <?php if ($row): ?>
                            <div class="flex items-center gap-4">
                                <span class="text-slate-500"><?= (int) $row['total'] ?> projeto<?= $row['total'] != 1 ? 's' : '' ?></span>
                                <span class="rounded-full px-3 py-0.5 text-xs font-bold
                                    <?= (float) $row['avg_days_stuck'] > 15 ? 'bg-red-100 text-red-700' : ((float) $row['avg_days_stuck'] > 7 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-50 text-emerald-700') ?>">
                                    <?= $row['avg_days_stuck'] ?>d média
                                </span>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">Nenhum projeto aqui</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <?php if ($slowestProjects): ?>
    <section class="rounded-lg border border-line bg-white p-5">
        <h2 class="font-bold">Projetos mais lentos finalizados no período</h2>
        <p class="text-sm text-slate-500">Top 10 projetos que levaram mais tempo do início ao fim.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3 text-left">Cliente</th>
                        <th class="p-3 text-left">Projeto</th>
                        <th class="p-3 text-left">Projetista</th>
                        <th class="p-3 text-left">Início</th>
                        <th class="p-3 text-left">Fim</th>
                        <th class="p-3 text-right">Dias total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slowestProjects as $i => $proj): ?>
                        <tr class="border-t border-line <?= $i % 2 === 0 ? '' : 'bg-fog/40' ?>">
                            <td class="p-3 font-semibold"><?= e($proj['client_name']) ?></td>
                            <td class="p-3 text-slate-600"><?= e($proj['project_name']) ?></td>
                            <td class="p-3 text-slate-600"><?= e($proj['designer_name'] ?: '-') ?></td>
                            <td class="p-3 text-slate-500"><?= date_br($proj['started_at']) ?></td>
                            <td class="p-3 text-slate-500"><?= date_br($proj['finished_at']) ?></td>
                            <td class="p-3 text-right">
                                <span class="rounded-full px-3 py-0.5 text-xs font-bold
                                    <?= $proj['total_days'] > 90 ? 'bg-red-100 text-red-700' : ($proj['total_days'] > 45 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') ?>">
                                    <?= (int) $proj['total_days'] ?>d
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
