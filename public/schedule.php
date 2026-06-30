<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);

$companyId = (int) $user['company_id'];
$userId = (int) $user['id'];
$isAdmin = $user['role'] === 'ADMIN_EMPRESA';
$today = new DateTimeImmutable('today');
$month = max(1, min(12, (int) ($_GET['month'] ?? $today->format('n'))));
$year = (int) ($_GET['year'] ?? $today->format('Y'));
[$start, $end] = month_range($year, $month);

function schedule_url(int $month, int $year, array $extra = []): string
{
    return '/schedule.php?' . http_build_query(array_merge(['month' => $month, 'year' => $year], $extra));
}

function ensure_manual_schedule_schema(): void
{
    db()->exec("
        create table if not exists manual_schedule_items (
          id int unsigned auto_increment primary key,
          company_id int unsigned not null,
          user_id int unsigned null,
          client_project_id int unsigned null,
          title varchar(180) not null,
          description text null,
          schedule_date date not null,
          start_time time null,
          category varchar(40) not null default 'COMPROMISSO',
          created_by_user_id int unsigned null,
          created_at timestamp not null default current_timestamp,
          updated_at timestamp null default null on update current_timestamp,
          constraint msi_company_fk foreign key (company_id) references companies(id) on delete cascade,
          constraint msi_user_fk foreign key (user_id) references users(id) on delete set null,
          constraint msi_project_fk foreign key (client_project_id) references client_projects(id) on delete set null,
          constraint msi_created_by_fk foreign key (created_by_user_id) references users(id) on delete set null,
          index msi_company_date_idx (company_id, schedule_date),
          index msi_user_date_idx (user_id, schedule_date)
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci
    ");
}

function can_manage_manual_schedule(array $item, array $user, bool $isAdmin): bool
{
    return $isAdmin || (int) ($item['user_id'] ?? 0) === (int) $user['id'] || (int) ($item['created_by_user_id'] ?? 0) === (int) $user['id'];
}

ensure_manual_schedule_schema();

$employeesStmt = db()->prepare("select id, name, role from users where company_id = ? and active = 1 and role in ('ADMIN_EMPRESA','PROJETISTA','CONFERENTE') order by name");
$employeesStmt->execute([$companyId]);
$employees = $employeesStmt->fetchAll();
$employeeIds = array_map(fn($employee) => (int) $employee['id'], $employees);

$projectsSql = "select p.id, p.client_name, p.project_name, p.current_stage, u.name as designer_name
    from client_projects p
    left join users u on u.id = p.designer_id
    where p.company_id = ?";
$projectsParams = [$companyId];
if ($user['role'] === 'PROJETISTA') {
    $projectsSql .= ' and p.designer_id = ?';
    $projectsParams[] = $userId;
}
$projectsSql .= ' order by p.updated_at desc, p.created_at desc limit 300';
$projectsStmt = db()->prepare($projectsSql);
$projectsStmt->execute($projectsParams);
$projects = $projectsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create_manual', 'update_manual'], true)) {
        $itemId = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduleDate = $_POST['schedule_date'] ?? $today->format('Y-m-d');
        $startTime = trim($_POST['start_time'] ?? '');
        $category = $_POST['category'] ?? 'COMPROMISSO';
        $projectId = (int) ($_POST['client_project_id'] ?? 0);
        $targetUserId = $isAdmin ? (int) ($_POST['user_id'] ?? 0) : $userId;

        if (!$targetUserId || !in_array($targetUserId, $employeeIds, true)) {
            $targetUserId = $userId;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate)) {
            $scheduleDate = $today->format('Y-m-d');
        }
        if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $startTime = '';
        }
        if (!in_array($category, ['COMPROMISSO', 'REUNIAO', 'VISITA', 'LEMBRETE'], true)) {
            $category = 'COMPROMISSO';
        }
        if ($title === '') {
            redirect(schedule_url($month, $year, ['error' => 'title']));
        }

        if ($action === 'create_manual') {
            $stmt = db()->prepare('insert into manual_schedule_items (company_id, user_id, client_project_id, title, description, schedule_date, start_time, category, created_by_user_id) values (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $companyId,
                $targetUserId,
                $projectId > 0 ? $projectId : null,
                $title,
                $description !== '' ? $description : null,
                $scheduleDate,
                $startTime !== '' ? $startTime : null,
                $category,
                $userId,
            ]);
            $dt = new DateTimeImmutable($scheduleDate);
            redirect(schedule_url((int) $dt->format('n'), (int) $dt->format('Y'), ['date' => $scheduleDate, 'ok' => '1']));
        }

        $itemStmt = db()->prepare('select * from manual_schedule_items where id = ? and company_id = ? limit 1');
        $itemStmt->execute([$itemId, $companyId]);
        $item = $itemStmt->fetch();
        if ($item && can_manage_manual_schedule($item, $user, $isAdmin)) {
            $stmt = db()->prepare('update manual_schedule_items set user_id = ?, client_project_id = ?, title = ?, description = ?, schedule_date = ?, start_time = ?, category = ? where id = ? and company_id = ?');
            $stmt->execute([
                $targetUserId,
                $projectId > 0 ? $projectId : null,
                $title,
                $description !== '' ? $description : null,
                $scheduleDate,
                $startTime !== '' ? $startTime : null,
                $category,
                $itemId,
                $companyId,
            ]);
            $dt = new DateTimeImmutable($scheduleDate);
            redirect(schedule_url((int) $dt->format('n'), (int) $dt->format('Y'), ['date' => $scheduleDate, 'ok' => '1']));
        }
        redirect(schedule_url($month, $year, ['error' => 'not-found']));
    }

    if ($action === 'delete_manual') {
        $itemId = (int) ($_POST['id'] ?? 0);
        $itemStmt = db()->prepare('select * from manual_schedule_items where id = ? and company_id = ? limit 1');
        $itemStmt->execute([$itemId, $companyId]);
        $item = $itemStmt->fetch();
        if ($item && can_manage_manual_schedule($item, $user, $isAdmin)) {
            db()->prepare('delete from manual_schedule_items where id = ? and company_id = ?')->execute([$itemId, $companyId]);
        }
        redirect(schedule_url($month, $year, ['date' => $_POST['current_date'] ?? '', 'ok' => '1']));
    }
}

