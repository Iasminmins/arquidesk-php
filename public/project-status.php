<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stage = $_POST['stage'] ?? 'PROJETO';
$view = $_POST['view'] ?? 'active';
$newStatus = trim($_POST['status'] ?? '');
$field = status_field_for_stage($stage);
$companyId = (int) $user['company_id'];

$ajax = wants_json();

if (!$field || !can_write_project($user, $stage)) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Sem permissão para alterar este status.'], 403);
    }
    redirect('/projects.php?stage=' . urlencode($stage) . '&view=' . urlencode($view));
}

// Captura valor anterior (para permitir desfazer)
$prevStmt = db()->prepare("select {$field} as val from client_projects where id = ? and company_id = ? limit 1");
$prevStmt->execute([$id, $companyId]);
$oldStatus = $prevStmt->fetchColumn();

if ($oldStatus === false) {
    if ($ajax) {
        json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
    }
    redirect('/projects.php?stage=' . urlencode($stage) . '&view=' . urlencode($view));
}

$stmt = db()->prepare("update client_projects set {$field} = ? where id = ? and company_id = ?");
$stmt->execute([$newStatus, $id, $companyId]);

if ($ajax) {
    json_response([
        'ok' => true,
        'id' => $id,
        'new_status' => $newStatus,
        'old_status' => (string) $oldStatus,
    ]);
}

redirect('/projects.php?stage=' . urlencode($stage) . '&view=' . urlencode($view) . '&ok=1');
