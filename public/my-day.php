<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);

$companyId = (int) $user['company_id'];
$userId = (int) $user['id'];
$isAdmin = $user['role'] === 'ADMIN_EMPRESA';
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$date = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}
$view = $_GET['view'] ?? 'day';
if (!in_array($view, ['day', 'week'], true)) {
    $view = 'day';
}

$selectedDate = new DateTimeImmutable($date);
$periodStart = $view === 'week' ? $selectedDate->modify('monday this week') : $selectedDate;
$periodEnd = $view === 'week' ? $periodStart->modify('+6 days') : $selectedDate;
$periodDates = [];
for ($day = $periodStart; $day <= $periodEnd; $day = $day->modify('+1 day')) {
    $periodDates[] = $day->format('Y-m-d');
}
$periodStartDate = $periodStart->format('Y-m-d');
$periodEndDate = $periodEnd->format('Y-m-d');
$periodLabel = $view === 'week'
    ? date_br($periodStartDate) . ' a ' . date_br($periodEndDate)
    : date_br($date);
$previousDate = $periodStart->modify($view === 'week' ? '-7 days' : '-1 day')->format('Y-m-d');
$nextDate = $periodStart->modify($view === 'week' ? '+7 days' : '+1 day')->format('Y-m-d');

function my_day_url(string $date, bool $isAdmin, int $selectedUserId = 0, array $extra = []): string
{
    $view = $extra['view'] ?? ($_GET['view'] ?? 'day');
    if (!in_array($view, ['day', 'week'], true)) {
        $view = 'day';
    }
    $params = ['date' => $date, 'view' => $view];
    if ($isAdmin && $selectedUserId > 0) {
        $params['user_id'] = $selectedUserId;
    }
    return '/my-day.php?' . http_build_query(array_merge($params, $extra));
}

