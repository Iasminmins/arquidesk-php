<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$stage = $_GET['stage'] ?? 'PROJETO';
$allowedStages = ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'];
if (!in_array($stage, $allowedStages, true)) {
    $stage = 'PROJETO';
}

$view = $_GET['view'] ?? 'active';
$pageTitle = stage_label($stage);
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
} else {
    $baseSql .= " and p.current_stage = ?";
    $params[] = $stage;
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

$canCreate = can_create_project($user, $stage);
$canEdit = can_write_project($user, $stage);
$canDelete = can_delete_project($user);

function project_status_for_stage(array $project, string $stage): string
{
    $field = status_field_for_stage($stage);
    if ($field && !empty($project[$field])) {
        return $project[$field];
    }

    return match ($stage) {
        'PROJETO' => 'Sondagem',
        'NEGOCIACAO' => 'Detalhamento de venda',
        'CONFERENCIA' => 'Medição',
        'MONTAGEM' => 'Vistoria de montagem',
        'ASSISTENCIA' => 'Aberta',
        default => 'Finalizado',
    };
}

function project_dates_for_stage(array $project, string $stage): array
{
    return match ($stage) {
        'PROJETO' => [['Entrada', $project['entry_date']], ['Apresentação', $project['presentation_date']]],
        'NEGOCIACAO' => [['Entrada', $project['entry_date']], ['Apresentação', $project['presentation_date']], ['Fechamento', $project['closing_date']]],
        'CONFERENCIA' => [['Envio fábrica', $project['sent_to_factory_date']], ['Faturamento', $project['billing_date']]],
        'MONTAGEM' => [['Início montagem', $project['assembly_started_date']], ['Fim montagem', $project['assembly_finished_date']]],
        'ASSISTENCIA' => [['Pedido', $project['order_date']], ['Assistência', $project['assistance_date']]],
        default => [['Finalizado', $project['finished_at']]],
    };
}

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-4">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Operação concluída com sucesso.</div>
    <?php endif; ?>

    <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <form method="get" class="flex flex-1 gap-2">
            <input type="hidden" name="stage" value="<?= e($stage) ?>">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input class="min-h-10 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-ink md:max-w-sm" name="q" value="<?= e($search) ?>" placeholder="Filtrar por cliente, projeto ou projetista">
            <button class="rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Filtrar</button>
        </form>
        <a class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-fog" href="/export.php?type=stage&stage=<?= e($stage) ?>&view=<?= e($view) ?>">Exportar</a>
        <?php if ($canCreate): ?>
            <a class="inline-flex min-h-10 items-center justify-center rounded-md bg-ink px-4 text-sm font-bold text-white" href="/project-form.php?stage=<?= e($stage) ?>"><?= $stage === 'ASSISTENCIA' ? 'Criar assistência' : 'Criar projeto' ?></a>
        <?php endif; ?>
    </div>

    <?php if ($stage !== 'FINALIZADO'): ?>
        <div class="grid gap-2 rounded-lg border border-line bg-white p-2 text-sm font-semibold sm:inline-grid sm:w-fit sm:grid-cols-2">
            <a class="rounded-md px-4 py-2 <?= $view !== 'completed' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?stage=<?= e($stage) ?>&view=active">
                <?= e(stage_label($stage)) ?> em andamento <span class="ml-2 opacity-70"><?= $activeCount ?></span>
            </a>
            <a class="rounded-md px-4 py-2 <?= $view === 'completed' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?stage=<?= e($stage) ?>&view=completed">
                <?= e(stage_label($stage)) ?> finalizados <span class="ml-2 opacity-70"><?= $completedCount ?></span>
            </a>
        </div>
    <?php endif; ?>

    <div class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1080px] text-left text-sm">
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
                            <td class="p-3"><strong><?= e($project['client_name']) ?></strong><span class="block text-xs text-slate-500"><?= e($project['client_phone']) ?></span></td>
                            <td class="p-3"><?= e($project['project_name']) ?></td>
                            <td class="p-3"><?= e($project['designer_name'] ?: '-') ?></td>
                            <td class="p-3">
                                <?php $field = status_field_for_stage($stage); ?>
                                <?php if ($canEdit && $field): ?>
                                    <form method="post" action="/project-status.php">
                                        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                        <input type="hidden" name="stage" value="<?= e($stage) ?>">
                                        <input type="hidden" name="view" value="<?= e($view) ?>">
                                        <select class="min-h-10 w-full min-w-44 rounded-md border border-line bg-white px-2 text-sm outline-none focus:border-ink" name="status" onchange="this.form.submit()">
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
                                <div class="flex flex-wrap justify-end gap-2">
                                    <?php if ($canEdit): ?><a class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" href="/project-form.php?id=<?= (int) $project['id'] ?>">Editar</a><?php endif; ?>
                                    <a class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" href="/project-history.php?id=<?= (int) $project['id'] ?>">Histórico</a>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="/project-delete.php" onsubmit="return confirm('Excluir este projeto?')">
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="rounded-md bg-red-600 px-3 py-2 text-xs font-bold text-white" type="submit">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($project['current_stage'] === $stage && next_stage($project['current_stage']) && $user['role'] !== 'CONFERENTE'): ?>
                                        <form method="post" action="/project-move.php">
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="rounded-md bg-ink px-3 py-2 text-xs font-bold text-white" type="submit">Enviar</button>
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
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
