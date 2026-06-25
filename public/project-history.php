<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ?');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();
if (!$project) {
    redirect('/projects.php');
}

$historyStmt = db()->prepare(
    'select h.*, u.name as user_name
     from flow_history h
     left join users u on u.id = h.user_id
     where h.client_project_id = ? and h.company_id = ?
     order by h.created_at desc'
);
$historyStmt->execute([$id, $companyId]);
$rows = $historyStmt->fetchAll();

$pageTitle = 'Histórico - ' . $project['project_name'];
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-4">
    <div class="rounded-lg border border-line bg-white p-4">
        <h2 class="font-bold"><?= e($project['project_name']) ?></h2>
        <p class="mt-1 text-sm text-slate-500"><?= e($project['client_name']) ?> · <?= e(stage_label($project['current_stage'])) ?></p>
    </div>
    <section class="grid gap-3">
        <?php if (!$rows): ?><div class="rounded-lg border border-line bg-white p-4 text-sm text-slate-500">Nenhum histórico encontrado.</div><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article class="rounded-lg border border-line bg-white p-4">
                <strong><?= e($row['action']) ?></strong>
                <p class="mt-1 text-sm text-slate-500">
                    <?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?> · <?= e($row['user_name'] ?: 'Sistema') ?> ·
                    <?= e($row['from_stage'] ? stage_label($row['from_stage']) : 'Origem') ?> → <?= e(stage_label($row['to_stage'])) ?>
                </p>
                <?php if ($row['notes']): ?><p class="mt-2 text-sm"><?= e($row['notes']) ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