$projectSql = 'select p.*, u.name as designer_name
     from client_projects p
     left join users u on u.id = p.designer_id
     where p.company_id = ?';
$projectParams = [$companyId];
if ($user['role'] === 'PROJETISTA') {
    $projectSql .= ' and p.designer_id = ?';
    $projectParams[] = $userId;
}
$projectSql .= ' order by p.updated_at desc, p.created_at desc';
$stmt = db()->prepare($projectSql);
$stmt->execute($projectParams);
$projectRows = $stmt->fetchAll();

$items = [];
$fields = [
    'presentation_date' => ['Apresentação', 'PROJETO'],
    'closing_date' => ['Fechamento', 'NEGOCIACAO'],
    'measurement_date' => ['Medição', 'CONFERENCIA'],
    'sent_to_factory_date' => ['Envio para fábrica', 'CONFERENCIA'],
    'billing_date' => ['Faturamento', 'CONFERENCIA'],
    'assembly_started_date' => ['Início da montagem', 'MONTAGEM'],
    'assembly_finished_date' => ['Finalização da montagem', 'MONTAGEM'],
    'order_date' => ['Pedido de assistência', 'ASSISTENCIA'],
    'assistance_date' => ['Assistência', 'ASSISTENCIA'],
];

foreach ($projectRows as $project) {
    foreach ($fields as $field => [$title, $stage]) {
        if (!empty($project[$field]) && $project[$field] >= $start && $project[$field] <= $end) {
            $items[] = [
                'type' => 'PROJECT',
                'date' => $project[$field],
                'time' => '',
                'title' => $title,
                'stage' => $stage,
                'project' => $project,
            ];
        }
    }
}

$manualSql = "select m.*, u.name as user_name, p.client_name, p.project_name, p.current_stage
    from manual_schedule_items m
    left join users u on u.id = m.user_id
    left join client_projects p on p.id = m.client_project_id
    where m.company_id = ? and m.schedule_date between ? and ?";
$manualParams = [$companyId, $start, $end];
if ($user['role'] !== 'ADMIN_EMPRESA') {
    $manualSql .= ' and (m.user_id = ? or m.created_by_user_id = ?)';
    $manualParams[] = $userId;
    $manualParams[] = $userId;
}
$manualSql .= ' order by m.schedule_date asc, m.start_time asc, m.created_at asc';
$manualStmt = db()->prepare($manualSql);
$manualStmt->execute($manualParams);
$manualItems = $manualStmt->fetchAll();
foreach ($manualItems as $manual) {
    $items[] = [
        'type' => 'MANUAL',
        'date' => $manual['schedule_date'],
        'time' => $manual['start_time'] ? substr($manual['start_time'], 0, 5) : '',
        'title' => $manual['title'],
        'stage' => 'MANUAL',
        'manual' => $manual,
    ];
}

