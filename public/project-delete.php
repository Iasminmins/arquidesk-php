<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
if (!can_delete_project($user)) {
    redirect('/');
}
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('select current_stage from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$stage = $stmt->fetchColumn() ?: 'PROJETO';

$delete = db()->prepare('delete from client_projects where id = ? and company_id = ?');
$delete->execute([$id, $companyId]);

redirect('/projects.php?stage=' . urlencode($stage) . '&ok=1');
