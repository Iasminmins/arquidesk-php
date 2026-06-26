<?php

require_once __DIR__ . '/../app/includes/auth.php';

$error = '';
$success = '';
$tokenValid = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($token) {
    $stmt = db()->prepare('select pr.*, u.name, u.email from password_resets pr join users u on u.id = pr.user_id where pr.token = ? and pr.used_at is null and pr.expires_at > now() limit 1');
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    $tokenValid = (bool) $reset;
    if (!$tokenValid) {
        $error = 'Link expirado ou inválido. Solicite um novo link de redefinição.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token && $tokenValid) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6) {
        $error = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas não conferem.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('update users set password_hash = ? where id = ?')->execute([$hash, $reset['user_id']]);
        db()->prepare('update password_resets set used_at = now() where id = ?')->execute([$reset['id']]);
        $success = 'Senha redefinida com sucesso! Já pode fazer login.';
        $tokenValid = false;
    }
}

$pageTitle = 'Redefinir senha';
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
            <h2 class="text-2xl font-bold">Redefinir senha</h2>

            <?php if ($error): ?>
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div>
                <?php if (!$tokenValid): ?>
                    <a class="mt-4 inline-flex min-h-10 items-center rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog" href="/forgot-password.php">Solicitar novo link</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($success) ?></div>
                <a class="mt-5 inline-flex min-h-10 items-center rounded-md bg-ink px-4 font-bold text-white" href="/login.php">Ir para login</a>
            <?php elseif ($tokenValid): ?>
                <p class="mt-2 text-sm text-slate-500">Olá <?= e($reset['name']) ?>, defina sua nova senha.</p>
                <form method="post" class="mt-6 grid gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label class="grid gap-1 text-sm font-semibold">Nova senha
                        <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="password" name="password" required minlength="6" autofocus>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Confirmar nova senha
                        <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="password" name="password_confirm" required minlength="6">
                    </label>
                    <button class="min-h-11 rounded-md bg-ink px-4 font-bold text-white" type="submit">Redefinir senha</button>
                </form>
            <?php elseif (!$error): ?>
                <p class="mt-4 text-sm text-slate-500">Nenhum token informado.</p>
                <a class="mt-4 inline-flex min-h-10 items-center rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog" href="/forgot-password.php">Solicitar redefinição</a>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
