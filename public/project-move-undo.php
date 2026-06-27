<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_csrf();

if ($user['role'] === 'CONFERENTE') {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);
$fromStage = $_POST['from_stage'] ?? ''; // estágio de origem (para onde voltar)
$toStage = $_POST['to_stage'] ?? '';     // estágio atual (de onde sai)

$stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

// Segurança: só desfaz se o projeto ainda estiver no estágio para onde acabou de ser movido,
// e se o estágio de origem for de fato o anterior imediato.
if ($project['current_stage'] !== $toStage || next_stage($fromStage) !== $toStage) {
    json_response(['ok' => false, 'error' => 'Não é mais possível desfazer esta movimentação.'], 409);
}

// Se voltou de FINALIZADO, limpa o finished_at
$clearFinished = $toStage === 'FINALIZADO';

if ($clearFinished) {
    $update = db()->prepare('update client_projects set current_stage = ?, finished_at = null where id = ? and company_id = ?');
    $update->execute([$fromStage, $id, $companyId]);
} else {
    $update = db()->prepare('update client_projects set current_stage = ? where id = ? and company_id = ?');
    $update->execute([$fromStage, $id, $companyId]);
}

// Registra a reversão no histórico (auditoria honesta — não apaga o movimento original)
$history = db()->prepare('insert into flow_history (company_id, client_project_id, from_stage, to_stage, action, user_id) values (?, ?, ?, ?, ?, ?)');
$history->execute([$companyId, $id, $toStage, $fromStage, 'Movimentação desfeita', (int) $user['id']]);

json_response([
    'ok' => true,
    'id' => $id,
    'current_stage' => $fromStage,
]);
