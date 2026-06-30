<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
$layout = $_GET['layout'] ?? ($_COOKIE['projects_layout'] ?? 'kanban');
if (!in_array($layout, ['kanban', 'table'], true)) {
    $layout = 'kanban';
}
setcookie('projects_layout', $layout, time() + 31536000, '/');
$stage = $_GET['stage'] ?? 'PROJETO';
$allowedStages = ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'];
if (!in_array($stage, $allowedStages, true)) {
    $stage = 'PROJETO';
}

$view = $_GET['view'] ?? 'active';
$pageTitle = $layout === 'kanban' ? 'Projetos' : stage_label($stage);
$primaryColor = $user['primary_color'] ?? '#15201d';
$companyId = (int) $user['company_id'];
$search = trim($_GET['q'] ?? '');
$currentOrder = stage_order($stage);

$baseSql = "select p.*, u.name as designer_name
        from client_projects p
        left join users u on u.id = p.designer_id
        where p.company_id = ?";
$params = [$companyId];

if ($user['role'] === 'PROJETISTA') {
    $baseSql .= " and p.designer_id = ?";
    $params[] = (int) $user['id'];
}

if ($stage === 'FINALIZADO') {
    $baseSql .= " and p.current_stage = 'FINALIZADO'";
} elseif ($view === 'completed') {
    $later = array_values(array_filter($allowedStages, fn($item) => stage_order($item) > $currentOrder));
    $placeholders = implode(',', array_fill(0, count($later), '?'));
    $baseSql .= " and p.current_stage in ({$placeholders})";
    array_push($params, ...$later);
} elseif ($view === 'desistidas' && $stage === 'NEGOCIACAO') {
    $baseSql .= " and p.current_stage = 'NEGOCIACAO' and p.negotiation_status = 'Desistida'";
} else {
    $baseSql .= " and p.current_stage = ?";
    $params[] = $stage;
    if ($stage === 'NEGOCIACAO') {
        $baseSql .= " and (p.negotiation_status IS NULL or p.negotiation_status != 'Desistida')";
    }
}

if ($search !== '') {
    $baseSql .= " and (p.client_name like ? or p.project_name like ? or u.name like ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$baseSql .= " order by p.updated_at desc, p.created_at desc";
$stmt = db()->prepare($baseSql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

$countSql = "select current_stage, count(*) total from client_projects where company_id = ?";
$countParams = [$companyId];
if ($user['role'] === 'PROJETISTA') {
    $countSql .= " and designer_id = ?";
    $countParams[] = (int) $user['id'];
}
$countSql .= " group by current_stage";
$countStmt = db()->prepare($countSql);
$countStmt->execute($countParams);
$counts = [];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['current_stage']] = (int) $row['total'];
}
$activeCount = $counts[$stage] ?? 0;
$completedCount = 0;
foreach ($counts as $itemStage => $count) {
    if ($stage !== 'FINALIZADO' && stage_order($itemStage) > $currentOrder) {
        $completedCount += $count;
    }
}

// Count desistidas for NEGOCIACAO
$desistidasCount = 0;
if ($stage === 'NEGOCIACAO') {
    $dSql = "select count(*) from client_projects where company_id = ? and current_stage = 'NEGOCIACAO' and negotiation_status = 'Desistida'";
    $dParams = [$companyId];
    if ($user['role'] === 'PROJETISTA') { $dSql .= " and designer_id = ?"; $dParams[] = (int) $user['id']; }
    $dStmt = db()->prepare($dSql);
    $dStmt->execute($dParams);
    $desistidasCount = (int) $dStmt->fetchColumn();
    // Adjust active count to exclude desistidas
    $activeCount = max(0, $activeCount - $desistidasCount);
}

$canCreate = can_create_project($user, $stage);
$canEdit = can_write_project($user, $stage);
$canDelete = can_delete_project($user);
$canDragKanban = $user['role'] !== 'CONFERENTE';

$kanbanByStage = array_fill_keys($allowedStages, []);
if ($layout === 'kanban') {
    $kanbanSql = "select p.*, u.name as designer_name
            from client_projects p
            left join users u on u.id = p.designer_id
            where p.company_id = ?";
    $kanbanParams = [$companyId];
    if ($user['role'] === 'PROJETISTA') {
        $kanbanSql .= ' and p.designer_id = ?';
        $kanbanParams[] = (int) $user['id'];
    }
    $kanbanSql .= " and (p.negotiation_status is null or p.negotiation_status != 'Desistida')";
    if ($search !== '') {
        $kanbanSql .= ' and (p.client_name like ? or p.project_name like ? or u.name like ?)';
        $term = "%{$search}%";
        $kanbanParams[] = $term;
        $kanbanParams[] = $term;
        $kanbanParams[] = $term;
    }
    $kanbanSql .= ' order by p.updated_at desc, p.created_at desc';
    $kanbanStmt = db()->prepare($kanbanSql);
    $kanbanStmt->execute($kanbanParams);
    foreach ($kanbanStmt->fetchAll() as $row) {
        $rowStage = $row['current_stage'];
        if (isset($kanbanByStage[$rowStage])) {
            $kanbanByStage[$rowStage][] = $row;
        }
    }
}

