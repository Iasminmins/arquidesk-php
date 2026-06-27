<?php
// Desfaz o envio para Clientes Futuros: remove o registro criado e restaura o status.
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
require_csrf();
if ($user['role'] === 'CONFERENTE') {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}
$id = (int) ($_POST['id'] ?? 0);
$futureId = (int) ($_POST['future_id'] ?? 0);
$prevStatus = $_POST['prev_status'] ?? '';
$companyId = (int) $user['company_id'];

$stmt = db()->prepare('select id from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
if (!$stmt->fetch()) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

$pdo = db();
$pdo->beginTransaction();

// Remove o future_client criado (escopo da empresa por segurança)
if ($futureId) {
    $pdo->prepare('delete from future_clients where id = ? and company_id = ?')->execute([$futureId, $companyId]);
}

// Restaura status anterior do projeto
$pdo->prepare('update client_projects set negotiation_status = ? where id = ? and company_id = ?')
    ->execute([$prevStatus !== '' ? $prevStatus : null, $id, $companyId]);
$pdo->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?,?,?,?,?,?)')->execute([
    $companyId, $id, 'NEGOCIACAO', 'NEGOCIACAO', 'Envio para Clientes Futuros desfeito', (int) $user['id'],
]);

$pdo->commit();

json_response(['ok' => true, 'id' => $id]);