usort($items, fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['time'] ?? '', $b['time'] ?? '') ?: strcmp($a['title'], $b['title']));

$grouped = [];
$stageCounts = ['PROJETO' => 0, 'NEGOCIACAO' => 0, 'CONFERENCIA' => 0, 'MONTAGEM' => 0, 'ASSISTENCIA' => 0, 'MANUAL' => 0];
foreach ($items as $item) {
    $grouped[$item['date']][] = $item;
    if (isset($stageCounts[$item['stage']])) {
        $stageCounts[$item['stage']]++;
    }
}
$manualCount = (int) ($stageCounts['MANUAL'] ?? 0);
$projectCount = max(0, count($items) - $manualCount);

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

$categoryLabels = [
    'COMPROMISSO' => 'Compromisso',
    'REUNIAO' => 'Reunião',
    'VISITA' => 'Visita',
    'LEMBRETE' => 'Lembrete',
];

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$calendarStart = $firstDay->modify('-' . (int) $firstDay->format('w') . ' days');
$calendarDays = [];
for ($i = 0; $i < 42; $i++) {
    $day = $calendarStart->modify("+{$i} days");
    $key = $day->format('Y-m-d');
    $calendarDays[] = [
        'key' => $key,
        'day' => (int) $day->format('j'),
        'in_month' => (int) $day->format('n') === $month,
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
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Agenda atualizada.</div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">Não foi possível salvar o agendamento.</div><?php endif; ?>

    <section class="overflow-hidden rounded-lg border border-line bg-white">
        <div class="grid gap-4 bg-ink p-5 text-white lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <span class="text-xs font-bold uppercase text-white/60">Agenda operacional</span>
                <h2 class="mt-2 text-2xl font-black">Agendamentos</h2>
                <p class="mt-1 max-w-3xl text-sm text-white/70">Eventos dos projetos e compromissos manuais no mesmo calendário.</p>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/10 p-2">
                <a class="grid h-10 w-10 place-items-center rounded-md bg-white text-ink hover:bg-fog" href="<?= e(schedule_url($prevMonth, $prevYear)) ?>">←</a>
                <span class="min-w-[160px] text-center text-sm font-black"><?= e($monthNames[$month]) ?> <?= $year ?></span>
                <a class="grid h-10 w-10 place-items-center rounded-md bg-white text-ink hover:bg-fog" href="<?= e(schedule_url($nextMonth, $nextYear)) ?>">→</a>
            </div>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-4">
            <div class="rounded-md border border-line bg-fog p-4">
                <span class="text-sm text-slate-500">Total no mês</span>
                <strong class="mt-2 block text-3xl"><?= count($items) ?></strong>
            </div>
            <div class="rounded-md border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Projetos</span>
                <strong class="mt-2 block text-3xl"><?= (int) $projectCount ?></strong>
            </div>
            <div class="rounded-md border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Manuais</span>
                <strong class="mt-2 block text-3xl"><?= (int) $manualCount ?></strong>
            </div>
            <div class="rounded-md border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Dias ocupados</span>
                <strong class="mt-2 block text-3xl"><?= count($grouped) ?></strong>
            </div>
        </div>
    </section>

    <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <section class="min-w-0 rounded-lg border border-line bg-white p-4">
            <div>
                <h3 class="font-bold">Novo agendamento</h3>
                <p class="mt-1 text-sm text-slate-500">Crie compromissos livres sem alterar os projetos.</p>
            </div>
            <form method="post" class="mt-4 grid min-w-0 gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_manual">
                <label class="grid min-w-0 gap-1 text-sm font-semibold">Título
                    <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" name="title" required placeholder="Ex.: Reunião com cliente">
                </label>
                <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Data
                        <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="schedule_date" value="<?= e($selectedDate ?: $today->format('Y-m-d')) ?>" required>
                    </label>
                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Hora
                        <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" type="time" name="start_time">
                    </label>
                </div>
                <label class="grid min-w-0 gap-1 text-sm font-semibold">Categoria
                    <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="category">
                        <?php foreach ($categoryLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($isAdmin): ?>
                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Responsável
                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="user_id">
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>"><?= e($employee['name']) ?> - <?= e(role_label($employee['role'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label class="grid min-w-0 gap-1 text-sm font-semibold">Projeto vinculado
                    <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="client_project_id">
                        <option value="0">Sem projeto</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>"><?= e($project['client_name']) ?> - <?= e($project['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid min-w-0 gap-1 text-sm font-semibold">Observação
                    <textarea class="min-h-20 w-full min-w-0 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="description"></textarea>
                </label>
                <button class="min-h-10 w-full rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" type="submit">Criar agendamento</button>
            </form>
            <div class="mt-4 rounded-md border border-line bg-fog p-3">
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600"><span class="h-2 w-2 rounded-full bg-emerald-700"></span>Evento do projeto</div>
                <div class="mt-2 flex items-center gap-2 text-xs font-semibold text-slate-600"><span class="h-2 w-2 rounded-full bg-indigo-600"></span>Agendamento manual</div>
            </div>
        </section>

        <section class="min-w-0 rounded-lg border border-line bg-white">
            <div class="flex items-center justify-between border-b border-line p-4">
                <h3 class="font-bold"><?= e($monthNames[$month]) ?> de <?= (int) $year ?></h3>
                <span class="text-sm text-slate-500"><?= count($items) ?> agendamento<?= count($items) === 1 ? '' : 's' ?></span>
            </div>
            <div class="p-4 md:p-6">
                <div class="mx-auto max-w-[620px]">
                    <div class="mb-2 grid grid-cols-7 text-center text-xs font-bold uppercase text-slate-500">
                        <span class="py-2">D</span><span class="py-2">S</span><span class="py-2">T</span><span class="py-2">Q</span><span class="py-2">Q</span><span class="py-2">S</span><span class="py-2">S</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <?php foreach ($calendarDays as $day):
                            $dayItems = $day['items'];
                            $hasProject = (bool) array_filter($dayItems, fn($item) => $item['type'] === 'PROJECT');
                            $hasManual = (bool) array_filter($dayItems, fn($item) => $item['type'] === 'MANUAL');
                        ?>
                            <a href="<?= e(schedule_url($month, $year, ['date' => $day['key']])) ?>" class="relative grid aspect-square min-h-16 place-items-center rounded-md border text-sm font-semibold transition hover:border-emerald-700 hover:bg-fog <?= $day['in_month'] ? 'border-line bg-white text-ink' : 'border-transparent bg-fog/50 text-slate-300' ?>">
                                <span class="grid h-9 w-9 place-items-center rounded-full <?= $day['is_today'] ? 'bg-ink text-white' : '' ?>"><?= (int) $day['day'] ?></span>
                                <?php if ($hasProject || $hasManual): ?>
                                    <span class="absolute bottom-2 flex gap-1">
                                        <?php if ($hasProject): ?><span class="h-1.5 w-1.5 rounded-full bg-emerald-700"></span><?php endif; ?>
                                        <?php if ($hasManual): ?><span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span><?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($selectedDate): ?>
        <div class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" onclick="if(event.target===this)location.href='<?= e(schedule_url($month, $year)) ?>'">
            <section class="w-full max-w-3xl rounded-lg border border-line bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-line p-4">
                    <h3 class="font-bold">Agendamentos - <?= e(date_br($selectedDate)) ?></h3>
                    <a href="<?= e(schedule_url($month, $year)) ?>" class="grid h-9 w-9 place-items-center rounded-md hover:bg-fog text-lg">x</a>
                </div>
                <div class="max-h-[70vh] overflow-y-auto p-4">
                    <?php $selectedItems = $grouped[$selectedDate] ?? []; ?>
                    <?php if ($selectedItems): ?>
                        <div class="grid gap-3">
                            <?php foreach ($selectedItems as $item): ?>
                                <?php if ($item['type'] === 'MANUAL'): $manual = $item['manual']; ?>
                                    <article class="rounded-md border border-indigo-100 bg-indigo-50/40 p-4">
                                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                            <div>
                                                <strong><?= e(($item['time'] ? $item['time'] . ' - ' : '') . $item['title']) ?></strong>
                                                <span class="block text-sm text-slate-500"><?= e($categoryLabels[$manual['category']] ?? $manual['category']) ?><?= $manual['user_name'] ? ' - ' . e($manual['user_name']) : '' ?></span>
                                            </div>
                                            <span class="w-fit rounded bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-700">Manual</span>
                                        </div>
                                        <div class="mt-3 grid gap-1 text-sm text-slate-500">
                                            <?php if ($manual['client_project_id']): ?><span>Projeto: <?= e($manual['client_name']) ?> - <?= e($manual['project_name']) ?></span><?php endif; ?>
                                            <?php if ($manual['description']): ?><span><?= e($manual['description']) ?></span><?php endif; ?>
                                        </div>
                                        <?php if (can_manage_manual_schedule($manual, $user, $isAdmin)): ?>
                                            <details class="mt-3 rounded-md border border-line bg-white">
                                                <summary class="cursor-pointer px-3 py-2 text-sm font-bold">Editar agendamento</summary>
                                                <form method="post" class="grid gap-3 border-t border-line p-3">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_manual">
                                                    <input type="hidden" name="id" value="<?= (int) $manual['id'] ?>">
                                                    <label class="grid gap-1 text-sm font-semibold">Título
                                                        <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="title" required value="<?= e($manual['title']) ?>">
                                                    </label>
                                                    <div class="grid gap-3 md:grid-cols-3">
                                                        <label class="grid gap-1 text-sm font-semibold">Data
                                                            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="schedule_date" value="<?= e($manual['schedule_date']) ?>" required>
                                                        </label>
                                                        <label class="grid gap-1 text-sm font-semibold">Hora
                                                            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="time" name="start_time" value="<?= e($item['time']) ?>">
                                                        </label>
                                                        <label class="grid gap-1 text-sm font-semibold">Categoria
                                                            <select class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="category">
                                                                <?php foreach ($categoryLabels as $value => $label): ?>
                                                                    <option value="<?= e($value) ?>" <?= $manual['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    </div>
                                                    <?php if ($isAdmin): ?>
                                                        <label class="grid gap-1 text-sm font-semibold">Responsável
                                                            <select class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="user_id">
                                                                <?php foreach ($employees as $employee): ?>
                                                                    <option value="<?= (int) $employee['id'] ?>" <?= (int) $manual['user_id'] === (int) $employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    <?php endif; ?>
                                                    <label class="grid gap-1 text-sm font-semibold">Projeto
                                                        <select class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="client_project_id">
                                                            <option value="0">Sem projeto</option>
                                                            <?php foreach ($projects as $project): ?>
                                                                <option value="<?= (int) $project['id'] ?>" <?= (int) ($manual['client_project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['client_name']) ?> - <?= e($project['project_name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="grid gap-1 text-sm font-semibold">Observação
                                                        <textarea class="min-h-20 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="description"><?= e($manual['description'] ?? '') ?></textarea>
                                                    </label>
                                                    <div class="flex flex-wrap justify-end gap-2">
                                                        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar</button>
                                                    </div>
                                                </form>
                                                <form method="post" class="border-t border-line p-3 text-right" onsubmit="return confirm('Excluir este agendamento manual?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_manual">
                                                    <input type="hidden" name="id" value="<?= (int) $manual['id'] ?>">
                                                    <input type="hidden" name="current_date" value="<?= e($selectedDate) ?>">
                                                    <button class="min-h-9 rounded-md border border-red-200 px-3 text-xs font-semibold text-red-600 hover:bg-red-50" type="submit">Excluir</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </article>
                                <?php else: ?>
                                    <article class="rounded-md border border-line p-4">
                                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                            <div>
                                                <strong><?= e($item['title']) ?></strong>
                                                <span class="block text-sm text-slate-500"><?= e($item['project']['client_name']) ?> - <?= e($item['project']['project_name']) ?></span>
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
                                <?php endif; ?>
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
                <?php foreach ($grouped as $dateKey => $dayItems): ?>
                    <div class="grid gap-3 p-4 md:grid-cols-[140px_1fr]">
                        <div>
                            <span class="text-xs uppercase text-slate-500">Data</span>
                            <strong class="block"><?= date_br($dateKey) ?></strong>
                        </div>
                        <div class="grid gap-2">
                            <?php foreach ($dayItems as $item): ?>
                                <article class="rounded-md border <?= $item['type'] === 'MANUAL' ? 'border-indigo-100 bg-indigo-50/30' : 'border-line' ?> p-3">
                                    <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <strong><?= e(($item['time'] ? $item['time'] . ' - ' : '') . $item['title']) ?></strong>
                                            <span class="block text-sm text-slate-500">
                                                <?php if ($item['type'] === 'MANUAL'): ?>
                                                    <?= e($categoryLabels[$item['manual']['category']] ?? $item['manual']['category']) ?><?= $item['manual']['user_name'] ? ' - ' . e($item['manual']['user_name']) : '' ?>
                                                <?php else: ?>
                                                    <?= e($item['project']['client_name']) ?> - <?= e($item['project']['project_name']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <span class="w-fit rounded px-2 py-1 text-xs font-semibold <?= $item['type'] === 'MANUAL' ? 'bg-indigo-100 text-indigo-700' : 'bg-fog text-slate-600' ?>"><?= $item['type'] === 'MANUAL' ? 'Manual' : e(stage_label($item['stage'])) ?></span>
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
