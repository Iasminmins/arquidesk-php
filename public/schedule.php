<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
$companyId = (int) $user['company_id'];
$today = new DateTimeImmutable('today');
$month = max(1, min(12, (int) ($_GET['month'] ?? $today->format('n'))));
$year = (int) ($_GET['year'] ?? $today->format('Y'));
[$start, $end] = month_range($year, $month);

$projectSql = 'select p.*, u.name as designer_name
     from client_projects p
     left join users u on u.id = p.designer_id
     where p.company_id = ?';
$projectParams = [$companyId];
if ($user['role'] === 'PROJETISTA') {
    $projectSql .= ' and p.designer_id = ?';
    $projectParams[] = (int) $user['id'];
}
$projectSql .= ' order by p.updated_at desc, p.created_at desc';
$stmt = db()->prepare($projectSql);
$stmt->execute($projectParams);
$projects = $stmt->fetchAll();

$items = [];
$fields = [
    'presentation_date' => ['Apresentação', 'PROJETO'],
    'closing_date' => ['Fechamento', 'NEGOCIACAO'],
    'sent_to_factory_date' => ['Envio para fábrica', 'CONFERENCIA'],
    'billing_date' => ['Faturamento', 'CONFERENCIA'],
    'assembly_started_date' => ['Início da montagem', 'MONTAGEM'],
    'assembly_finished_date' => ['Finalização da montagem', 'MONTAGEM'],
    'order_date' => ['Pedido de assistência', 'ASSISTENCIA'],
    'assistance_date' => ['Assistência', 'ASSISTENCIA'],
];

foreach ($projects as $project) {
    foreach ($fields as $field => [$title, $stage]) {
        if (!empty($project[$field]) && $project[$field] >= $start && $project[$field] <= $end) {
            $items[] = [
                'date' => $project[$field],
                'title' => $title,
                'stage' => $stage,
                'project' => $project,
            ];
        }
    }
}

usort($items, fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['title'], $b['title']));

$grouped = [];
$stageCounts = ['PROJETO' => 0, 'NEGOCIACAO' => 0, 'CONFERENCIA' => 0, 'MONTAGEM' => 0, 'ASSISTENCIA' => 0];
foreach ($items as $item) {
    $grouped[$item['date']][] = $item;
    if (isset($stageCounts[$item['stage']])) {
        $stageCounts[$item['stage']]++;
    }
}

$monthNames = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$calendarStart = $firstDay->modify('-' . (int) $firstDay->format('w') . ' days');
$calendarDays = [];
for ($i = 0; $i < 42; $i++) {
    $date = $calendarStart->modify("+{$i} days");
    $key = $date->format('Y-m-d');
    $calendarDays[] = [
        'key' => $key,
        'day' => (int) $date->format('j'),
        'in_month' => (int) $date->format('n') === $month,
        'is_today' => $key === $today->format('Y-m-d'),
        'items' => $grouped[$key] ?? [],
    ];
}

$selectedDate = $_GET['date'] ?? '';

