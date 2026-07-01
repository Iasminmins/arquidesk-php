<?php

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/contracts.php';

contracts_bootstrap();

$token = $_GET['t'] ?? '';
$kind = $_GET['kind'] ?? 'source';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token) || !in_array($kind, ['source', 'signed'], true)) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

$stmt = db()->prepare('select * from project_contracts where public_token = ? and status != ? limit 1');
$stmt->execute([$token, 'CANCELED']);
$contract = $stmt->fetch();

if (!$contract) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

if ($kind === 'signed') {
    if (($contract['status'] ?? '') === 'SIGNED_MANUAL' && !empty($contract['manual_signature_data'])) {
        $signedPdf = contract_regenerate_manual_signed_pdf($contract);
        if ($signedPdf) {
            $contract['signed_file_original'] = $signedPdf['original'];
            $contract['signed_file_stored'] = $signedPdf['stored'];
            $contract['signed_file_size'] = $signedPdf['size'];
            $contract['signed_file_mime'] = $signedPdf['mime'];
        }
    }

    if (empty($contract['signed_file_stored'])) {
        http_response_code(404);
        exit('Arquivo nao encontrado.');
    }
    $path = __DIR__ . '/../uploads/contracts/' . (int) $contract['company_id'] . '/' . (int) $contract['client_project_id'] . '/' . (int) $contract['id'] . '/' . $contract['signed_file_stored'];
    serve_contract_file($path, $contract['signed_file_original'] ?: $contract['signed_file_stored'], $contract['signed_file_mime'] ?: 'application/pdf');
}

if (empty($contract['source_file_stored'])) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

$path = __DIR__ . '/../uploads/contracts/templates/' . (int) $contract['company_id'] . '/' . $contract['source_file_stored'];
serve_contract_file($path, $contract['source_file_original'] ?: $contract['source_file_stored'], $contract['source_file_mime'] ?: 'application/octet-stream');
