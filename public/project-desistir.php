<?php
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
require_csrf();
if ($user['role'] === 'CONFERENTE') {
    if (wants_json()) json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
    redirect('/');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/projects.php?stage=NEGOCIACAO');
$id = (int) ($_POST['id'] ?? 0);
$companyId = (int) $user['company_id'];
$ajax = wants_json();

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();
if (!$project) {
    if ($ajax) json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
    redirect('/projects.php?stage=NEGOCIACAO');
}

$prevStatus = $project['negotiation_status'];

db()->prepare('update client_projects set negotiation_status = ? where id = ? and company_id = ?')->execute(['Desistida', $id, $companyId]);
db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?,?,?,?,?,?)')->execute([
    $companyId, $id, 'NEGOCIACAO', 'NEGOCIACAO', 'Marcado como desistida', (int) $user['id'],
]);

if ($ajax) {
    json_response(['ok' => true, 'id' => $id, 'prev_status' => $prevStatus]);
}
redirect('/projects.php?stage=NEGOCIACAO&ok=1&msg=' . urlencode('Projeto marcado como desistida.'));
