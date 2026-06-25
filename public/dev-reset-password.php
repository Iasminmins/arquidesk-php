<?php

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Disponivel apenas localmente.');
}

$message = '';
$error = '';

$users = db()->query('select id, name, email from users order by id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if ($userId && strlen($password) >= 6) {
        $stmt = db()->prepare('update users set password_hash = ? where id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $message = 'Senha redefinida. Volte para o login.';
    } else {
        $error = 'Selecione o usuário e informe uma senha com no mínimo 6 caracteres.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset local - Arquidesk</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="grid min-h-screen place-items-center bg-slate-100 px-4 text-slate-900">
    <main class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">Reset local de senha</h1>
        <p class="mt-2 text-sm text-slate-600">Ferramenta apenas para teste local.</p>
        <?php if ($message): ?><div class="mt-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="mt-5 grid gap-4">
            <label class="grid gap-1 text-sm font-semibold">Usuário
                <select class="min-h-10 rounded-md border border-slate-300 px-3" name="user_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int) $user['id'] ?>"><?= e($user['name'] . ' - ' . $user['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Nova senha
                <input class="min-h-10 rounded-md border border-slate-300 px-3" type="password" name="password" minlength="6" required>
            </label>
            <button class="min-h-10 rounded-md bg-slate-900 px-4 font-bold text-white" type="submit">Redefinir senha</button>
        </form>
        <a class="mt-4 inline-flex text-sm font-semibold underline" href="/login.php">Voltar ao login</a>
    </main>
</body>
</html>
