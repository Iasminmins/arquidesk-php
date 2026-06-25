<?php

require_once __DIR__ . '/../app/includes/auth.php';

if (current_user()) {
    redirect('/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login_user($email, $password)) {
        redirect('/');
    }

    $error = 'E-mail ou senha inválidos.';
}

$pageTitle = 'Entrar no Arquidesk';
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
            <p class="mt-5 max-w-xl text-lg text-white/85">Fluxo operacional, financeiro separado, metas e permissões por empresa em uma plataforma SaaS.</p>
        </div>
    </section>
    <section class="flex items-center justify-center p-6">
        <form method="post" class="w-full max-w-md rounded-lg border border-line bg-white p-6 shadow-sm">
            <div class="grid grid-cols-2 rounded-md border border-line bg-fog p-1 text-sm font-semibold">
                <a class="rounded bg-white px-3 py-2 text-center text-ink shadow-sm" href="/login.php">Entrar</a>
                <a class="rounded px-3 py-2 text-center text-ink/60" href="/setup.php">Cadastrar</a>
            </div>
            <h2 class="mt-6 text-2xl font-bold">Entrar</h2>

            <?php if ($error): ?>
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="mt-6 grid gap-4">
                <label class="grid gap-1 text-sm font-semibold">E-mail
                    <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="email" name="email" required autofocus>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Senha
                    <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="password" name="password" required>
                </label>
                <button class="min-h-11 rounded-md bg-ink px-4 font-bold text-white hover:opacity-95" type="submit">Entrar</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
