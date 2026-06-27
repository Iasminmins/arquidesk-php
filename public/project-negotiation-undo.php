<?php
// Desfaz mudanças de negotiation_status (desistir/reativar), restaurando o valor anterior.
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
require_csrf();
if ($user['role'] === 'CONFERENTE') {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}
$id = (int) ($_POST['id'] ?? 0);
$companyId = (int) $user['company_id'];
$target = $_POST['target_status'] ?? '';

// Valores permitidos para restauração
$allowed = ['Desistida', 'Em negociação', 'Detalhamento de venda', ''];
if (!in_array($target, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Status inválido.'], 422);
}

$stmt = db()->prepare('select id from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
if (!$stmt->fetch()) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

db()->prepare('update client_projects set negotiation_status = ? where id = ? and company_id = ?')
    ->execute([$target !== '' ? $target : null, $id, $companyId]);
db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?,?,?,?,?,?)')->execute([
    $companyId, $id, 'NEGOCIACAO', 'NEGOCIACAO', 'Ação desfeita', (int) $user['id'],
]);

json_response(['ok' => true, 'id' => $id]);
