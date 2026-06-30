<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_csrf();

if ($user['role'] === 'CONFERENTE') {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);
$fromStage = $_POST['from_stage'] ?? '';
$toStage = $_POST['to_stage'] ?? '';
$allowedStages = ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'];

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

$isValidStage = in_array($fromStage, $allowedStages, true) && in_array($toStage, $allowedStages, true);
$isAdjacentMove = abs(stage_order($fromStage) - stage_order($toStage)) === 1;
if (!$isValidStage || !$isAdjacentMove || $project['current_stage'] !== $toStage) {
    json_response(['ok' => false, 'error' => 'Não é mais possível desfazer esta movimentação.'], 409);
}

$finishedAt = $fromStage === 'FINALIZADO' ? date('Y-m-d H:i:s') : null;
$update = db()->prepare('update client_projects set current_stage = ?, finished_at = ? where id = ? and company_id = ?');
$update->execute([$fromStage, $finishedAt, $id, $companyId]);

$history = db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?, ?, ?, ?, ?, ?)');
$history->execute([$companyId, $id, $toStage, $fromStage, 'Movimentação desfeita', (int) $user['id']]);

json_response([
    'ok' => true,
    'id' => $id,
    'current_stage' => $fromStage,
]);
