<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if ($user['role'] !== 'ADMIN_EMPRESA') {
    http_response_code(403);
    exit('Acesso restrito ao administrador da empresa.');
}

$companyId = (int) $user['company_id'];
$error = '';
$subscription = get_subscription($companyId);
$userLimit = plan_user_limit($subscription['plan']);
$editEmployee = null;

if (!empty($_GET['edit'])) {
    $editStmt = db()->prepare('select * from users where id = ? and company_id = ?');
    $editStmt->execute([(int) $_GET['edit'], $companyId]);
    $editEmployee = $editStmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_id'])) {
    $editId = (int) $_POST['edit_id'];
    $editName = trim($_POST['edit_name'] ?? '');
    $editEmail = trim($_POST['edit_email'] ?? '');
    $editRole = $_POST['edit_role'] ?? 'PROJETISTA';
    $editPassword = $_POST['edit_password'] ?? '';

    if ($editName && $editEmail) {
        if ($editPassword && strlen($editPassword) >= 6) {
            db()->prepare('update users set name = ?, email = ?, role = ?, password_hash = ? where id = ? and company_id = ?')->execute([$editName, $editEmail, $editRole, password_hash($editPassword, PASSWORD_DEFAULT), $editId, $companyId]);
        } else {
            db()->prepare('update users set name = ?, email = ?, role = ? where id = ? and company_id = ?')->execute([$editName, $editEmail, $editRole, $editId, $companyId]);
        }
        redirect('/employees.php?ok=1');
    } else {
        $error = 'Preencha nome e e-mail.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'PROJETISTA';

    $countStmt = db()->prepare('select count(*) from users where company_id = ?');
    $countStmt->execute([$companyId]);
    $currentCount = (int) $countStmt->fetchColumn();

    if ($currentCount >= $userLimit) {
        $error = "Seu plano atual permite até {$userLimit} usuários. Para adicionar mais pessoas, faça upgrade do plano.";
    } elseif ($name && $email && strlen($password) >= 6) {
        $stmt = db()->prepare('insert into users (company_id, name, email, password_hash, role) values (?, ?, ?, ?, ?)');
        try {
            $stmt->execute([$companyId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            redirect('/employees.php?ok=1');
        } catch (Throwable $exception) {
            $error = 'Não foi possível criar o funcionário. Verifique se o e-mail já existe.';
        }
    } else {
        $error = 'Preencha nome, e-mail e senha com no minimo 6 caracteres.';
    }
}

$stmt = db()->prepare('select * from users where company_id = ? order by name');
$stmt->execute([$companyId]);
$employees = $stmt->fetchAll();

$pageTitle = 'Funcionários';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <div class="rounded-lg border border-line bg-white p-4 text-sm text-slate-500">
        Plano atual: <strong class="text-ink"><?= e(plan_label($subscription['plan'])) ?></strong> · Usuários: <strong class="text-ink"><?= count($employees) ?>/<?= $userLimit ?></strong>
    </div>
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Operacao concluida.</div><?php endif; ?>
    <?php if ($error): ?><div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>

    <?php if ($editEmployee): ?>
    <form method="post" class="grid gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 xl:grid-cols-[1fr_1fr_180px_180px_auto] xl:items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="edit_id" value="<?= (int) $editEmployee['id'] ?>">
        <label class="grid gap-1 text-sm font-semibold">Nome
            <input class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="edit_name" required value="<?= e($editEmployee['name']) ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">E-mail
            <input class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" type="email" name="edit_email" required value="<?= e($editEmployee['email']) ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Nova senha (opcional)
            <input class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" type="password" name="edit_password" minlength="6" placeholder="Manter atual">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Permissão
            <select class="min-h-10 rounded-md border border-line bg-white px-3 outline-none focus:border-ink" name="edit_role">
                <option value="PROJETISTA" <?= $editEmployee['role'] === 'PROJETISTA' ? 'selected' : '' ?>>Projetista</option>
                <option value="CONFERENTE" <?= $editEmployee['role'] === 'CONFERENTE' ? 'selected' : '' ?>>Conferente</option>
                <option value="ADMIN_EMPRESA" <?= $editEmployee['role'] === 'ADMIN_EMPRESA' ? 'selected' : '' ?>>Admin empresa</option>
            </select>
        </label>
        <div class="flex gap-2">
            <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar</button>
            <a class="inline-flex min-h-10 items-center rounded-md border border-line px-4 text-sm font-semibold hover:bg-white" href="/employees.php">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>

    <form method="post" class="grid gap-3 rounded-lg border border-line bg-white p-4 xl:grid-cols-[1fr_1fr_180px_180px_auto] xl:items-end">
        <?= csrf_field() ?>
        <label class="grid gap-1 text-sm font-semibold">Nome
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="name" required>
        </label>
        <label class="grid gap-1 text-sm font-semibold">E-mail
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="email" name="email" required>
        </label>
        <label class="grid gap-1 text-sm font-semibold">Senha
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="password" name="password" minlength="6" required>
        </label>
        <label class="grid gap-1 text-sm font-semibold">Permissao
            <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="role">
                <option value="PROJETISTA">Projetista</option>
                <option value="CONFERENTE">Conferente</option>
                <option value="ADMIN_EMPRESA">Admin empresa</option>
            </select>
        </label>
        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Criar</button>
    </form>

    <section class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-fog text-xs uppercase text-slate-500">
                <tr><th class="p-3">Nome</th><th class="p-3">E-mail</th><th class="p-3">Permissão</th><th class="p-3">Status</th><th class="p-3 text-right">Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <tr class="border-t border-line">
                        <td class="p-3"><?= e($employee['name']) ?></td>
                        <td class="p-3"><?= e($employee['email']) ?></td>
                        <td class="p-3"><?= e($employee['role']) ?></td>
                        <td class="p-3"><?= $employee['active'] ? 'Ativo' : 'Inativo' ?></td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <a class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" href="/employees.php?edit=<?= (int) $employee['id'] ?>">Editar</a>
                                <form method="post" action="/employee-toggle.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                    <button class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" type="submit"><?= $employee['active'] ? 'Desativar' : 'Ativar' ?></button>
                                </form>
                                <?php if ((int) $employee['id'] !== (int) $user['id']): ?>
                                    <form method="post" action="/employee-delete.php" onsubmit="return confirm('Excluir este funcionário?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <button class="rounded-md bg-red-600 px-3 py-2 text-xs font-bold text-white" type="submit">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>

