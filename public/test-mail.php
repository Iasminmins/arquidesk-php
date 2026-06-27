<?php

/**
 * Teste de envio de email. Acesse via navegador:
 *   http://arquidesk-php.test/test-mail.php?to=seuemail@gmail.com
 *   (ou a URL local do seu Laragon)
 *
 * IMPORTANTE: APAGUE este arquivo depois de testar.
 */

require_once __DIR__ . '/../app/includes/mailer.php';
require_once __DIR__ . '/../app/includes/email-templates.php';

$to = $_GET['to'] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit('Use ?to=seuemail@exemplo.com');
}

$html = email_password_reset('Teste', 'https://exemplo.com/reset-password.php?token=teste123');
$text = email_password_reset_text('Teste', 'https://exemplo.com/reset-password.php?token=teste123');

$ok = send_mail($to, 'Teste', 'Teste de email - Arquidesk', $html, $text);

echo $ok
    ? 'Email enviado com sucesso para ' . htmlspecialchars($to) . '. Verifique a caixa de entrada (e o spam).'
    : 'Falha ao enviar. Verifique o bloco mail em config.local.php e o log de erros do PHP.';
