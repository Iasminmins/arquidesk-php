<?php

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/contracts.php';

contracts_bootstrap();

$token = $_GET['t'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Contrato nao encontrado.');
}

$stmt = db()->prepare(
    'select pc.*, p.client_name, p.project_name, c.name as company_name, c.document, c.email, c.phone, c.address
     from project_contracts pc
     join client_projects p on p.id = pc.client_project_id and p.company_id = pc.company_id
     join companies c on c.id = pc.company_id
     where pc.public_token = ?
     limit 1'
);
$stmt->execute([$token]);
$contract = $stmt->fetch();

if (!$contract || $contract['status'] === 'CANCELED') {
    http_response_code(404);
    exit('Contrato nao encontrado ou cancelado.');
}

if (!empty($contract['signed_file_stored'])) {
    redirect(contract_signed_file_url($contract));
}

if ($contract['status'] === 'SIGNED_MANUAL' && !empty($contract['manual_signature_data'])) {
    $signedPdf = contract_regenerate_manual_signed_pdf($contract);
    if ($signedPdf) {
        $contract['signed_file_stored'] = $signedPdf['stored'];
        $contract['signed_file_original'] = $signedPdf['original'];
        $contract['signed_file_mime'] = $signedPdf['mime'];
        redirect(contract_signed_file_url($contract));
    }
}

$isAccepted = $contract['status'] === 'ACCEPTED';
$isGovbrSigned = $contract['status'] === 'SIGNED_GOVBR';
$isManualSigned = $contract['status'] === 'SIGNED_MANUAL';
$sourceFileUrl = !empty($contract['source_file_stored']) ? contract_template_file_url($contract) : '';
$isSourcePdf = $sourceFileUrl !== '' && (
    ($contract['source_file_mime'] ?? '') === 'application/pdf'
    || strtolower(pathinfo($contract['source_file_original'] ?? '', PATHINFO_EXTENSION)) === 'pdf'
);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($contract['title']) ?></title>
    <style>
        @page { margin: 18mm; }
        * { box-sizing: border-box; }
        body { color: #15201d; font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.55; margin: 0; }
        header { border-bottom: 1px solid #ddd; margin-bottom: 24px; padding-bottom: 16px; }
        h1 { font-size: 20pt; margin: 0 0 8px; }
        .muted { color: #666; font-size: 10pt; }
        .pdf-frame { border: 1px solid #ddd; height: 78vh; margin-bottom: 22px; width: 100%; }
        .source-note { background: #f6f4ef; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 18px; padding: 12px; }
        .body { white-space: pre-wrap; }
        .signature { display: grid; gap: 36px; grid-template-columns: 1fr 1fr; margin-top: 64px; }
        .line { border-top: 1px solid #222; padding-top: 8px; text-align: center; }
        .stamp { border: 2px solid #15201d; border-radius: 10px; margin-top: 40px; padding: 16px; }
        .stamp h2 { font-size: 15pt; margin: 0 0 8px; text-transform: uppercase; }
        .signature-image { border: 1px solid #ddd; border-radius: 8px; display: block; margin: 14px 0; max-height: 120px; max-width: 360px; padding: 8px; }
        .stamp table { border-collapse: collapse; margin-top: 12px; width: 100%; }
        .stamp td { border: 1px solid #ddd; padding: 7px; vertical-align: top; }
        .stamp td:first-child { background: #f6f4ef; font-weight: bold; width: 190px; }
        .actions { margin: 24px 0; }
        .actions button { background: #15201d; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font-weight: 700; min-height: 40px; padding: 0 18px; }
        @media print {
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Salvar como PDF / Imprimir</button>
    </div>
    <header>
        <p class="muted"><?= e($contract['company_name']) ?><?= $contract['document'] ? ' · ' . e($contract['document']) : '' ?></p>
        <h1><?= e($contract['title']) ?></h1>
        <p class="muted"><?= e($contract['client_name']) ?> · <?= e($contract['project_name']) ?></p>
    </header>
    <main>
        <?php if ($isSourcePdf): ?>
            <section class="source-note">
                <strong>Contrato original anexado</strong>
                <p class="muted"><?= e($contract['source_file_original'] ?? 'PDF anexado') ?>. A assinatura registrada abaixo pertence a este contrato.</p>
            </section>
            <iframe class="pdf-frame" src="<?= e($sourceFileUrl) ?>#toolbar=1" title="Contrato original anexado"></iframe>
        <?php elseif ($sourceFileUrl): ?>
            <section class="source-note">
                <strong>Contrato original anexado</strong>
                <p class="muted">O contrato original esta em formato Word. Use o arquivo original anexado e o bloco de assinatura abaixo como comprovante visual.</p>
            </section>
            <article class="body"><?= e($contract['body']) ?></article>
        <?php else: ?>
            <article class="body"><?= e($contract['body']) ?></article>
        <?php endif; ?>
        <?php if ($isAccepted || $isManualSigned): ?>
            <section class="stamp">
                <h2><?= $isManualSigned ? 'Assinado manualmente na tela' : 'Assinado eletronicamente' ?></h2>
                <p><?= $isManualSigned ? 'Este contrato foi assinado pelo contratante desenhando a assinatura no Arquidesk.' : 'Este contrato foi aceito eletronicamente pelo contratante no Arquidesk.' ?></p>
                <?php if ($isManualSigned && !empty($contract['manual_signature_data'])): ?>
                    <img class="signature-image" src="<?= e($contract['manual_signature_data']) ?>" alt="Assinatura do contratante">
                <?php endif; ?>
                <table>
                    <tr><td>Assinante</td><td><?= e($contract['accepted_name']) ?></td></tr>
                    <tr><td>CPF/CNPJ informado</td><td><?= e($contract['accepted_document']) ?></td></tr>
                    <tr><td>E-mail informado</td><td><?= e($contract['accepted_email'] ?: '-') ?></td></tr>
                    <tr><td>Data e hora</td><td><?= e(date('d/m/Y H:i:s', strtotime($contract['accepted_at']))) ?></td></tr>
                    <tr><td>IP registrado</td><td><?= e($contract['accepted_ip'] ?: '-') ?></td></tr>
                    <tr><td>Codigo do contrato</td><td><?= e($contract['public_token']) ?></td></tr>
                </table>
            </section>
        <?php elseif ($isGovbrSigned): ?>
            <section class="stamp">
                <h2>Assinado via gov.br</h2>
                <p>O PDF assinado pelo gov.br foi recebido e arquivado no contrato.</p>
                <table>
                    <tr><td>Arquivo recebido</td><td><?= e($contract['signed_file_original'] ?: '-') ?></td></tr>
                    <tr><td>Data de recebimento</td><td><?= e($contract['signed_file_uploaded_at'] ? date('d/m/Y H:i:s', strtotime($contract['signed_file_uploaded_at'])) : '-') ?></td></tr>
                    <tr><td>Codigo do contrato</td><td><?= e($contract['public_token']) ?></td></tr>
                </table>
            </section>
        <?php else: ?>
            <section class="signature">
                <div class="line">Contratante</div>
                <div class="line"><?= e($contract['company_name']) ?></div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
