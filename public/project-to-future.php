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
$pdo = db();
$pdo->beginTransaction();

$pdo->prepare('insert into future_clients (company_id, designer_id, name, phone, address, interest, estimated_value, contact_date, next_contact_date, source, status, notes) values (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    $companyId,
    $project['designer_id'],
    $project['client_name'],
    $project['client_phone'] ?? '',
    $project['client_address'] ?? null,
    $project['project_name'],
    $project['closed_value'] ?: $project['new_proposal_value'],
    date('Y-m-d'),
    date('Y-m-d', strtotime('+30 days')),
    'Negociação',
    'AGUARDANDO',
    'Veio da negociação. Projeto: ' . $project['project_name'] . '. ' . ($project['notes'] ?? ''),
]);
$futureId = (int) $pdo->lastInsertId();

$pdo->prepare('update client_projects set negotiation_status = ? where id = ? and company_id = ?')->execute(['Desistida', $id, $companyId]);
$pdo->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?,?,?,?,?,?)')->execute([
    $companyId, $id, 'NEGOCIACAO', 'NEGOCIACAO', 'Enviado para Clientes Futuros', (int) $user['id'],
]);

$pdo->commit();

if ($ajax) {
    json_response(['ok' => true, 'id' => $id, 'future_id' => $futureId, 'prev_status' => $prevStatus]);
}
redirect('/projects.php?stage=NEGOCIACAO&ok=1&msg=' . urlencode('Cliente enviado para Clientes Futuros com próximo contato em 30 dias.'));
