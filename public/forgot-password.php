<?php

require_once __DIR__ . '/../app/includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Informe seu e-mail.';
    } else {
        $stmt = db()->prepare('select id, name, email from users where email = ? and active = 1 limit 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate old tokens
            db()->prepare('delete from password_resets where user_id = ?')->execute([$user['id']]);

            // Create new token (valid for 1 hour)
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            db()->prepare('insert into password_resets (user_id, token, expires_at) values (?, ?, ?)')->execute([$user['id'], $token, $expiresAt]);

            // Build reset URL
            $config = require __DIR__ . '/../app/config/config.php';
            $baseUrl = rtrim($config['base_url'] ?? '', '/');
            if (!$baseUrl) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            $resetUrl = $baseUrl . '/reset-password.php?token=' . $token;

            // Send email
            $subject = 'Redefinir senha - Arquidesk';
            $body = "Olá {$user['name']},\n\n";
            $body .= "Você solicitou a redefinição de senha no Arquidesk.\n\n";
            $body .= "Clique no link abaixo para criar uma nova senha:\n";
            $body .= $resetUrl . "\n\n";
            $body .= "Este link é válido por 1 hora.\n\n";
            $body .= "Se você não solicitou, ignore este e-mail.\n\n";
            $body .= "— Equipe Arquidesk";

            $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'arquidesk.com') . "\r\n";
            $headers .= "Reply-To: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'arquidesk.com') . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            @mail($user['email'], $subject, $body, $headers);
        }

        // Always show success (don't reveal if email exists)
        $success = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.';
    }
}

$pageTitle = 'Esqueci minha senha';
require __DIR__ . '/../app/includes/header.php';
?>
<main class="grid min-h-screen grid-cols-1 bg-fog lg:grid-cols-[1.1fr_0.9fr]">
    <section class="flex min-h-[42vh] items-end bg-[linear-gradient(135deg,rgba(21,32,29,.82),rgba(71,98,79,.74)),url('https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1600&q=80')] bg-cover bg-center p-8 text-white lg:min-h-screen lg:p-12">
        <div class="max-w-2xl">
            <div class="mb-5 inline-flex items-center gap-3 text-lg font-bold">
                <span class="grid h-10 w-10 place-items-center rounded-md bg-white/15 text-white">A</span>
                Arquidesk
            </div>
            <h1 class="text-4xl font-black leading-tight md:text-6xl">Gestão completa para arquitetura, marcenaria e interiores.</h1>
        </div>
    </section>
    <section class="flex items-center justify-center p-6">
        <div class="w-full max-w-md rounded-lg border border-line bg-white p-6 shadow-sm">
            <h2 class="text-2xl font-bold">Esqueci minha senha</h2>
            <p class="mt-2 text-sm text-slate-500">Informe o e-mail cadastrado e enviaremos um link para redefinir sua senha.</p>

            <?php if ($error): ?>
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($success) ?></div>
                <a class="mt-5 inline-flex min-h-10 items-center rounded-md bg-ink px-4 font-bold text-white" href="/login.php">Voltar ao login</a>
            <?php else: ?>
                <form method="post" class="mt-6 grid gap-4">
                    <?= csrf_field() ?>
                    <label class="grid gap-1 text-sm font-semibold">E-mail
                        <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="email" name="email" required autofocus>
                    </label>
                    <button class="min-h-11 rounded-md bg-ink px-4 font-bold text-white" type="submit">Enviar link de redefinição</button>
                </form>
                <a class="mt-4 block text-sm text-slate-500 hover:text-ink" href="/login.php">Voltar ao login</a>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
