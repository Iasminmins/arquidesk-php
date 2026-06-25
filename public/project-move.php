<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
if ($user['role'] === 'CONFERENTE') {
    redirect('/');
}
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/');
}

$validationError = advance_stage_validation($project);
if ($validationError) {
    redirect('/projects.php?stage=' . urlencode($project['current_stage']) . '&error=' . urlencode($validationError));
}

$toStage = next_stage($project['current_stage']);
if (!$toStage) {
    redirect('/projects.php?stage=' . urlencode($project['current_stage']));
}

$finishedAt = $toStage === 'FINALIZADO' ? date('Y-m-d H:i:s') : null;

$update = db()->prepare('update client_projects set current_stage = ?, finished_at = coalesce(?, finished_at) where id = ? and company_id = ?');
$update->execute([$toStage, $finishedAt, $id, $companyId]);

$stageMessages = [
    'PROJETO' => 'Projeto enviado para Negociação com sucesso.',
    'NEGOCIACAO' => 'Negociação enviada para Conferência com sucesso.',
    'CONFERENCIA' => 'Conferência enviada para Montagem com sucesso.',
    'MONTAGEM' => 'Montagem enviada para Assistência com sucesso.',
    'ASSISTENCIA' => 'Assistência enviada para Finalizados com sucesso.',
];
$msg = $stageMessages[$project['current_stage']] ?? 'Movimentação concluída com sucesso.';

$history = db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?, ?, ?, ?, ?, ?)');
$history->execute([$companyId, $id, $project['current_stage'], $toStage, 'Enviado para ' . stage_label($toStage), (int) $user['id']]);

redirect('/projects.php?stage=' . urlencode($toStage) . '&ok=1&msg=' . urlencode($msg));
