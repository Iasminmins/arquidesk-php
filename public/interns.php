<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if ($user['role'] !== 'ADMIN_EMPRESA') {
    http_response_code(403);
    exit('Acesso restrito.');
}

ensure_intern_permissions_schema();
$companyId = (int) $user['company_id'];
$error = '';
$subscription = get_subscription($companyId);
$userLimit = plan_user_limit($subscription['plan']);

function manageable_intern(int $id, array $user): ?array
{
    $stmt = db()->prepare("select * from users where id = ? and company_id = ? and role = 'ESTAGIARIO' limit 1");
    $stmt->execute([$id, (int) $user['company_id']]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $internId = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        if (manageable_intern($internId, $user)) {
            db()->prepare('update users set active = if(active = 1, 0, 1) where id = ?')->execute([$internId]);
        }
        redirect('/interns.php?ok=1');
    }

    if ($action === 'delete') {
        if (manageable_intern($internId, $user)) {
            db()->prepare('delete from users where id = ?')->execute([$internId]);
        }
        redirect('/interns.php?ok=1');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || (!$internId && strlen($password) < 6)) {
        $error = 'Informe nome, e-mail válido e senha com pelo menos 6 caracteres.';
    } else {
        $existing = $internId ? manageable_intern($internId, $user) : null;
        if ($internId && !$existing) {
            $error = 'Estagiário não encontrado ou sem permissão para editar.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if (!$internId) {
                    $countStmt = $pdo->prepare('select count(*) from users where company_id = ?');
                    $countStmt->execute([$companyId]);
                    if ((int) $countStmt->fetchColumn() >= $userLimit) {
                        throw new RuntimeException('Seu plano inclui ' . (plan_config()[$subscription['plan']]['users'] ?? "até {$userLimit} contas") . '.');
                    }
                    $stmt = $pdo->prepare("insert into users (company_id, name, email, password_hash, role, supervisor_user_id, intern_data_scope) values (?, ?, ?, ?, 'ESTAGIARIO', null, 'COMPANY')");
                    $stmt->execute([$companyId, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $internId = (int) $pdo->lastInsertId();
                } elseif ($password !== '') {
                    if (strlen($password) < 6) {
                        throw new RuntimeException('A nova senha precisa ter pelo menos 6 caracteres.');
                    }
                    $pdo->prepare("update users set name = ?, email = ?, password_hash = ?, supervisor_user_id = null, intern_data_scope = 'COMPANY' where id = ? and company_id = ? and role = 'ESTAGIARIO'")
                        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $internId, $companyId]);
                } else {
                    $pdo->prepare("update users set name = ?, email = ?, supervisor_user_id = null, intern_data_scope = 'COMPANY' where id = ? and company_id = ? and role = 'ESTAGIARIO'")
                        ->execute([$name, $email, $internId, $companyId]);
                }

                $pdo->prepare('delete from intern_permissions where intern_user_id = ?')->execute([$internId]);
                $permissionStmt = $pdo->prepare('insert into intern_permissions (company_id, intern_user_id, tab_key, access_level) values (?, ?, ?, ?)');
                foreach (['my_day' => 'EDIT', 'projects' => 'EDIT'] as $tabKey => $level) {
                    $permissionStmt->execute([$companyId, $internId, $tabKey, $level]);
                }
                $pdo->commit();
                redirect('/interns.php?ok=1');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Não foi possível salvar. Verifique se o e-mail já está cadastrado.';
            }
        }
    }
}

$editIntern = !empty($_GET['edit']) ? manageable_intern((int) $_GET['edit'], $user) : null;
$listStmt = db()->prepare("select * from users where company_id = ? and role = 'ESTAGIARIO' order by name");
$listStmt->execute([$companyId]);
$interns = $listStmt->fetchAll();

$pageTitle = 'Estagiários';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
$form = $editIntern ?: [];
?>
<section class="grid gap-5">
    <div class="rounded-lg border border-line bg-white p-4 text-sm text-slate-600">
        Estagiários contam no limite do plano. Qualquer projetista pode atribuir tarefas e acompanhar projetos de qualquer estagiário.
    </div>
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Operação concluída.</div><?php endif; ?>
    <?php if ($error): ?><div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="grid gap-5 rounded-lg border border-line bg-white p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
        <div class="grid gap-3 md:grid-cols-3">
            <label class="grid gap-1 text-sm font-semibold">Nome
                <input class="min-h-11 rounded-md border border-line px-3" name="name" required value="<?= e($form['name'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">E-mail
                <input class="min-h-11 rounded-md border border-line px-3" type="email" name="email" required value="<?= e($form['email'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold"><?= $editIntern ? 'Nova senha (opcional)' : 'Senha' ?>
                <input class="min-h-11 rounded-md border border-line px-3" type="password" name="password" minlength="6" <?= $editIntern ? '' : 'required' ?>>
            </label>
        </div>
        <div class="flex gap-2"><button class="min-h-11 rounded-md bg-ink px-5 font-bold text-white" type="submit"><?= $editIntern ? 'Salvar alterações' : 'Criar estagiário' ?></button><?php if ($editIntern): ?><a class="inline-flex min-h-11 items-center rounded-md border border-line px-5 font-semibold" href="/interns.php">Cancelar</a><?php endif; ?></div>
    </form>

    <section class="overflow-x-auto rounded-lg border border-line bg-white">
        <table class="w-full text-left text-sm"><thead class="bg-fog"><tr><th class="p-3">Nome</th><th class="p-3">Status</th><th class="p-3 text-right">Ações</th></tr></thead><tbody>
        <?php foreach ($interns as $intern): ?><tr class="border-t border-line"><td class="p-3"><strong><?= e($intern['name']) ?></strong><span class="block text-xs text-slate-500"><?= e($intern['email']) ?></span></td><td class="p-3"><?= $intern['active'] ? 'Ativo' : 'Inativo' ?></td><td class="p-3"><div class="flex justify-end gap-2"><a class="rounded-md border border-line px-3 py-2 text-xs font-semibold" href="/interns.php?edit=<?= (int) $intern['id'] ?>">Editar</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $intern['id'] ?>"><button class="rounded-md border border-line px-3 py-2 text-xs font-semibold"><?= $intern['active'] ? 'Desativar' : 'Ativar' ?></button></form><form method="post" onsubmit="return confirm('Excluir este estagiário?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $intern['id'] ?>"><button class="rounded-md bg-red-600 px-3 py-2 text-xs font-bold text-white">Excluir</button></form></div></td></tr><?php endforeach; ?>
        <?php if (!$interns): ?><tr><td colspan="3" class="p-6 text-center text-slate-500">Nenhum estagiário cadastrado.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
