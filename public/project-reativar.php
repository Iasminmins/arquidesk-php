<?php
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
if ($user['role'] === 'CONFERENTE') redirect('/');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/projects.php?stage=NEGOCIACAO');
$id = (int) ($_POST['id'] ?? 0);
$companyId = (int) $user['company_id'];

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();
if (!$project) redirect('/projects.php?stage=NEGOCIACAO');

db()->prepare('update client_projects set negotiation_status = ? where id = ? and company_id = ?')->execute(['Em negociação', $id, $companyId]);
db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?,?,?,?,?,?)')->execute([
    $companyId, $id, 'NEGOCIACAO', 'NEGOCIACAO', 'Reativado da lista de desistidas', (int) $user['id'],
]);

redirect('/projects.php?stage=NEGOCIACAO&ok=1&msg=' . urlencode('Negociação reativada com sucesso.'));
