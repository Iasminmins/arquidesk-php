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
$allowedStages = ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'];

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
    }
    redirect('/');
}

$fromStage = $project['current_stage'];
$toStage = $_POST['to_stage'] ?? next_stage($fromStage);

if (!$toStage) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Não há próxima etapa.'], 422);
    }
    redirect('/projects.php?stage=' . urlencode($fromStage));
}

if (!in_array($fromStage, $allowedStages, true) || !in_array($toStage, $allowedStages, true)) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Etapa inválida.'], 422);
    }
    redirect('/projects.php?stage=' . urlencode($fromStage));
}

if ($toStage === $fromStage) {
    if ($ajax) {
        json_response([
            'ok' => true,
            'id' => $id,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'message' => 'Projeto já está nesta etapa.',
        ]);
    }
    redirect('/projects.php?stage=' . urlencode($fromStage));
}

$fromOrder = stage_order($fromStage);
$toOrder = stage_order($toStage);
$isForward = $toOrder > $fromOrder;
$isBackward = $toOrder < $fromOrder;

if ($isForward && next_stage($fromStage) !== $toStage) {
    $msg = 'Avance uma etapa por vez para manter o fluxo correto.';
    if ($ajax) {
        json_response(['ok' => false, 'error' => $msg], 422);
    }
    redirect('/projects.php?stage=' . urlencode($fromStage) . '&error=' . urlencode($msg));
}

if ($isBackward && ($fromOrder - $toOrder) > 1) {
    $msg = 'Volte uma etapa por vez para manter o histórico correto.';
    if ($ajax) {
        json_response(['ok' => false, 'error' => $msg], 422);
    }
    redirect('/projects.php?stage=' . urlencode($fromStage) . '&error=' . urlencode($msg));
}

if ($isForward) {
    $validationError = advance_stage_validation($project);
    if ($validationError) {
        if ($ajax) {
            json_response(['ok' => false, 'error' => $validationError], 422);
        }
        redirect('/projects.php?stage=' . urlencode($fromStage) . '&error=' . urlencode($validationError));
    }
}

$finishedAt = $toStage === 'FINALIZADO' ? date('Y-m-d H:i:s') : null;
$update = db()->prepare('update client_projects set current_stage = ?, finished_at = ? where id = ? and company_id = ?');
$update->execute([$toStage, $finishedAt, $id, $companyId]);

$stageMessages = [
    'PROJETO' => 'Projeto enviado para Negociação.',
    'NEGOCIACAO' => 'Negociação enviada para Conferência.',
    'CONFERENCIA' => 'Conferência enviada para Montagem.',
    'MONTAGEM' => 'Montagem enviada para Assistência.',
    'ASSISTENCIA' => 'Assistência enviada para Finalizados.',
];
$msg = $isForward
    ? ($stageMessages[$fromStage] ?? 'Movimentação concluída.')
    : 'Projeto retornado para ' . stage_label($toStage) . '.';

$action = ($isForward ? 'Enviado para ' : 'Retornado para ') . stage_label($toStage);
$history = db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?, ?, ?, ?, ?, ?)');
$history->execute([$companyId, $id, $fromStage, $toStage, $action, (int) $user['id']]);

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
