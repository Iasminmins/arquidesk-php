<?php

require_once __DIR__ . '/../app/includes/auth.php';

$hasUsers = (int) db()->query('select count(*) from users')->fetchColumn() > 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasUsers) {
    $companyName = trim($_POST['company_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$companyName || !$name || !$email || strlen($password) < 6) {
        $error = 'Preencha todos os campos. A senha precisa ter pelo menos 6 caracteres.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('insert into companies (name, email) values (?, ?)');
        $stmt->execute([$companyName, $email]);
        $companyId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('insert into users (company_id, name, email, password_hash, role) values (?, ?, ?, ?, ?)');
        $stmt->execute([$companyId, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'ADMIN_EMPRESA']);

        $selectedPlan = $_POST['selected_plan'] ?? 'PROFISSIONAL';
        $validPlans = ['START', 'PROFISSIONAL', 'PREMIUM'];
        if (!in_array($selectedPlan, $validPlans, true)) $selectedPlan = 'PROFISSIONAL';

        $stmt = $pdo->prepare('insert into subscriptions (company_id, plan, status, trial_ends_at, selected_plan_key) values (?, ?, ?, date_add(curdate(), interval 30 day), ?)');
        $stmt->execute([$companyId, $selectedPlan, 'TRIAL', $selectedPlan]);
        $pdo->commit();

        $success = 'Administrador criado. Já pode entrar no sistema.';
        $hasUsers = true;
    }
}

$pageTitle = 'Setup inicial';
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
        <div class="w-full max-w-md rounded-lg border border-line bg-white p-6 shadow-sm">
            <div class="grid grid-cols-2 rounded-md border border-line bg-fog p-1 text-sm font-semibold">
                <a class="rounded px-3 py-2 text-center text-ink/60" href="/login.php">Entrar</a>
                <a class="rounded bg-white px-3 py-2 text-center text-ink shadow-sm" href="/setup.php">Cadastrar</a>
            </div>
            <h2 class="mt-6 text-2xl font-bold">Criar conta</h2>

            <?php if ($hasUsers && !$success): ?>
                <p class="mt-4 text-sm text-slate-600">Já existe usuário cadastrado. Acesse pelo login.</p>
                <a class="mt-5 inline-flex min-h-10 items-center rounded-md bg-ink px-4 font-bold text-white" href="/login.php">Ir para login</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($success) ?></div>
                    <a class="mt-5 inline-flex min-h-10 items-center rounded-md bg-ink px-4 font-bold text-white" href="/login.php">Entrar agora</a>
                <?php else: ?>
                    <form method="post" class="mt-6 grid gap-4">
                        <?php
                        $planFromUrl = $_GET['plan'] ?? '';
                        $planLabel = '';
                        if ($planFromUrl && isset(plan_config()[$planFromUrl])) {
                            $planLabel = plan_config()[$planFromUrl]['name'];
                        }
                        ?>
                        <input type="hidden" name="selected_plan" value="<?= e($planFromUrl ?: 'PROFISSIONAL') ?>">
                        <?php if ($planLabel): ?>
                            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                                Plano selecionado: <strong><?= e($planLabel) ?></strong> · 1 mês grátis
                            </div>
                        <?php endif; ?>
                        <label class="grid gap-1 text-sm font-semibold">Nome
                            <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" name="name" required>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">Empresa
                            <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" name="company_name" required>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">E-mail
                            <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="email" name="email" required>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">Senha
                            <input class="min-h-11 rounded-md border border-line px-3 outline-none focus:border-ink" type="password" name="password" required minlength="6">
                        </label>
                        <button class="min-h-11 rounded-md bg-ink px-4 font-bold text-white" type="submit">Criar conta</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
