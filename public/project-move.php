<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/');
}

$toStage = next_stage($project['current_stage']);
if (!$toStage) {
    redirect('/projects.php?stage=' . urlencode($project['current_stage']));
}

$finishedAt = $toStage === 'FINALIZADO' ? date('Y-m-d H:i:s') : null;

$update = db()->prepare('update client_projects set current_stage = ?, finished_at = coalesce(?, finished_at) where id = ? and company_id = ?');
$update->execute([$toStage, $finishedAt, $id, $companyId]);

$history = db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?, ?, ?, ?, ?, ?)');
$history->execute([$companyId, $id, $project['current_stage'], $toStage, 'Projeto avancado', (int) $user['id']]);

redirect('/projects.php?stage=' . urlencode($toStage) . '&ok=1');
