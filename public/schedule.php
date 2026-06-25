<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
$companyId = (int) $user['company_id'];
$today = new DateTimeImmutable('today');
$month = max(1, min(12, (int) ($_GET['month'] ?? $today->format('n'))));
$year = (int) ($_GET['year'] ?? $today->format('Y'));
[$start, $end] = month_range($year, $month);

$stmt = db()->prepare(
    'select p.*, u.name as designer_name
     from client_projects p
     left join users u on u.id = p.designer_id
     where p.company_id = ?
     order by p.updated_at desc, p.created_at desc'
);
$stmt->execute([$companyId]);
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

$pageTitle = 'Agendamentos';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <form method="get" class="flex flex-col gap-3 rounded-lg border border-line bg-white p-4 md:flex-row md:items-end">
        <div class="mr-auto">
            <h2 class="font-bold">Agenda do mês</h2>
            <p class="mt-1 text-sm text-slate-500">Datas marcadas nos projetos aparecem aqui.</p>
        </div>
        <label class="grid gap-1 text-sm font-semibold">
            Mês
            <input class="min-h-10 w-28 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" min="1" max="12" name="month" value="<?= $month ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">
            Ano
            <input class="min-h-10 w-36 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" name="year" value="<?= $year ?>">
        </label>
        <button class="min-h-10 rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Filtrar</button>
    </form>

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

        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex items-center justify-between border-b border-line p-4">
                <h3 class="font-bold"><?= e($monthNames[$month]) ?> de <?= (int) $year ?></h3>
                <span class="text-sm text-slate-500"><?= count($items) ?> agendamento<?= count($items) === 1 ? '' : 's' ?></span>
            </div>
            <div class="p-4">
                <div class="mx-auto max-w-[580px]">
                    <div class="mb-2 grid grid-cols-7 text-center text-xs font-bold uppercase text-slate-500">
                        <span class="py-2">D</span>
                        <span class="py-2">S</span>
                        <span class="py-2">T</span>
                        <span class="py-2">Q</span>
                        <span class="py-2">Q</span>
                        <span class="py-2">S</span>
                        <span class="py-2">S</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1.5">
                        <?php foreach ($calendarDays as $day): ?>
                            <div class="relative grid aspect-square place-items-center rounded-md border text-sm font-semibold <?= $day['in_month'] ? 'border-line bg-white text-ink' : 'border-transparent bg-fog/50 text-slate-300' ?>">
                                <span class="grid h-11 w-11 place-items-center rounded-full <?= $day['is_today'] ? 'bg-ink text-white' : '' ?>">
                                    <?= (int) $day['day'] ?>
                                </span>
                                <?php if ($day['items']): ?>
                                    <span class="absolute bottom-2 h-1.5 w-1.5 rounded-full bg-emerald-900" title="<?= count($day['items']) ?> agendamento<?= count($day['items']) === 1 ? '' : 's' ?>"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

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
