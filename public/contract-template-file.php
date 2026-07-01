<?php

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/contracts.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true)) {
    http_response_code(403);
    exit('Sem permissao.');
}

contracts_bootstrap((int) $user['company_id']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('select * from contract_templates where id = ? and company_id = ? limit 1');
$stmt->execute([$id, (int) $user['company_id']]);
$template = $stmt->fetch();

if (!$template || empty($template['source_file_stored'])) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

$path = __DIR__ . '/../uploads/contracts/templates/' . (int) $template['company_id'] . '/' . $template['source_file_stored'];
serve_contract_file($path, $template['source_file_original'] ?: $template['source_file_stored'], $template['source_file_mime'] ?: 'application/octet-stream');

