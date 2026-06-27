<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_csrf();

if ($user['role'] === 'CONFERENTE') {
    if (wants_json()) {
        json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
    }
    redirect('/');
}
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);
$ajax = wants_json();

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
    }
    redirect('/');
}

$validationError = advance_stage_validation($project);
if ($validationError) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => $validationError], 422);
    }
    redirect('/projects.php?stage=' . urlencode($project['current_stage']) . '&error=' . urlencode($validationError));
}

$fromStage = $project['current_stage'];
$toStage = next_stage($fromStage);
if (!$toStage) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Não há próxima etapa.'], 422);
    }
    redirect('/projects.php?stage=' . urlencode($fromStage));
}

$finishedAt = $toStage === 'FINALIZADO' ? date('Y-m-d H:i:s') : null;

$update = db()->prepare('update client_projects set current_stage = ?, finished_at = coalesce(?, finished_at) where id = ? and company_id = ?');
$update->execute([$toStage, $finishedAt, $id, $companyId]);

$stageMessages = [
    'PROJETO' => 'Projeto enviado para Negociação.',
    'NEGOCIACAO' => 'Negociação enviada para Conferência.',
    'CONFERENCIA' => 'Conferência enviada para Montagem.',
    'MONTAGEM' => 'Montagem enviada para Assistência.',
    'ASSISTENCIA' => 'Assistência enviada para Finalizados.',
];
$msg = $stageMessages[$fromStage] ?? 'Movimentação concluída.';

$history = db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?, ?, ?, ?, ?, ?)');
$history->execute([$companyId, $id, $fromStage, $toStage, 'Enviado para ' . stage_label($toStage), (int) $user['id']]);

if ($ajax) {
    json_response([
        'ok' => true,
        'id' => $id,
        'from_stage' => $fromStage,
        'to_stage' => $toStage,
        'message' => $msg,
    ]);
}

redirect('/projects.php?stage=' . urlencode($toStage) . '&ok=1&msg=' . urlencode($msg));