function projects_query_args(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);

    return $query !== '' ? '?' . $query : '';
}

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-4">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($_GET['msg'] ?? 'Operação concluída com sucesso.') ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:flex-1">
            <form method="get" class="flex flex-1 gap-2">
                <?php if ($layout === 'table'): ?>
                    <input type="hidden" name="stage" value="<?= e($stage) ?>">
                    <input type="hidden" name="view" value="<?= e($view) ?>">
                    <input type="hidden" name="layout" value="table">
                <?php else: ?>
                    <input type="hidden" name="layout" value="kanban">
                <?php endif; ?>
                <input class="min-h-10 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-ink md:max-w-sm" name="q" value="<?= e($search) ?>" placeholder="Filtrar por cliente, projeto ou projetista">
                <button class="rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Filtrar</button>
            </form>
            <div class="inline-grid w-fit grid-cols-2 rounded-md border border-line bg-fog p-1 text-sm font-semibold">
                <a class="rounded px-3 py-2 text-center <?= $layout === 'kanban' ? 'bg-white text-ink shadow-sm' : 'text-slate-500 hover:text-ink' ?>" href="/projects.php<?= e(projects_query_args(['layout' => 'kanban', 'stage' => null, 'view' => null])) ?>">Kanban</a>
                <a class="rounded px-3 py-2 text-center <?= $layout === 'table' ? 'bg-white text-ink shadow-sm' : 'text-slate-500 hover:text-ink' ?>" href="/projects.php<?= e(projects_query_args(['layout' => 'table', 'stage' => $stage, 'view' => $view ?: 'active'])) ?>">Tabela</a>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-fog" href="<?= $layout === 'kanban' ? '/export.php?type=projects' : '/export.php?type=stage&stage=' . e($stage) . '&view=' . e($view) ?>">Exportar</a>
            <?php if ($layout === 'kanban' ? (can_create_project($user, 'PROJETO') || can_create_project($user, 'ASSISTENCIA')) : $canCreate): ?>
                <a class="inline-flex min-h-10 items-center justify-center rounded-md bg-ink px-4 text-sm font-bold text-white" href="/project-form.php?stage=<?= e($layout === 'kanban' ? 'PROJETO' : $stage) ?>"><?= ($layout !== 'kanban' && $stage === 'ASSISTENCIA') ? 'Criar assistência' : 'Criar projeto' ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($layout === 'kanban'): ?>
        <div id="kanban-scroll" class="cursor-grab overflow-x-auto pb-2 active:cursor-grabbing">
            <div class="flex min-w-max gap-3">
                <?php foreach ($allowedStages as $columnStage): ?>
                    <?php
                    $columnProjects = $kanbanByStage[$columnStage] ?? [];
                    $columnCount = count($columnProjects);
                    $highlight = $stage === $columnStage;
                    ?>
                    <section id="col-<?= e($columnStage) ?>" class="flex w-72 shrink-0 flex-col rounded-lg bg-white shadow-sm <?= $highlight ? 'border-2' : 'border border-line' ?>" <?= $highlight ? 'style="border-color:' . e($primaryColor) . '"' : '' ?>>
                        <header class="flex items-center justify-between border-b border-line px-3 py-3" style="background: color-mix(in srgb, <?= e($primaryColor) ?> 8%, white)">
                            <div>
                                <h2 class="text-sm font-bold"><?= e(stage_label($columnStage)) ?></h2>
                                <p class="text-xs text-slate-500"><?= $columnCount ?> <?= $columnCount === 1 ? 'projeto' : 'projetos' ?></p>
                            </div>
                            <span class="grid h-8 min-w-8 place-items-center rounded-full px-2 text-xs font-bold text-white" style="background:<?= e($primaryColor) ?>"><?= $columnCount ?></span>
                        </header>
                        <div class="js-kanban-column flex max-h-[calc(100vh-280px)] min-h-40 flex-col gap-2 overflow-y-auto p-2 transition"
                            data-kanban-column="<?= e($columnStage) ?>"
                            data-stage-label="<?= e(stage_label($columnStage)) ?>">
                            <?php if (!$columnProjects): ?>
                                <div class="rounded-md border border-dashed border-line bg-fog p-4 text-center text-xs text-slate-500" data-empty-column="1">Nenhum projeto nesta etapa.</div>
                            <?php endif; ?>
                            <?php foreach ($columnProjects as $project): ?>
                                <?php
                                $cardStage = $project['current_stage'];
                                $daysInStage = project_days_in_stage($project);
                                $isStale = project_is_stale($project);
                                $statusLabel = project_status_for_stage($project, $cardStage);
                                ?>
                                <button type="button"
                                    class="js-kanban-card w-full <?= $canDragKanban ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer' ?> rounded-md border border-line bg-white p-3 text-left shadow-sm transition hover:border-ink/30 hover:shadow-md"
                                    draggable="<?= $canDragKanban ? 'true' : 'false' ?>"
                                    data-id="<?= (int) $project['id'] ?>"
                                    data-stage="<?= e($cardStage) ?>"
                                    data-client="<?= e($project['client_name']) ?>">
                                    <div class="flex items-start justify-between gap-2">
                                        <strong class="text-sm leading-snug"><?= e($project['client_name']) ?></strong>
                                        <?php if ($isStale): ?>
                                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800" title="Parado há <?= $daysInStage ?> dias">!</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-600"><?= e($project['project_name']) ?></p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <span class="rounded-full bg-fog px-2 py-0.5 text-[10px] font-semibold text-slate-600"><?= e($statusLabel) ?></span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $isStale ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600' ?>"><?= $daysInStage ?>d</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-slate-500">
                                        <span class="truncate"><?= e($project['designer_name'] ?: 'Sem projetista') ?></span>
                                        <?php if ((float) ($project['closed_value'] ?? 0) > 0): ?>
                                            <span class="shrink-0 font-semibold text-emerald-800"><?= money_br($project['closed_value']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>

    <?php if ($stage !== 'FINALIZADO'): ?>
        <div class="grid gap-2 rounded-lg border border-line bg-white p-2 text-sm font-semibold sm:inline-grid sm:w-fit <?= $stage === 'NEGOCIACAO' ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?>">
            <a class="rounded-md px-4 py-2 <?= $view === 'active' || $view === '' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?layout=table&stage=<?= e($stage) ?>&view=active">
                <?= e(stage_label($stage)) ?> em andamento <span class="ml-2 opacity-70"><?= $activeCount ?></span>
            </a>
            <a class="rounded-md px-4 py-2 <?= $view === 'completed' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?layout=table&stage=<?= e($stage) ?>&view=completed">
                <?= e(stage_label($stage)) ?> finalizados <span class="ml-2 opacity-70"><?= $completedCount ?></span>
            </a>
            <?php if ($stage === 'NEGOCIACAO'): ?>
            <a class="rounded-md px-4 py-2 <?= $view === 'desistidas' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?layout=table&stage=NEGOCIACAO&view=desistidas">
                Desistidas <span class="ml-2 opacity-70"><?= $desistidasCount ?></span>
            </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Projeto</th>
                        <th class="p-3">Projetista</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Datas</th>
                        <th class="p-3">Valor fechado</th>
                        <th class="p-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$projects): ?>
                        <tr><td colspan="7" class="p-6 text-center text-slate-500">Nenhum registro em <?= e(stage_label($stage)) ?>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($projects as $project): ?>
                        <tr class="border-t border-line align-top">
                            <td class="p-3"><strong><?= e($project['client_name']) ?></strong><span class="flex items-center gap-1 text-xs text-slate-500"><?= e($project['client_phone']) ?> <?= whatsapp_link($project['client_phone'] ?? '') ?></span></td>
                            <td class="p-3"><?= e($project['project_name']) ?></td>
                            <td class="p-3"><?= e($project['designer_name'] ?: '-') ?></td>
                            <td class="p-3">
                                <?php $field = status_field_for_stage($stage); ?>
                                <?php if ($canEdit && $field): ?>
                                    <form method="post" action="/project-status.php" class="js-status-form" data-id="<?= (int) $project['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                        <input type="hidden" name="stage" value="<?= e($stage) ?>">
                                        <input type="hidden" name="view" value="<?= e($view) ?>">
                                        <select class="min-h-10 w-full min-w-44 rounded-md border border-line bg-white px-2 text-sm outline-none focus:border-ink js-status-select" name="status" data-prev="<?= e(project_status_for_stage($project, $stage)) ?>">
                                            <?php foreach (status_options($stage) as $option): ?>
                                                <option value="<?= e($option) ?>" <?= project_status_for_stage($project, $stage) === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <?= e(project_status_for_stage($project, $stage)) ?>
                                <?php endif; ?>
                                <?php if ($project['current_stage'] !== $stage): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Hoje em <?= e(stage_label($project['current_stage'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-xs text-slate-600">
                                <?php foreach (project_dates_for_stage($project, $stage) as [$label, $value]): ?>
                                    <span class="block"><?= e($label) ?>: <?= e(date_br($value)) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="p-3"><?= money_br($project['closed_value'] ?? 0) ?></td>
                            <td class="p-3">
                                <div class="flex justify-end gap-1.5">
                                    <?php if ($canEdit): ?><a class="grid h-9 w-9 place-items-center rounded-md border border-line hover:bg-fog" href="/project-form.php?id=<?= (int) $project['id'] ?>" title="Editar"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></a><?php endif; ?>
                                    <a class="grid h-9 w-9 place-items-center rounded-md border border-line hover:bg-fog" href="/project-history.php?id=<?= (int) $project['id'] ?>" title="Histórico"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></a>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="/project-delete.php" class="js-delete-form" data-id="<?= (int) $project['id'] ?>" data-client="<?= e($project['client_name']) ?>" data-project="<?= e($project['project_name']) ?>" onsubmit="return confirm('Excluir este projeto?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="grid h-9 w-9 place-items-center rounded-md border border-red-200 text-red-600 hover:bg-red-50" type="submit" title="Excluir"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($stage === 'NEGOCIACAO' && $project['current_stage'] === 'NEGOCIACAO' && ($project['negotiation_status'] ?? '') !== 'Desistida' && $user['role'] !== 'CONFERENTE'): ?>
                                        <form method="post" action="/project-desistir.php" class="js-action-form" data-action="desistir" data-id="<?= (int) $project['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50" type="submit">Desistir</button>
                                        </form>
                                        <form method="post" action="/project-to-future.php" class="js-action-form" data-action="futuro" data-id="<?= (int) $project['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" type="submit">Futuro</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($stage === 'NEGOCIACAO' && ($project['negotiation_status'] ?? '') === 'Desistida' && $user['role'] !== 'CONFERENTE'): ?>
                                        <form method="post" action="/project-reativar.php" class="js-action-form" data-action="reativar" data-id="<?= (int) $project['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="inline-flex h-9 items-center whitespace-nowrap rounded-md bg-ink px-3 text-xs font-bold text-white" type="submit">Voltar para Negociação</button>
                                        </form>
                                    <?php elseif ($project['current_stage'] === $stage && next_stage($project['current_stage']) && $user['role'] !== 'CONFERENTE' && ($project['negotiation_status'] ?? '') !== 'Desistida'): ?>
                                        <?php $nextLabel = stage_label(next_stage($project['current_stage'])); ?>
                                        <form method="post" action="/project-move.php" class="js-move-form" data-id="<?= (int) $project['id'] ?>" data-from="<?= e($project['current_stage']) ?>" data-client="<?= e($project['client_name']) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="inline-flex h-9 items-center gap-1.5 whitespace-nowrap rounded-md bg-ink px-3 text-xs font-bold text-white" type="submit">Enviar para <?= e($nextLabel) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</section>

<div id="project-drawer" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div id="project-drawer-backdrop" class="absolute inset-0 bg-ink/40"></div>
    <aside class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-line bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-line px-4 py-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Detalhes do projeto</p>
                <h2 id="drawer-title" class="truncate text-lg font-bold">Carregando...</h2>
            </div>
            <button type="button" id="drawer-close" class="grid h-10 w-10 place-items-center rounded-md hover:bg-fog" aria-label="Fechar">✕</button>
        </div>
        <div id="drawer-body" class="flex-1 overflow-y-auto p-4">
            <div class="grid gap-3 text-sm text-slate-500">Carregando...</div>
        </div>
        <div id="drawer-actions" class="border-t border-line bg-fog/50 p-4"></div>
    </aside>
</div>

<!-- Container de notificações (toast) -->
<div id="toast-root" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script>
(function () {
    const csrf = <?= json_encode(csrf_token()) ?>;
    const toastRoot = document.getElementById('toast-root');

    function showToast(message, undoCallback) {
        const el = document.createElement('div');
        el.className = 'flex items-center gap-3 rounded-lg bg-ink px-4 py-3 text-sm text-white shadow-lg';
        el.style.minWidth = '260px';

        const text = document.createElement('span');
        text.className = 'flex-1';
        text.textContent = message;
        el.appendChild(text);

        let timer = null;
        if (undoCallback) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'shrink-0 rounded-md bg-white/15 px-3 py-1 font-bold hover:bg-white/25';
            btn.textContent = 'Desfazer';
            btn.addEventListener('click', function () {
                if (timer) clearTimeout(timer);
                el.remove();
                undoCallback();
            });
            el.appendChild(btn);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'shrink-0 text-white/60 hover:text-white';
        close.textContent = '✕';
        close.addEventListener('click', function () { if (timer) clearTimeout(timer); el.remove(); });
        el.appendChild(close);

        toastRoot.appendChild(el);
        timer = setTimeout(function () { el.remove(); }, 6000);
    }

    async function postForm(url, data) {
        const body = new URLSearchParams(data);
        body.set('csrf_token', csrf);
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        });
        return resp.json();
    }

    const drawer = document.getElementById('project-drawer');
    const drawerBackdrop = document.getElementById('project-drawer-backdrop');
    const drawerClose = document.getElementById('drawer-close');
    const drawerTitle = document.getElementById('drawer-title');
    const drawerBody = document.getElementById('drawer-body');
    const drawerActions = document.getElementById('drawer-actions');
    let activeDrawerId = 0;

    function findProjectRow(id) {
        return document.querySelector('.js-status-form[data-id="' + id + '"]')?.closest('tr') || null;
    }

    function findKanbanCard(id) {
        return document.querySelector('.js-kanban-card[data-id="' + id + '"]');
    }

    function updateKanbanColumnState(column) {
        if (!column) return;
        const cards = column.querySelectorAll('.js-kanban-card');
        const empty = column.querySelector('[data-empty-column]');
        if (!cards.length && !empty) {
            const placeholder = document.createElement('div');
            placeholder.className = 'rounded-md border border-dashed border-line bg-fog p-4 text-center text-xs text-slate-500';
            placeholder.dataset.emptyColumn = '1';
            placeholder.textContent = 'Nenhum projeto nesta etapa.';
            column.appendChild(placeholder);
        }
        if (cards.length && empty) {
            empty.remove();
        }

        const section = column.closest('section');
        const countText = section?.querySelector('header p');
        const countBadge = section?.querySelector('header span');
        const label = cards.length === 1 ? 'projeto' : 'projetos';
        if (countText) countText.textContent = cards.length + ' ' + label;
        if (countBadge) countBadge.textContent = cards.length;
    }

    function moveKanbanCard(card, fromStage, toStage, prepend = true) {
        if (!card) return;
        const origin = document.querySelector('[data-kanban-column="' + fromStage + '"]');
        const target = document.querySelector('[data-kanban-column="' + toStage + '"]');
        if (target) {
            const empty = target.querySelector('[data-empty-column]');
            if (empty) empty.remove();
            if (prepend) {
                target.prepend(card);
            } else {
                target.appendChild(card);
            }
            card.dataset.stage = toStage;
            updateKanbanColumnState(origin);
            updateKanbanColumnState(target);
        } else {
            card.remove();
            updateKanbanColumnState(origin);
        }
    }

    function fadeRemoveElement(el) {
        if (!el) return;
        el.style.transition = 'opacity .3s';
        el.style.opacity = '0.4';
        setTimeout(function () {
            if (el.style.opacity === '0.4') el.remove();
        }, 6200);
    }

    async function handleProjectMove(id, fromStage, undoRow, undoCard, toStage = null) {
        const payload = { id };
        if (toStage) payload.to_stage = toStage;
        const res = await postForm('/project-move.php', payload);
        if (!res.ok) {
            showToast(res.error || 'Não foi possível mover.', null);
            return null;
        }

        fadeRemoveElement(undoRow);
        if (undoCard && res.to_stage) {
            moveKanbanCard(undoCard, res.from_stage, res.to_stage);
        } else if (undoCard) {
            fadeRemoveElement(undoCard);
        }

        showToast(res.message || 'Movido com sucesso.', async function () {
            const undo = await postForm('/project-move-undo.php', {
                id, from_stage: res.from_stage, to_stage: res.to_stage,
            });
            if (undo.ok) {
                if (undoRow) undoRow.style.opacity = '1';
                if (undoCard && res.from_stage) {
                    moveKanbanCard(undoCard, res.to_stage, res.from_stage);
                }
                showToast('Movimentação desfeita.', null);
                if (activeDrawerId === id) openDrawer(id);
            } else {
                showToast(undo.error || 'Não foi possível desfazer.', null);
            }
        });

        if (activeDrawerId === id) closeDrawer();
        return res;
    }

    function renderDrawer(data) {
        const p = data.project;
        drawerTitle.textContent = p.client_name + ' · ' + p.project_name;

        let html = '';
        html += '<div class="flex flex-wrap gap-2">';
        html += '<span class="rounded-full bg-ink px-3 py-1 text-xs font-bold text-white">' + escapeHtml(p.current_stage_label) + '</span>';
        if (p.is_stale) {
            html += '<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Parado há ' + p.days_in_stage + ' dias</span>';
        } else {
            html += '<span class="rounded-full bg-fog px-3 py-1 text-xs font-semibold text-slate-600">' + p.days_in_stage + ' dias nesta etapa</span>';
        }
        html += '</div>';

        html += '<div class="mt-4 grid gap-3 rounded-lg border border-line bg-fog p-3 text-sm">';
        html += '<div class="flex items-center justify-between gap-2"><span class="text-slate-500">Projetista</span><strong>' + escapeHtml(p.designer_name) + '</strong></div>';
        html += '<div class="flex items-center justify-between gap-2"><span class="text-slate-500">Valor fechado</span><strong>' + escapeHtml(p.closed_value_label) + '</strong></div>';
        if (p.client_phone) {
            html += '<div class="flex items-center justify-between gap-2"><span class="text-slate-500">Telefone</span><span>' + escapeHtml(p.client_phone) + '</span></div>';
        }
        html += '</div>';

        if (p.can_edit && p.status_field && p.status_options.length) {
            html += '<div class="mt-4"><label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>';
            html += '<select class="js-drawer-status min-h-10 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-ink" data-id="' + p.id + '" data-stage="' + escapeHtml(p.current_stage) + '" data-prev="' + escapeHtml(p.status) + '">';
            p.status_options.forEach(function (opt) {
                html += '<option value="' + escapeHtml(opt) + '"' + (opt === p.status ? ' selected' : '') + '>' + escapeHtml(opt) + '</option>';
            });
            html += '</select></div>';
        } else {
            html += '<div class="mt-4 rounded-lg border border-line p-3 text-sm"><span class="text-slate-500">Status</span><strong class="mt-1 block">' + escapeHtml(p.status) + '</strong></div>';
        }

        html += '<div class="mt-4"><h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Datas</h3><div class="mt-2 grid gap-2">';
        p.dates.forEach(function (item) {
            html += '<div class="flex items-center justify-between rounded-md border border-line bg-white px-3 py-2 text-sm"><span class="text-slate-500">' + escapeHtml(item.label) + '</span><span>' + escapeHtml(item.value) + '</span></div>';
        });
        html += '</div></div>';

        if (p.notes) {
            html += '<div class="mt-4 rounded-lg border border-line bg-white p-3 text-sm"><span class="text-slate-500">Observações</span><p class="mt-1">' + escapeHtml(p.notes) + '</p></div>';
        }

        // ---- Arquivos do projeto ----
        html += '<div class="mt-4">';
        html += '<h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Arquivos</h3>';
        html += '<div id="js-drawer-files" class="mt-2"></div>';
        if (p.can_edit) {
            html += '<div class="mt-2 flex items-center gap-2">';
            html += '<select id="js-file-cat" class="min-h-9 flex-1 rounded-md border border-line bg-white px-2 text-xs">';
            html += '<option value="GERAL">Geral</option><option value="PLANTA">Planta</option><option value="CONTRATO">Contrato</option><option value="MEDICAO">MediÃ§Ã£o</option><option value="MONTAGEM">Montagem</option><option value="FOTO">Foto</option><option value="OUTRO">Outro</option>';
            html += '</select>';
            html += '<label class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-md bg-ink px-3 text-xs font-bold text-white hover:opacity-90"><input type="file" id="js-file-input" class="sr-only" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" multiple>+ Arquivo</label>';
            html += '</div>';
        }
        html += '</div>';

        html += '<div class="mt-4"><h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Histórico recente</h3><div class="mt-2 grid gap-2">';
        if (!data.history.length) {
            html += '<div class="rounded-md border border-line p-3 text-sm text-slate-500">Nenhum histórico ainda.</div>';
        }
        data.history.forEach(function (row) {
            html += '<article class="rounded-md border border-line bg-white p-3 text-sm">';
            html += '<strong>' + escapeHtml(row.action) + '</strong>';
            html += '<p class="mt-1 text-xs text-slate-500">' + escapeHtml(row.created_at) + ' · ' + escapeHtml(row.user_name);
            if (row.from_stage) html += ' · ' + escapeHtml(row.from_stage) + ' → ' + escapeHtml(row.to_stage);
            html += '</p>';
            if (row.notes) html += '<p class="mt-1">' + escapeHtml(row.notes) + '</p>';
            html += '</article>';
        });
        html += '</div></div>';

        drawerBody.innerHTML = html;
        loadDrawerFiles(p.id);
        const fileInput = drawerBody.querySelector('#js-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                uploadFiles(Array.from(fileInput.files), p.id);
                fileInput.value = '';
            });
        }

        const statusSelect = drawerBody.querySelector('.js-drawer-status');
        if (statusSelect) {
            statusSelect.addEventListener('change', async function () {
                const id = statusSelect.dataset.id;
                const stage = statusSelect.dataset.stage;
                const oldStatus = statusSelect.dataset.prev;
                const newStatus = statusSelect.value;
                statusSelect.disabled = true;
                try {
                    const res = await postForm('/project-status.php', { id, stage, view: 'active', status: newStatus });
                    if (!res.ok) {
                        statusSelect.value = oldStatus;
                        showToast(res.error || 'Não foi possível salvar.', null);
                    } else {
                        statusSelect.dataset.prev = newStatus;
                        showToast('Status salvo.', null);
                        const card = findKanbanCard(id);
                        if (card) {
                            const badge = card.querySelector('.rounded-full.bg-fog');
                            if (badge) badge.textContent = newStatus;
                        }
                    }
                } catch (e) {
                    statusSelect.value = oldStatus;
                    showToast('Erro de conexão.', null);
                } finally {
                    statusSelect.disabled = false;
                }
            });
        }

        let actions = '<div class="grid gap-2 sm:grid-cols-2">';
        if (p.whatsapp_url) {
            actions += '<a href="' + escapeHtml(p.whatsapp_url) + '" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-700 hover:bg-emerald-100">WhatsApp</a>';
        }
        if (p.can_edit) {
            actions += '<a href="/project-form.php?id=' + p.id + '" class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-white">Editar</a>';
        }
        actions += '<a href="/project-history.php?id=' + p.id + '" class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-white">Histórico completo</a>';
        actions += '<a href="/project-files.php?id=' + p.id + '" class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-white">Arquivos</a>';
        actions += '</div>';

        if (p.can_move && p.next_stage_label) {
            actions += '<button type="button" class="js-drawer-move mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" data-id="' + p.id + '" data-from="' + escapeHtml(p.current_stage) + '">Enviar para ' + escapeHtml(p.next_stage_label) + '</button>';
        } else if (p.move_error) {
            actions += '<p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">' + escapeHtml(p.move_error) + '</p>';
        }

        drawerActions.innerHTML = actions;

        const moveBtn = drawerActions.querySelector('.js-drawer-move');
        if (moveBtn) {
            moveBtn.addEventListener('click', async function () {
                moveBtn.disabled = true;
                const id = moveBtn.dataset.id;
                const fromStage = moveBtn.dataset.from;
                await handleProjectMove(id, fromStage, findProjectRow(id), findKanbanCard(id));
                moveBtn.disabled = false;
            });
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function loadDrawerFiles(projectId) {
        const container = document.getElementById('js-drawer-files');
        if (!container) return;
        container.innerHTML = '<p class="text-xs text-slate-400">Carregando...</p>';
        try {
            const resp = await fetch('/project-file-list.php?project_id=' + encodeURIComponent(projectId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            const data = await resp.json();
            renderFileList(data.files || [], projectId);
        } catch (e) {
            const c = document.getElementById('js-drawer-files');
            if (c) c.innerHTML = '<p class="text-xs text-red-500">Erro ao carregar arquivos.</p>';
        }
    }

    function renderFileList(files, projectId) {
        const container = document.getElementById('js-drawer-files');
        if (!container) return;
        if (!files.length) { container.innerHTML = '<p class="text-xs text-slate-400 py-1">Nenhum arquivo ainda.</p>'; return; }
        let fHtml = '<div class="grid gap-1.5 mt-1">';
        files.forEach(function (f) {
            fHtml += '<div class="flex items-center gap-2 rounded-md border border-line bg-white p-2 text-xs">';
            fHtml += f.is_image
                ? '<img src="' + escapeHtml(f.url) + '" class="h-10 w-10 shrink-0 rounded object-cover" alt="">'
                : '<span class="grid h-10 w-10 shrink-0 place-items-center rounded bg-fog text-base">ðŸ„</span>';
            fHtml += '<div class="min-w-0 flex-1">';
            fHtml += '<a href="' + escapeHtml(f.url) + '" target="_blank" rel="noopener" class="block truncate font-semibold hover:underline">' + escapeHtml(f.original_name) + '</a>';
            fHtml += '<span class="text-slate-400">' + escapeHtml(f.category_label) + ' Â· ' + escapeHtml(f.file_size_label) + ' Â· ' + escapeHtml(f.created_at) + '</span>';
            fHtml += '</div>';
            fHtml += '<button type="button" class="js-delete-file shrink-0 px-1 text-red-400 hover:text-red-600" data-id="' + f.id + '" data-project="' + projectId + '" aria-label="Excluir">âœ•</button>';
            fHtml += '</div>';
        });
        fHtml += '</div>';
        container.innerHTML = fHtml;
        container.querySelectorAll('.js-delete-file').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (!confirm('Excluir este arquivo?')) return;
                btn.disabled = true;
                const res = await postForm('/project-file-delete.php', { file_id: btn.dataset.id });
                if (res.ok) { loadDrawerFiles(btn.dataset.project); showToast('Arquivo excluÃ­do.', null); }
                else { showToast(res.error || 'Erro ao excluir.', null); btn.disabled = false; }
            });
        });
    }

    async function uploadFiles(files, projectId) {
        if (!files.length) return;
        const cat = document.getElementById('js-file-cat');
        let success = 0;
        for (const file of files) {
            const formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('project_id', projectId);
            formData.append('category', cat ? cat.value : 'GERAL');
            formData.append('file', file);
            try {
                const resp = await fetch('/project-file-upload.php', {
                    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, body: formData,
                });
                const data = await resp.json();
                if (data.ok) { success++; } else { showToast(data.error || 'Erro ao enviar ' + file.name, null); }
            } catch (e) { showToast('Erro de conexÃ£o.', null); }
        }
        if (success > 0) {
            showToast(success + (success === 1 ? ' arquivo enviado.' : ' arquivos enviados.'), null);
            loadDrawerFiles(projectId);
        }
    }

    async function openDrawer(id) {
        activeDrawerId = id;
        drawer.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        drawerTitle.textContent = 'Carregando...';
        drawerBody.innerHTML = '<div class="text-sm text-slate-500">Carregando...</div>';
        drawerActions.innerHTML = '';

        try {
            const resp = await fetch('/project-detail.php?id=' + encodeURIComponent(id), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();
            if (!data.ok) {
                showToast(data.error || 'Não foi possível carregar.', null);
                closeDrawer();
                return;
            }
            renderDrawer(data);
        } catch (e) {
            showToast('Erro de conexão.', null);
            closeDrawer();
        }
    }

    function closeDrawer() {
        activeDrawerId = 0;
        drawer.classList.add('hidden');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    const kanbanStages = <?= json_encode($allowedStages) ?>;
    const kanbanScroll = document.getElementById('kanban-scroll');
    let draggingCard = null;
    let dragStartedAt = 0;

    function stageDistance(fromStage, toStage) {
        return Math.abs(kanbanStages.indexOf(fromStage) - kanbanStages.indexOf(toStage));
    }

    function clearDropState() {
        document.querySelectorAll('.js-kanban-column').forEach(function (column) {
            column.classList.remove('ring-2', 'ring-ink/30', 'bg-emerald-50/40');
        });
    }

    document.querySelectorAll('.js-kanban-card').forEach(function (card) {
        card.addEventListener('dragstart', function (ev) {
            draggingCard = card;
            dragStartedAt = Date.now();
            card.classList.add('opacity-50', 'scale-[0.99]');
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', card.dataset.id);
        });

        card.addEventListener('dragend', function () {
            card.classList.remove('opacity-50', 'scale-[0.99]');
            clearDropState();
            setTimeout(function () {
                draggingCard = null;
            }, 50);
        });

        card.addEventListener('click', function () {
            if (Date.now() - dragStartedAt < 250) return;
            openDrawer(card.dataset.id);
        });
    });

    document.querySelectorAll('.js-kanban-column').forEach(function (column) {
        column.addEventListener('dragover', function (ev) {
            if (!draggingCard) return;
            ev.preventDefault();
            ev.dataTransfer.dropEffect = 'move';
            clearDropState();
            column.classList.add('ring-2', 'ring-ink/30', 'bg-emerald-50/40');
        });

        column.addEventListener('dragleave', function (ev) {
            if (!column.contains(ev.relatedTarget)) {
                column.classList.remove('ring-2', 'ring-ink/30', 'bg-emerald-50/40');
            }
        });

        column.addEventListener('drop', async function (ev) {
            ev.preventDefault();
            clearDropState();
            const card = draggingCard;
            if (!card) return;

            const id = card.dataset.id;
            const fromStage = card.dataset.stage;
            const toStage = column.dataset.kanbanColumn;
            if (!id || !fromStage || !toStage || fromStage === toStage) return;

            if (stageDistance(fromStage, toStage) > 1) {
                showToast('Mova uma etapa por vez para manter o fluxo correto.', null);
                return;
            }

            card.classList.add('pointer-events-none');
            try {
                await handleProjectMove(id, fromStage, null, card, toStage);
            } catch (e) {
                showToast('Erro de conexão.', null);
            } finally {
                card.classList.remove('pointer-events-none');
            }
        });
    });

    if (kanbanScroll) {
        let isPanning = false;
        let panStartX = 0;
        let panStartScroll = 0;
        kanbanScroll.addEventListener('pointerdown', function (ev) {
            if (ev.button !== 0 || ev.target.closest('.js-kanban-card, a, button, input, select, textarea')) return;
            isPanning = true;
            panStartX = ev.clientX;
            panStartScroll = kanbanScroll.scrollLeft;
            kanbanScroll.setPointerCapture(ev.pointerId);
        });
        kanbanScroll.addEventListener('pointermove', function (ev) {
            if (!isPanning) return;
            kanbanScroll.scrollLeft = panStartScroll - (ev.clientX - panStartX);
        });
        function stopPan() {
            isPanning = false;
        }
        kanbanScroll.addEventListener('pointerup', stopPan);
        kanbanScroll.addEventListener('pointercancel', stopPan);
        kanbanScroll.addEventListener('pointerleave', stopPan);
    }

    if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
    if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !drawer.classList.contains('hidden')) closeDrawer();
    });

    if (window.location.hash) {
        const targetColumn = document.querySelector(window.location.hash);
        if (targetColumn) {
            targetColumn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    }

    // ---- Mudança de status ----
    document.querySelectorAll('.js-status-select').forEach(function (select) {
        select.addEventListener('change', async function () {
            const form = select.closest('.js-status-form');
            const id = form.dataset.id;
            const stage = form.querySelector('[name="stage"]').value;
            const view = form.querySelector('[name="view"]').value;
            const newStatus = select.value;
            const oldStatus = select.dataset.prev;
            select.disabled = true;

            try {
                const res = await postForm('/project-status.php', { id, stage, view, status: newStatus });
                if (!res.ok) {
                    select.value = oldStatus;
                    showToast(res.error || 'Não foi possível salvar.', null);
                } else {
                    select.dataset.prev = newStatus;
                    showToast('Status salvo.', async function () {
                        const undo = await postForm('/project-status.php', { id, stage, view, status: oldStatus });
                        if (undo.ok) {
                            select.value = oldStatus;
                            select.dataset.prev = oldStatus;
                            showToast('Alteração desfeita.', null);
                        }
                    });
                }
            } catch (e) {
                select.value = oldStatus;
                showToast('Erro de conexão.', null);
            } finally {
                select.disabled = false;
            }
        });
    });

    // ---- Mover etapa ----
    document.querySelectorAll('.js-move-form').forEach(function (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const id = form.dataset.id;
            const fromStage = form.dataset.from;
            const btn = form.querySelector('button[type="submit"]');
            const row = form.closest('tr');
            const card = findKanbanCard(id);
            if (btn) btn.disabled = true;

            try {
                const res = await handleProjectMove(id, fromStage, row, card);
                if (!res && btn) btn.disabled = false;
            } catch (e) {
                showToast('Erro de conexão.', null);
                if (btn) btn.disabled = false;
            }
        });
    });

    // ---- Desistir / Futuro / Reativar (com desfazer) ----
    const actionConfig = {
        desistir: {
            url: '/project-desistir.php',
            success: 'Marcado como desistida.',
            removeRow: true,
            undo: function (res, id) {
                return postForm('/project-negotiation-undo.php', { id, target_status: res.prev_status || '' });
            },
        },
        futuro: {
            url: '/project-to-future.php',
            success: 'Enviado para Clientes Futuros.',
            removeRow: true,
            undo: function (res, id) {
                return postForm('/project-to-future-undo.php', { id, future_id: res.future_id || 0, prev_status: res.prev_status || '' });
            },
        },
        reativar: {
            url: '/project-reativar.php',
            success: 'Negociação reativada.',
            removeRow: true,
            undo: function (res, id) {
                return postForm('/project-negotiation-undo.php', { id, target_status: 'Desistida' });
            },
        },
    };

    document.querySelectorAll('.js-action-form').forEach(function (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const action = form.dataset.action;
            const cfg = actionConfig[action];
            if (!cfg) return;
            const id = form.dataset.id;
            const btn = form.querySelector('button[type="submit"]');
            const row = form.closest('tr');
            if (btn) btn.disabled = true;

            try {
                const res = await postForm(cfg.url, { id });
                if (!res.ok) {
                    showToast(res.error || 'Não foi possível concluir.', null);
                    if (btn) btn.disabled = false;
                    return;
                }
                if (cfg.removeRow && row) { row.style.transition = 'opacity .3s'; row.style.opacity = '0.4'; }

                showToast(cfg.success, async function () {
                    const undo = await cfg.undo(res, id);
                    if (undo.ok) {
                        if (row) row.style.opacity = '1';
                        showToast('Ação desfeita.', null);
                    } else {
                        showToast(undo.error || 'Não foi possível desfazer.', null);
                    }
                });

                setTimeout(function () {
                    if (row && row.style.opacity === '0.4') row.remove();
                }, 6200);
            } catch (e) {
                showToast('Erro de conexão.', null);
                if (btn) btn.disabled = false;
            }
        });
    });

    // ---- Excluir (modal de confirmação, sem desfazer pois é destrutivo) ----
    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.removeAttribute('onsubmit');
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const client = form.dataset.client || '';
            const projectName = form.dataset.project || '';
            const id = form.dataset.id;
            const row = form.closest('tr');

            const confirmed = await confirmDelete(client, projectName);
            if (!confirmed) return;

            try {
                const res = await postForm('/project-delete.php', { id });
                if (!res.ok) {
                    showToast(res.error || 'Não foi possível excluir.', null);
                    return;
                }
                if (row) { row.style.transition = 'opacity .3s'; row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                showToast('Projeto excluído.', null);
            } catch (e) {
                showToast('Erro de conexão.', null);
            }
        });
    });

    // Modal de confirmação de exclusão
    function confirmDelete(client, projectName) {
        return new Promise(function (resolve) {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4';

            const box = document.createElement('div');
            box.className = 'w-full max-w-sm rounded-lg bg-white p-5 shadow-xl';
            const nome = [client, projectName].filter(Boolean).join(' — ') || 'este projeto';
            box.innerHTML =
                '<h3 class="text-lg font-bold text-ink">Excluir projeto</h3>' +
                '<p class="mt-2 text-sm text-slate-600">Tem certeza que deseja excluir <strong></strong>? Esta ação não pode ser desfeita.</p>' +
                '<div class="mt-5 flex justify-end gap-2">' +
                '<button type="button" class="rounded-md border border-line px-4 py-2 text-sm font-semibold hover:bg-fog" data-cancel>Cancelar</button>' +
                '<button type="button" class="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700" data-ok>Excluir</button>' +
                '</div>';
            box.querySelector('strong').textContent = nome;

            overlay.appendChild(box);
            document.body.appendChild(overlay);

            function close(result) { overlay.remove(); resolve(result); }
            box.querySelector('[data-cancel]').addEventListener('click', function () { close(false); });
            box.querySelector('[data-ok]').addEventListener('click', function () { close(true); });
            overlay.addEventListener('click', function (e) { if (e.target === overlay) close(false); });
        });
    }
})();
</script>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
