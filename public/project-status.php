<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$id = (int) ($_POST['id'] ?? 0);
$stage = $_POST['stage'] ?? 'PROJETO';
$view = $_POST['view'] ?? 'active';
$field = status_field_for_stage($stage);

if ($field && can_write_project($user, $stage)) {
    $stmt = db()->prepare("update client_projects set {$field} = ? where id = ? and company_id = ?");
    $stmt->execute([trim($_POST['status'] ?? ''), $id, (int) $user['company_id']]);
}

redirect('/projects.php?stage=' . urlencode($stage) . '&view=' . urlencode($view) . '&ok=1');
