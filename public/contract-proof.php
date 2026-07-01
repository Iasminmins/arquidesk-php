<?php

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/contracts.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true)) {
    redirect('/');
}

contracts_bootstrap();

$companyId = (int) $user['company_id'];
$token = $_GET['t'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Comprovante nao encontrado.');
}

$sql = 'select pc.*, p.client_name, p.project_name, p.current_stage, p.designer_id,
               c.name as company_name, c.document as company_document
        from project_contracts pc
        join client_projects p on p.id = pc.client_project_id and p.company_id = pc.company_id
        join companies c on c.id = pc.company_id
        where pc.public_token = ? and pc.company_id = ?';
$params = [$token, $companyId];
if ($user['role'] === 'PROJETISTA') {
    $sql .= ' and p.designer_id = ?';
    $params[] = (int) $user['id'];
}
$sql .= ' limit 1';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$contract = $stmt->fetch();

if (!$contract || !in_array($contract['status'], ['ACCEPTED', 'SIGNED_MANUAL', 'SIGNED_GOVBR'], true)) {
    http_response_code(404);
    exit('Comprovante nao encontrado.');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprovante - <?= e($contract['title']) ?></title>
    <style>
        @page { margin: 18mm; }
        * { box-sizing: border-box; }
        body { color: #15201d; font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.55; margin: 0; }
        header { border-bottom: 1px solid #ddd; margin-bottom: 22px; padding-bottom: 16px; }
        h1 { font-size: 20pt; margin: 0 0 8px; }
        h2 { font-size: 14pt; margin: 24px 0 10px; }
        table { border-collapse: collapse; width: 100%; }
        td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        td:first-child { background: #f6f4ef; font-weight: bold; width: 220px; }
        .body { border: 1px solid #ddd; margin-top: 12px; padding: 14px; white-space: pre-wrap; }
        .signature-image { border: 1px solid #ddd; border-radius: 8px; display: block; margin: 14px 0; max-height: 120px; max-width: 360px; padding: 8px; }
        .muted { color: #666; font-size: 10pt; }
        .actions { margin: 24px 0; }
        .actions button { background: #15201d; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font-weight: 700; min-height: 40px; padding: 0 18px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Salvar comprovante / Imprimir</button>
    </div>
    <header>
        <p class="muted"><?= e($contract['company_name']) ?><?= $contract['company_document'] ? ' - ' . e($contract['company_document']) : '' ?></p>
        <h1>Comprovante de aceite eletronico</h1>
        <p class="muted"><?= e($contract['title']) ?> - <?= e($contract['client_name']) ?> - <?= e($contract['project_name']) ?></p>
    </header>

    <section>
        <h2>Dados do aceite</h2>
        <?php if ($contract['status'] === 'SIGNED_MANUAL' && !empty($contract['manual_signature_data'])): ?>
            <img class="signature-image" src="<?= e($contract['manual_signature_data']) ?>" alt="Assinatura do contratante">
        <?php endif; ?>
        <table>
            <tr><td>Status</td><td><?= e(contract_status_label($contract['status'])) ?></td></tr>
            <tr><td>Metodo</td><td><?= e(contract_method_label($contract['signature_method'])) ?></td></tr>
            <tr><td>Nome informado</td><td><?= e($contract['accepted_name']) ?></td></tr>
            <tr><td>CPF/CNPJ informado</td><td><?= e($contract['accepted_document']) ?></td></tr>
            <tr><td>E-mail informado</td><td><?= e($contract['accepted_email'] ?: '-') ?></td></tr>
            <tr><td>Data e hora</td><td><?= e(date('d/m/Y H:i:s', strtotime($contract['accepted_at']))) ?></td></tr>
            <tr><td>IP registrado</td><td><?= e($contract['accepted_ip'] ?: '-') ?></td></tr>
            <tr><td>Navegador/dispositivo</td><td><?= e($contract['accepted_user_agent'] ?: '-') ?></td></tr>
            <tr><td>Codigo do contrato</td><td><?= e($contract['public_token']) ?></td></tr>
        </table>
    </section>

    <section>
        <h2>Conteudo aceito</h2>
        <article class="body"><?= e($contract['body']) ?></article>
    </section>
</body>
</html>
