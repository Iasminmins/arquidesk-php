<?php

/**
 * Templates de email do Arquidesk.
 * Cada função retorna o HTML completo do email (compatível com clientes de email:
 * tabelas, estilos inline, sem dependência de CSS externo).
 */

/**
 * Wrapper base — cabeçalho, corpo e rodapé padrão.
 */
function email_layout(string $contentHtml): string
{
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f5f4;font-family:Arial,Helvetica,sans-serif;color:#15201d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f4;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e8e6;">

<!-- Header -->
<tr><td style="background-color:#15201d;padding:28px 32px;">
<table role="presentation" cellpadding="0" cellspacing="0">
<tr>
<td style="width:44px;height:44px;background-color:#b8664b;border-radius:8px;text-align:center;vertical-align:middle;color:#ffffff;font-size:22px;font-weight:bold;line-height:44px;">A</td>
<td style="padding-left:14px;color:#ffffff;font-size:20px;font-weight:bold;">Arquidesk</td>
</tr>
</table>
</td></tr>

<!-- Content -->
<tr><td style="padding:36px 32px;">
{$contentHtml}
</td></tr>

<!-- Footer -->
<tr><td style="background-color:#f4f5f4;padding:24px 32px;border-top:1px solid #e6e8e6;">
<p style="margin:0;font-size:12px;color:#8a948f;line-height:1.6;">
Você está recebendo este email porque foi solicitada uma ação na sua conta Arquidesk.
Se não foi você, pode ignorar esta mensagem com segurança.
</p>
<p style="margin:12px 0 0;font-size:12px;color:#8a948f;">© {$year} Arquidesk · Gestão para arquitetura, interiores e planejados</p>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Email de redefinição de senha.
 */
function email_password_reset(string $name, string $resetUrl): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl  = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;color:#15201d;">Redefinir sua senha</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#3a443f;">
Olá <strong>{$safeName}</strong>, recebemos um pedido para redefinir a senha da sua conta no Arquidesk.
Clique no botão abaixo para criar uma nova senha.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px;">
<tr><td style="border-radius:8px;background-color:#15201d;">
<a href="{$safeUrl}" target="_blank"
style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">
Criar nova senha
</a>
</td></tr>
</table>

<p style="margin:0 0 8px;font-size:13px;color:#8a948f;line-height:1.6;">
Este link é válido por <strong>1 hora</strong>. Após esse período, será necessário solicitar um novo.
</p>
<p style="margin:0 0 4px;font-size:13px;color:#8a948f;line-height:1.6;">
Se o botão não funcionar, copie e cole este endereço no navegador:
</p>
<p style="margin:0;font-size:12px;word-break:break-all;">
<a href="{$safeUrl}" target="_blank" style="color:#b8664b;">{$safeUrl}</a>
</p>
HTML;

    return email_layout($content);
}

/**
 * Versão texto puro (fallback / AltBody).
 */
function email_password_reset_text(string $name, string $resetUrl): string
{
    return "Olá {$name},\n\n"
        . "Recebemos um pedido para redefinir a senha da sua conta no Arquidesk.\n\n"
        . "Acesse o link abaixo para criar uma nova senha (válido por 1 hora):\n"
        . $resetUrl . "\n\n"
        . "Se não foi você, ignore este email.\n\n"
        . "— Equipe Arquidesk";
}