$pageTitle = 'Agendamentos';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>
<section class="grid gap-5">
    <div class="flex flex-col gap-3 rounded-lg border border-line bg-white p-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="font-bold">Agenda do mês</h2>
            <p class="mt-1 text-sm text-slate-500">Clique em um dia para ver os detalhes.</p>
        </div>
        <div class="flex items-center gap-2">
            <a class="grid h-10 w-10 place-items-center rounded-md border border-line hover:bg-fog" href="/schedule.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">←</a>
            <span class="min-w-[160px] text-center font-bold"><?= e($monthNames[$month]) ?> <?= $year ?></span>
            <a class="grid h-10 w-10 place-items-center rounded-md border border-line hover:bg-fog" href="/schedule.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">→</a>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
        <section class="rounded-lg border border-line bg-white p-4">
            <h3 class="font-bold">Resumo</h3>
            <div class="mt-4 rounded-md bg-fog p-5">
                <span class="text-sm text-slate-500">Agendamentos no mês</span>
                <strong class="mt-2 block text-3xl"><?= count($items) ?></strong>
            </div>
            <div class="mt-4 grid gap-3">
                <?php foreach ($stageCounts as $stage => $count): ?>
                    <div class="flex items-center justify-between rounded-md border border-line px-4 py-3 text-sm">
                        <span><?= e(stage_label($stage)) ?></span>
                        <strong><?= (int) $count ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-lg border border-line bg-white">
            <div class="flex items-center justify-between border-b border-line p-4">
                <h3 class="font-bold"><?= e($monthNames[$month]) ?> de <?= (int) $year ?></h3>
                <span class="text-sm text-slate-500"><?= count($items) ?> agendamento<?= count($items) === 1 ? '' : 's' ?></span>
            </div>
            <div class="p-4">
                <div class="mx-auto max-w-[460px]">
                    <div class="mb-2 grid grid-cols-7 text-center text-xs font-bold uppercase text-slate-500">
                        <span class="py-2">D</span><span class="py-2">S</span><span class="py-2">T</span><span class="py-2">Q</span><span class="py-2">Q</span><span class="py-2">S</span><span class="py-2">S</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <?php foreach ($calendarDays as $day):
                            $hasItems = (bool) $day['items'];
                            $isSelected = $selectedDate === $day['key'];
                        ?>
                            <a href="/schedule.php?month=<?= $month ?>&year=<?= $year ?>&date=<?= e($day['key']) ?>" class="relative grid aspect-square place-items-center rounded-md border text-sm font-semibold transition hover:border-emerald-700 hover:bg-fog <?= $day['in_month'] ? 'border-line bg-white text-ink' : 'border-transparent bg-fog/50 text-slate-300' ?>">
                                <span class="grid h-9 w-9 place-items-center rounded-full <?= $day['is_today'] ? 'bg-ink text-white' : '' ?>">
                                    <?= (int) $day['day'] ?>
                                </span>
                                <?php if ($hasItems): ?>
                                    <span class="absolute bottom-1.5 h-1.5 w-1.5 rounded-full bg-emerald-700"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($selectedDate): ?>
        <div class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" onclick="if(event.target===this)location.href='/schedule.php?month=<?= $month ?>&year=<?= $year ?>'">
            <section class="w-full max-w-2xl rounded-lg border border-line bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-line p-4">
                    <h3 class="font-bold">Agendamentos — <?= e(date_br($selectedDate)) ?></h3>
                    <a href="/schedule.php?month=<?= $month ?>&year=<?= $year ?>" class="grid h-9 w-9 place-items-center rounded-md hover:bg-fog text-lg">✕</a>
                </div>
                <div class="max-h-[60vh] overflow-y-auto p-4">
                    <?php $selectedItems = $grouped[$selectedDate] ?? []; ?>
                    <?php if ($selectedItems): ?>
                        <div class="grid gap-3">
                            <?php foreach ($selectedItems as $item): ?>
                                <article class="rounded-md border border-line p-4">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <strong><?= e($item['title']) ?></strong>
                                            <span class="block text-sm text-slate-500"><?= e($item['project']['client_name']) ?> · <?= e($item['project']['project_name']) ?></span>
                                        </div>
                                        <span class="w-fit rounded bg-fog px-2 py-1 text-xs font-semibold text-slate-600"><?= e(stage_label($item['stage'])) ?></span>
                                    </div>
                                    <div class="mt-3 grid gap-1 text-sm text-slate-500 md:grid-cols-2">
                                        <span>Projetista: <?= e($item['project']['designer_name'] ?: '-') ?></span>
                                        <span>Etapa atual: <?= e(stage_label($item['project']['current_stage'])) ?></span>
                                        <span>Cliente: <?= e($item['project']['client_name']) ?></span>
                                        <span>Telefone: <?= e($item['project']['client_phone'] ?: '-') ?> <?= whatsapp_link($item['project']['client_phone'] ?? '') ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-md border border-line bg-fog p-4 text-sm text-slate-500">Nenhum agendamento marcado para este dia.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <?php endif; ?>

        <section class="overflow-hidden rounded-lg border border-line bg-white xl:col-start-2">
            <div class="border-b border-line p-4 font-bold">Lista por data</div>
            <?php if (!$grouped): ?>
                <div class="p-6 text-sm text-slate-500">Nenhum agendamento marcado para este mês.</div>
            <?php endif; ?>
            <div class="divide-y divide-line">
                <?php foreach ($grouped as $date => $dayItems): ?>
                    <div class="grid gap-3 p-4 md:grid-cols-[140px_1fr]">
                        <div>
                            <span class="text-xs uppercase text-slate-500">Data</span>
                            <strong class="block"><?= date_br($date) ?></strong>
                        </div>
                        <div class="grid gap-2">
                            <?php foreach ($dayItems as $item): ?>
                                <article class="rounded-md border border-line p-3">
                                    <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <strong><?= e($item['title']) ?></strong>
                                            <span class="block text-sm text-slate-500"><?= e($item['project']['client_name']) ?> · <?= e($item['project']['project_name']) ?></span>
                                        </div>
                                        <span class="w-fit rounded bg-fog px-2 py-1 text-xs font-semibold text-slate-600"><?= e(stage_label($item['stage'])) ?></span>
                                    </div>
                                    <div class="mt-2 text-xs text-slate-500">
                                        Projetista: <?= e($item['project']['designer_name'] ?: '-') ?> · Etapa atual: <?= e(stage_label($item['project']['current_stage'])) ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