function ensure_daily_checklist_schema(): void
{
    $pdo = db();
    $pdo->exec("
        create table if not exists daily_checklist_items (
          id int unsigned auto_increment primary key,
          company_id int unsigned not null,
          user_id int unsigned not null,
          client_project_id int unsigned null,
          title varchar(180) not null,
          description text null,
          checklist_date date not null,
          priority enum('BAIXA','NORMAL','ALTA') not null default 'NORMAL',
          status enum('PENDENTE','CONCLUIDO','CANCELADO') not null default 'PENDENTE',
          source varchar(20) not null default 'MANUAL',
          auto_key varchar(160) null,
          created_by_user_id int unsigned null,
          completed_at datetime null,
          created_at timestamp not null default current_timestamp,
          updated_at timestamp null default null on update current_timestamp,
          index dci_company_date_idx (company_id, checklist_date),
          index dci_user_date_idx (user_id, checklist_date),
          unique key dci_auto_unique (company_id, user_id, checklist_date, auto_key),
          constraint dci_company_fk foreign key (company_id) references companies(id) on delete cascade,
          constraint dci_user_fk foreign key (user_id) references users(id) on delete cascade,
          constraint dci_project_fk foreign key (client_project_id) references client_projects(id) on delete set null,
          constraint dci_created_by_fk foreign key (created_by_user_id) references users(id) on delete set null
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci
    ");

    $dbName = $pdo->query('select database()')->fetchColumn();
    $columnStmt = $pdo->prepare('select count(*) from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?');
    foreach ([
        'source' => "alter table daily_checklist_items add source varchar(20) not null default 'MANUAL' after status",
        'auto_key' => "alter table daily_checklist_items add auto_key varchar(160) null after source",
    ] as $column => $sql) {
        $columnStmt->execute([$dbName, 'daily_checklist_items', $column]);
        if ((int) $columnStmt->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }

    $indexStmt = $pdo->prepare('select count(*) from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?');
    $indexStmt->execute([$dbName, 'daily_checklist_items', 'dci_auto_unique']);
    if ((int) $indexStmt->fetchColumn() === 0) {
        $pdo->exec('alter table daily_checklist_items add unique key dci_auto_unique (company_id, user_id, checklist_date, auto_key)');
    }
}

function insert_auto_checklist_item(int $companyId, int $userId, ?int $projectId, string $date, string $title, string $description, string $priority, string $autoKey): bool
{
    if ($userId <= 0 || $title === '') {
        return false;
    }

    $stmt = db()->prepare(
        "insert ignore into daily_checklist_items
            (company_id, user_id, client_project_id, title, description, checklist_date, priority, status, source, auto_key, created_by_user_id)
         values (?, ?, ?, ?, ?, ?, ?, 'PENDENTE', 'AUTO', ?, null)"
    );
    $stmt->execute([
        $companyId,
        $userId,
        $projectId,
        $title,
        $description,
        $date,
        in_array($priority, ['BAIXA', 'NORMAL', 'ALTA'], true) ? $priority : 'NORMAL',
        $autoKey,
    ]);

    return $stmt->rowCount() > 0;
}

function generate_daily_auto_items(int $companyId, string $date, array $employees): int
{
    $generated = 0;
    $admins = [];
    $conferentes = [];
    foreach ($employees as $employee) {
        if ($employee['role'] === 'ADMIN_EMPRESA') {
            $admins[] = (int) $employee['id'];
        }
        if ($employee['role'] === 'CONFERENTE') {
            $conferentes[] = (int) $employee['id'];
        }
    }
    $conferenceTargets = $conferentes ?: $admins;

    $projectEvents = [
        'presentation_date' => ['Apresentação marcada hoje', 'PROJETISTA', 'ALTA'],
        'closing_date' => ['Fechamento previsto hoje', 'PROJETISTA', 'ALTA'],
        'measurement_date' => ['Medição agendada hoje', 'CONFERENTE', 'ALTA'],
        'sent_to_factory_date' => ['Envio para fábrica hoje', 'CONFERENTE', 'ALTA'],
        'billing_date' => ['Faturamento previsto hoje', 'CONFERENTE', 'NORMAL'],
        'assembly_started_date' => ['Início de montagem hoje', 'CONFERENTE', 'NORMAL'],
        'assembly_finished_date' => 'Finalização de montagem hoje',
        'assistance_date' => ['Assistência agendada hoje', 'CONFERENTE', 'ALTA'],
        'order_date' => ['Pedido de assistência hoje', 'CONFERENTE', 'NORMAL'],
    ];

    $eventsStmt = db()->prepare(
        "select p.*, u.name as designer_name
         from client_projects p
         left join users u on u.id = p.designer_id
         where p.company_id = ?
           and (
                p.presentation_date = ? or p.closing_date = ? or p.measurement_date = ? or
                p.sent_to_factory_date = ? or p.billing_date = ? or p.assembly_started_date = ? or
                p.assembly_finished_date = ? or p.assistance_date = ? or p.order_date = ?
           )
         limit 120"
    );
    $eventsStmt->execute([$companyId, $date, $date, $date, $date, $date, $date, $date, $date, $date]);
    foreach ($eventsStmt->fetchAll() as $project) {
        foreach ($projectEvents as $field => $config) {
            if (empty($project[$field]) || $project[$field] !== $date) {
                continue;
            }
            if (is_string($config)) {
                $config = [$config, 'CONFERENTE', 'NORMAL'];
            }
            [$label, $targetRole, $priority] = $config;
            $targetUsers = $targetRole === 'PROJETISTA' && !empty($project['designer_id'])
                ? [(int) $project['designer_id']]
                : $conferenceTargets;
            foreach ($targetUsers as $targetUserId) {
                $title = $label . ': ' . $project['client_name'] . ' - ' . $project['project_name'];
                $description = 'Criado automaticamente pela agenda do projeto em ' . stage_label($project['current_stage']) . '.';
                $generated += insert_auto_checklist_item($companyId, $targetUserId, (int) $project['id'], $date, $title, $description, $priority, "project-date:{$project['id']}:{$field}") ? 1 : 0;
            }
        }
    }

    $futureStmt = db()->prepare(
        "select fc.*
         from future_clients fc
         where fc.company_id = ?
           and fc.designer_id is not null
           and fc.next_contact_date is not null
           and fc.next_contact_date <= ?
           and fc.status not in ('CONVERTIDO','PERDIDO')
           and fc.next_contact_date >= date_sub(?, interval 30 day)
         order by fc.next_contact_date asc
         limit 20"
    );
    $futureStmt->execute([$companyId, $date, $date]);
    foreach ($futureStmt->fetchAll() as $future) {
        $title = 'Retomar cliente futuro: ' . $future['name'];
        $description = 'Próximo contato estava marcado para ' . date_br($future['next_contact_date']) . '. Interesse: ' . ($future['interest'] ?: '-');
        $generated += insert_auto_checklist_item($companyId, (int) $future['designer_id'], null, $date, $title, $description, 'ALTA', "future-client:{$future['id']}:{$future['next_contact_date']}") ? 1 : 0;
    }

    $staleDesignerStmt = db()->prepare(
        "select p.*
         from client_projects p
         where p.company_id = ?
           and p.designer_id is not null
           and p.current_stage in ('PROJETO','NEGOCIACAO')
           and date(coalesce(p.updated_at, p.created_at)) <= date_sub(?, interval 7 day)
           and (p.negotiation_status is null or p.negotiation_status not in ('Desistida','Perdido'))
         order by coalesce(p.updated_at, p.created_at) asc
         limit 12"
    );
    $staleDesignerStmt->execute([$companyId, $date]);
    foreach ($staleDesignerStmt->fetchAll() as $project) {
        $title = 'Projeto parado: ' . $project['client_name'] . ' - ' . $project['project_name'];
        $description = 'Sem atualização recente em ' . stage_label($project['current_stage']) . '. Revisar próxima ação e status.';
        $generated += insert_auto_checklist_item($companyId, (int) $project['designer_id'], (int) $project['id'], $date, $title, $description, 'NORMAL', "stale-designer:{$project['id']}:{$project['current_stage']}") ? 1 : 0;
    }

    if ($conferenceTargets) {
        $staleOpsStmt = db()->prepare(
            "select p.*
             from client_projects p
             where p.company_id = ?
               and p.current_stage in ('CONFERENCIA','MONTAGEM','ASSISTENCIA')
               and date(coalesce(p.updated_at, p.created_at)) <= date_sub(?, interval 5 day)
             order by coalesce(p.updated_at, p.created_at) asc
             limit 12"
        );
        $staleOpsStmt->execute([$companyId, $date]);
        foreach ($staleOpsStmt->fetchAll() as $project) {
            foreach ($conferenceTargets as $targetUserId) {
                $title = 'Conferir pendência: ' . $project['client_name'] . ' - ' . $project['project_name'];
                $description = 'Projeto sem movimentação recente em ' . stage_label($project['current_stage']) . '. Validar se pode avançar.';
                $generated += insert_auto_checklist_item($companyId, $targetUserId, (int) $project['id'], $date, $title, $description, 'NORMAL', "stale-ops:{$project['id']}:{$project['current_stage']}") ? 1 : 0;
            }
        }
    }

    $financeStmt = db()->prepare(
        "select p.*
         from client_projects p
         left join financial_sales fs on fs.client_project_id = p.id and fs.company_id = p.company_id
         where p.company_id = ?
           and p.designer_id is not null
           and fs.id is null
           and (p.negotiation_status = 'Fechado' or (p.closed_value is not null and p.closed_value > 0))
           and coalesce(p.closing_date, date(p.updated_at), date(p.created_at)) between date_sub(?, interval 30 day) and ?
         order by coalesce(p.closing_date, p.updated_at, p.created_at) desc
         limit 12"
    );
    $financeStmt->execute([$companyId, $date, $date]);
    foreach ($financeStmt->fetchAll() as $project) {
        $title = 'Lançar venda no financeiro: ' . $project['client_name'] . ' - ' . $project['project_name'];
        $description = 'Projeto fechado ainda não tem venda vinculada no financeiro.';
        $generated += insert_auto_checklist_item($companyId, (int) $project['designer_id'], (int) $project['id'], $date, $title, $description, 'ALTA', "finance-missing:{$project['id']}") ? 1 : 0;
    }

    return $generated;
}

function checklist_item_allowed(array $item, array $user, bool $isAdmin): bool
{
    return $isAdmin || (int) $item['user_id'] === (int) $user['id'];
}

ensure_daily_checklist_schema();

$employeesStmt = db()->prepare("select id, name, role from users where company_id = ? and active = 1 and role in ('ADMIN_EMPRESA','PROJETISTA','CONFERENTE') order by name");
$employeesStmt->execute([$companyId]);
$employees = $employeesStmt->fetchAll();
$employeeIds = array_map(fn($employee) => (int) $employee['id'], $employees);
$autoGenerated = 0;
foreach ($periodDates as $periodDate) {
    $autoGenerated += generate_daily_auto_items($companyId, $periodDate, $employees);
}

$selectedUserId = $isAdmin ? (int) ($_GET['user_id'] ?? 0) : $userId;
if (!$isAdmin || $selectedUserId <= 0) {
    $selectedUserId = $isAdmin ? 0 : $userId;
}
if ($isAdmin && $selectedUserId > 0 && !in_array($selectedUserId, $employeeIds, true)) {
    $selectedUserId = 0;
}

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

    if (in_array($action, ['create', 'update'], true)) {
        $itemId = (int) ($_POST['id'] ?? 0);
        $targetUserId = $isAdmin ? (int) ($_POST['user_id'] ?? 0) : $userId;
        if (!$targetUserId || !in_array($targetUserId, $employeeIds, true)) {
            $targetUserId = $userId;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $itemDate = $_POST['checklist_date'] ?? $date;
        $priority = $_POST['priority'] ?? 'NORMAL';
        $status = $_POST['status'] ?? 'PENDENTE';
        $projectId = (int) ($_POST['client_project_id'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $itemDate)) {
            $itemDate = $date;
        }
        if (!in_array($priority, ['BAIXA', 'NORMAL', 'ALTA'], true)) {
            $priority = 'NORMAL';
        }
        if (!in_array($status, ['PENDENTE', 'CONCLUIDO', 'CANCELADO'], true)) {
            $status = 'PENDENTE';
        }
        if ($title === '') {
            redirect(my_day_url($date, $isAdmin, $selectedUserId, ['error' => 'title']));
        }

        if ($action === 'create') {
            $stmt = db()->prepare("insert into daily_checklist_items (company_id, user_id, client_project_id, title, description, checklist_date, priority, status, source, created_by_user_id, completed_at) values (?,?,?,?,?,?,?,?, 'MANUAL', ?, ?)");
            $stmt->execute([
                $companyId,
                $targetUserId,
                $projectId > 0 ? $projectId : null,
                $title,
                $description !== '' ? $description : null,
                $itemDate,
                $priority,
                $status,
                $userId,
                $status === 'CONCLUIDO' ? date('Y-m-d H:i:s') : null,
            ]);
            redirect(my_day_url($itemDate, $isAdmin, $selectedUserId, ['ok' => 'created']));
        }

        $itemStmt = db()->prepare('select * from daily_checklist_items where id = ? and company_id = ? limit 1');
        $itemStmt->execute([$itemId, $companyId]);
        $item = $itemStmt->fetch();
        if ($item && checklist_item_allowed($item, $user, $isAdmin)) {
            $stmt = db()->prepare(
                "update daily_checklist_items
                 set user_id = ?, client_project_id = ?, title = ?, description = ?, checklist_date = ?, priority = ?, status = ?,
                     completed_at = case when ? = 'CONCLUIDO' then coalesce(completed_at, now()) else null end
                 where id = ? and company_id = ?"
            );
            $stmt->execute([
                $targetUserId,
                $projectId > 0 ? $projectId : null,
                $title,
                $description !== '' ? $description : null,
                $itemDate,
                $priority,
                $status,
                $status,
                $itemId,
                $companyId,
            ]);
            redirect(my_day_url($itemDate, $isAdmin, $selectedUserId, ['ok' => 'updated']));
        }
        redirect(my_day_url($date, $isAdmin, $selectedUserId, ['error' => 'not-found']));
    }

    if (in_array($action, ['complete', 'reopen', 'cancel', 'delete'], true)) {
        $itemId = (int) ($_POST['id'] ?? 0);
        $itemStmt = db()->prepare('select * from daily_checklist_items where id = ? and company_id = ? limit 1');
        $itemStmt->execute([$itemId, $companyId]);
        $item = $itemStmt->fetch();

        if ($item && checklist_item_allowed($item, $user, $isAdmin)) {
            if ($action === 'delete') {
                db()->prepare('delete from daily_checklist_items where id = ? and company_id = ?')->execute([$itemId, $companyId]);
            } elseif ($action === 'complete') {
                db()->prepare("update daily_checklist_items set status = 'CONCLUIDO', completed_at = now() where id = ?")->execute([$itemId]);
            } elseif ($action === 'reopen') {
                db()->prepare("update daily_checklist_items set status = 'PENDENTE', completed_at = null where id = ?")->execute([$itemId]);
            } else {
                db()->prepare("update daily_checklist_items set status = 'CANCELADO', completed_at = null where id = ?")->execute([$itemId]);
            }
        }
        redirect(my_day_url($date, $isAdmin, $selectedUserId, ['ok' => 'updated']));
    }
}

$itemsSql = "select d.*, p.client_name, p.project_name, p.current_stage, u.name as user_name, u.role as user_role
    from daily_checklist_items d
    join users u on u.id = d.user_id
    left join client_projects p on p.id = d.client_project_id
    where d.company_id = ? and d.checklist_date between ? and ?";
$itemsParams = [$companyId, $periodStartDate, $periodEndDate];
if ($isAdmin && $selectedUserId > 0) {
    $itemsSql .= ' and d.user_id = ?';
    $itemsParams[] = $selectedUserId;
} elseif (!$isAdmin) {
    $itemsSql .= ' and d.user_id = ?';
    $itemsParams[] = $userId;
}
$itemsSql .= " order by d.checklist_date asc, field(d.status, 'PENDENTE','CONCLUIDO','CANCELADO'), field(d.priority, 'ALTA','NORMAL','BAIXA'), d.source desc, d.created_at desc";
$itemsStmt = db()->prepare($itemsSql);
$itemsStmt->execute($itemsParams);
$items = $itemsStmt->fetchAll();

$stats = ['PENDENTE' => 0, 'CONCLUIDO' => 0, 'CANCELADO' => 0, 'AUTO' => 0, 'TOTAL' => count($items)];
$dayStats = [];
foreach ($periodDates as $periodDate) {
    $dayStats[$periodDate] = ['PENDENTE' => 0, 'CONCLUIDO' => 0, 'TOTAL' => 0];
}
foreach ($items as $item) {
    $stats[$item['status']] = ($stats[$item['status']] ?? 0) + 1;
    if (($item['source'] ?? 'MANUAL') === 'AUTO') {
        $stats['AUTO']++;
    }
    if (isset($dayStats[$item['checklist_date']])) {
        $dayStats[$item['checklist_date']]['TOTAL']++;
        if (isset($dayStats[$item['checklist_date']][$item['status']])) {
            $dayStats[$item['checklist_date']][$item['status']]++;
        }
    }
}

$priorityClasses = [
    'ALTA' => 'bg-red-50 text-red-700 border-red-200',
    'NORMAL' => 'bg-amber-50 text-amber-700 border-amber-200',
    'BAIXA' => 'bg-slate-50 text-slate-600 border-slate-200',
];

$statusClasses = [
    'PENDENTE' => 'bg-amber-50 text-amber-700',
    'CONCLUIDO' => 'bg-emerald-50 text-emerald-700',
    'CANCELADO' => 'bg-slate-100 text-slate-500',
];

$sourceClasses = [
    'AUTO' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
    'MANUAL' => 'bg-slate-50 text-slate-600 border-slate-200',
];

$statusLabels = [
    'PENDENTE' => 'Pendente',
    'CONCLUIDO' => 'Concluído',
    'CANCELADO' => 'Cancelado',
];

$priorityLabels = [
    'ALTA' => 'Alta',
    'NORMAL' => 'Normal',
    'BAIXA' => 'Baixa',
];
$weekdayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];

$pageTitle = 'Meu Dia';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Checklist atualizado.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">Não foi possível salvar. Verifique os campos obrigatórios.</div>
    <?php endif; ?>

    <section class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="grid gap-5 border-b border-line bg-ink p-5 text-white xl:grid-cols-[1fr_auto] xl:items-end">
            <div>
                <span class="text-xs font-bold uppercase tracking-wide text-white/60">Rotina inteligente</span>
                <h2 class="mt-2 text-2xl font-black"><?= $view === 'week' ? 'Minha Semana' : 'Meu Dia' ?></h2>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold text-white/80">
                    <a class="inline-flex min-h-9 items-center rounded-md border border-white/15 bg-white/10 px-3 hover:bg-white/15" href="<?= e(my_day_url($previousDate, $isAdmin, $selectedUserId)) ?>"><?= $view === 'week' ? 'Semana anterior' : 'Dia anterior' ?></a>
                    <span class="inline-flex min-h-9 items-center rounded-md bg-white px-3 text-ink"><?= e($periodLabel) ?></span>
                    <a class="inline-flex min-h-9 items-center rounded-md border border-white/15 bg-white/10 px-3 hover:bg-white/15" href="<?= e(my_day_url($nextDate, $isAdmin, $selectedUserId)) ?>"><?= $view === 'week' ? 'Proxima semana' : 'Proximo dia' ?></a>
                </div>
                <p class="mt-1 max-w-3xl text-sm text-white/70">Pendências manuais e automáticas em um único lugar, sempre editáveis pela equipe.</p>
            </div>
            <form method="get" class="grid w-full min-w-0 gap-2 rounded-lg border border-white/10 bg-white/10 p-3 backdrop-blur sm:grid-cols-2 lg:grid-cols-[140px_160px_minmax(0,220px)_auto] lg:items-end">
                <label class="grid gap-1 text-sm font-semibold text-white/80">Visualizar
                    <select class="min-h-10 w-full min-w-0 rounded-md border border-white/20 bg-white px-3 text-ink outline-none focus:border-white" name="view">
                        <option value="day" <?= $view === 'day' ? 'selected' : '' ?>>Dia</option>
                        <option value="week" <?= $view === 'week' ? 'selected' : '' ?>>Semana</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold text-white/80">Data
                    <input class="min-h-10 w-full min-w-0 rounded-md border border-white/20 bg-white px-3 text-ink outline-none focus:border-white" type="date" name="date" value="<?= e($date) ?>">
                </label>
                <?php if ($isAdmin): ?>
                    <label class="grid gap-1 text-sm font-semibold text-white/80">Funcionário
                        <select class="min-h-10 w-full min-w-0 rounded-md border border-white/20 bg-white px-3 text-ink outline-none focus:border-white" name="user_id">
                            <option value="0">Todos</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= $selectedUserId === (int) $employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <button class="min-h-10 rounded-md bg-white px-4 text-sm font-black text-ink hover:bg-fog" type="submit">Aplicar</button>
            </form>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-4">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <span class="text-sm text-slate-500">Pendentes</span>
                <strong class="mt-2 block text-3xl"><?= (int) $stats['PENDENTE'] ?></strong>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <span class="text-sm text-slate-500">Concluídos</span>
                <strong class="mt-2 block text-3xl"><?= (int) $stats['CONCLUIDO'] ?></strong>
            </div>
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <span class="text-sm text-slate-500">Automáticos</span>
                <strong class="mt-2 block text-3xl"><?= (int) $stats['AUTO'] ?></strong>
            </div>
            <div class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Total <?= $view === 'week' ? 'da semana' : 'do dia' ?></span>
                <strong class="mt-2 block text-3xl"><?= (int) $stats['TOTAL'] ?></strong>
            </div>
        </div>
        <?php if ($view === 'week'): ?>
            <div class="grid gap-2 border-t border-line bg-fog/50 p-4 sm:grid-cols-2 lg:grid-cols-7">
                <?php foreach ($periodDates as $periodDate): ?>
                    <?php $isSelectedPeriodDate = $periodDate === $date; ?>
                    <a href="<?= e(my_day_url($periodDate, $isAdmin, $selectedUserId, ['view' => 'week'])) ?>#day-<?= e($periodDate) ?>" class="rounded-lg border p-3 text-sm hover:border-ink <?= $isSelectedPeriodDate ? 'border-ink bg-white shadow-sm ring-2 ring-ink/10' : 'border-line bg-white' ?>">
                        <span class="block text-xs font-bold uppercase text-slate-400"><?= e($weekdayLabels[(int) date('w', strtotime($periodDate))]) ?></span>
                        <strong class="mt-1 block"><?= e(date_br($periodDate)) ?></strong>
                        <span class="mt-2 block text-xs text-slate-500"><?= (int) $dayStats[$periodDate]['PENDENTE'] ?> pend. / <?= (int) $dayStats[$periodDate]['CONCLUIDO'] ?> concl.</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid min-w-0 gap-5">
        <details class="group min-w-0 overflow-hidden rounded-lg border border-line bg-white">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 hover:bg-fog/60">
                <div>
                    <h3 class="font-bold">Novo item</h3>
                    <p class="mt-1 text-sm text-slate-500">Adicione uma pendência manual para o dia.</p>
                </div>
                <span class="inline-flex min-h-10 shrink-0 items-center rounded-md bg-ink px-4 text-sm font-bold text-white group-open:hidden">Criar tarefa</span>
                <span class="hidden min-h-10 shrink-0 items-center rounded-md border border-line px-4 text-sm font-bold group-open:inline-flex">Fechar</span>
            </summary>
            <form method="post" class="grid gap-3 border-t border-line bg-white p-4 lg:grid-cols-2 xl:grid-cols-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <label class="grid min-w-0 gap-1 text-sm font-semibold">Data do item
                    <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="checklist_date" value="<?= e($date) ?>">
                </label>
                <?php if ($isAdmin): ?>
                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Responsável
                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="user_id">
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= ($selectedUserId ?: $userId) === (int) $employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?> - <?= e(role_label($employee['role'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label class="grid min-w-0 gap-1 text-sm font-semibold xl:col-span-2">Título
                    <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" name="title" required placeholder="Ex.: Conferir medidas do cliente Ana">
                </label>
                <label class="grid min-w-0 gap-1 text-sm font-semibold xl:col-span-2">Projeto vinculado
                    <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="client_project_id">
                        <option value="0">Sem projeto</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>"><?= e($project['client_name']) ?> - <?= e($project['project_name']) ?> (<?= e(stage_label($project['current_stage'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Prioridade
                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="priority">
                            <?php foreach ($priorityLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Status
                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="grid min-w-0 gap-1 text-sm font-semibold lg:col-span-2 xl:col-span-3">Observação
                    <textarea class="min-h-24 w-full min-w-0 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="description" placeholder="Detalhe rápido da pendência"></textarea>
                </label>
                <div class="flex items-end">
                    <button class="min-h-10 w-full min-w-0 rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" type="submit">Adicionar pendencia</button>
                </div>
            </form>
        </details>

        <section class="min-w-0 overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-col gap-1 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-bold">Itens de <?= e($periodLabel) ?></h3>
                    <p class="text-sm text-slate-500"><?= $autoGenerated > 0 ? $autoGenerated . ' nova(s) pendência(s) automática(s) criada(s).' : 'As regras automáticas já foram verificadas.' ?></p>
                </div>
                <span class="text-sm font-semibold text-slate-500"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'itens' ?></span>
            </div>
            <div class="divide-y divide-line">
                <?php if (!$items): ?>
                    <div class="p-6 text-sm text-slate-500">Nenhum item criado para este periodo.</div>
                <?php endif; ?>
                <?php $lastRenderedDate = null; ?>
                <?php foreach ($items as $item): ?>
                    <?php if ($view === 'week' && $lastRenderedDate !== $item['checklist_date']): ?>
                        <?php $lastRenderedDate = $item['checklist_date']; ?>
                        <div id="day-<?= e($lastRenderedDate) ?>" class="scroll-mt-24 bg-fog px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <?= e(date_br($lastRenderedDate)) ?>
                        </div>
                    <?php endif; ?>
                    <article class="p-4 <?= $item['status'] === 'CONCLUIDO' ? 'bg-emerald-50/30' : '' ?>">
                        <div class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-start">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="break-words <?= $item['status'] === 'CONCLUIDO' ? 'text-slate-500 line-through' : '' ?>"><?= e($item['title']) ?></strong>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= e($statusClasses[$item['status']] ?? 'bg-slate-100 text-slate-500') ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span>
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($priorityClasses[$item['priority']] ?? $priorityClasses['NORMAL']) ?>"><?= e($priorityLabels[$item['priority']] ?? $item['priority']) ?></span>
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($sourceClasses[$item['source'] ?? 'MANUAL'] ?? $sourceClasses['MANUAL']) ?>"><?= ($item['source'] ?? 'MANUAL') === 'AUTO' ? 'Automático' : 'Manual' ?></span>
                                </div>
                                <div class="mt-2 grid gap-1 text-sm text-slate-500">
                                    <?php if ($isAdmin): ?><span>Responsável: <?= e($item['user_name']) ?> - <?= e(role_label($item['user_role'])) ?></span><?php endif; ?>
                                    <?php if ($item['client_project_id']): ?>
                                        <span>Projeto: <?= e($item['client_name']) ?> - <?= e($item['project_name']) ?> (<?= e(stage_label($item['current_stage'])) ?>)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['description'])): ?><span><?= e($item['description']) ?></span><?php endif; ?>
                                    <?php if (!empty($item['completed_at'])): ?><span>Concluído em <?= e(date('d/m/Y H:i', strtotime($item['completed_at']))) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                <?php if ($item['status'] === 'PENDENTE'): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="min-h-9 rounded-md bg-ink px-3 text-xs font-bold text-white" type="submit">Concluir</button>
                                    </form>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="min-h-9 rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" type="submit">Cancelar</button>
                                    </form>
                                <?php elseif ($item['status'] === 'CONCLUIDO'): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reopen">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="min-h-9 rounded-md border border-line px-3 text-xs font-semibold hover:bg-fog" type="submit">Reabrir</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('Excluir este item do checklist?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <button class="min-h-9 rounded-md border border-red-200 px-3 text-xs font-semibold text-red-600 hover:bg-red-50" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                        <details class="mt-4 rounded-md border border-line bg-fog/60">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-bold text-slate-700">Editar item</summary>
                            <form method="post" class="grid gap-3 border-t border-line bg-white p-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <div class="grid gap-3 lg:grid-cols-[1fr_170px]">
                                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Título
                                        <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" name="title" required value="<?= e($item['title']) ?>">
                                    </label>
                                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Data
                                        <input class="min-h-10 w-full min-w-0 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="checklist_date" value="<?= e($item['checklist_date']) ?>">
                                    </label>
                                </div>
                                <div class="grid gap-3 lg:grid-cols-4">
                                    <?php if ($isAdmin): ?>
                                        <label class="grid min-w-0 gap-1 text-sm font-semibold">Responsável
                                            <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="user_id">
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?= (int) $employee['id'] ?>" <?= (int) $item['user_id'] === (int) $employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    <?php endif; ?>
                                    <label class="grid min-w-0 gap-1 text-sm font-semibold <?= $isAdmin ? '' : 'lg:col-span-2' ?>">Projeto
                                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="client_project_id">
                                            <option value="0">Sem projeto</option>
                                            <?php foreach ($projects as $project): ?>
                                                <option value="<?= (int) $project['id'] ?>" <?= (int) ($item['client_project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['client_name']) ?> - <?= e($project['project_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Prioridade
                                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="priority">
                                            <?php foreach ($priorityLabels as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= $item['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="grid min-w-0 gap-1 text-sm font-semibold">Status
                                        <select class="min-h-10 w-full min-w-0 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="status">
                                            <?php foreach ($statusLabels as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= $item['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <label class="grid min-w-0 gap-1 text-sm font-semibold">Observação
                                    <textarea class="min-h-20 w-full min-w-0 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="description"><?= e($item['description'] ?? '') ?></textarea>
                                </label>
                                <div class="flex justify-end">
                                    <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar alterações</button>
                                </div>
                            </form>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
