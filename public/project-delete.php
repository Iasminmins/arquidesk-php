<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_csrf();
if (!can_delete_project($user)) {
    if (wants_json()) json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
    redirect('/');
}
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);
$ajax = wants_json();

$stmt = db()->prepare('select current_stage from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$stage = $stmt->fetchColumn() ?: 'PROJETO';

$delete = db()->prepare('delete from client_projects where id = ? and company_id = ?');
$delete->execute([$id, $companyId]);

if ($ajax) {
    json_response(['ok' => true, 'id' => $id]);
}
redirect('/projects.php?stage=' . urlencode($stage) . '&ok=1');
