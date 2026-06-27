<?php

/**
 * Mailer do Arquidesk usando mail() nativo do PHP.
 * Mesma técnica do site-carteira (QRCertify): MIME multipart (texto + HTML),
 * assunto e remetente codificados em UTF-8 base64. Sem dependências externas.
 *
 * Configuração no array 'mail' do config.local.php:
 *   from_email, from_name, reply_to (opcional)
 *
 * Uso:
 *   send_mail('dest@email.com', 'Nome', 'Assunto', '<p>HTML do corpo</p>', 'texto puro opcional');
 */

function mail_config(): array
{
    $config = require __DIR__ . '/../config/config.php';
    return $config['mail'] ?? [];
}

/**
 * Envia um email com corpo HTML via mail() nativo, bem formatado para entrega.
 * O $htmlBody recebido é o conteúdo interno; aqui ele é embrulhado no layout completo.
 * Retorna true em caso de sucesso.
 */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $cfg = mail_config();

    $fromEmail = $cfg['from_email'] ?? 'noreply@localhost';
    $fromName  = $cfg['from_name'] ?? 'Arquidesk';
    $replyTo   = $cfg['reply_to'] ?? $fromEmail;

    // Fallback texto puro a partir do HTML
    if ($textBody === '') {
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    }

    $boundary = '==ARQUIDESK_' . md5(uniqid('', true)) . '==';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($textBody)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--";

    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($toEmail, $encSubject, $body, $headers);
}
