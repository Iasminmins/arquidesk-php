<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
if ($user['role'] !== 'ADMIN_EMPRESA') {
    http_response_code(403);
    exit('Acesso restrito ao administrador da empresa.');
}

$companyId = (int) $user['company_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logoUrl = null_if_empty($_POST['logo_url'] ?? '');
    $coverUrl = null_if_empty($_POST['cover_image_url'] ?? '');

    if (!empty($_FILES['logo_file']['tmp_name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION) ?: 'png');
        $filename = 'logo-' . $companyId . '-' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $filename)) {
            $logoUrl = '/uploads/' . $filename;
        }
    }
    if (!empty($_FILES['cover_file']['tmp_name']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION) ?: 'png');
        $filename = 'cover-' . $companyId . '-' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $uploadDir . $filename)) {
            $coverUrl = '/uploads/' . $filename;
        }
    }

    $stmt = db()->prepare(
        'update companies set name = ?, document = ?, email = ?, phone = ?, address = ?, logo_url = ?, cover_image_url = ?, primary_color = ?, secondary_color = ? where id = ?'
    );
    $stmt->execute([
        trim($_POST['name'] ?? ''),
        null_if_empty($_POST['document'] ?? ''),
        null_if_empty($_POST['email'] ?? ''),
        null_if_empty($_POST['phone'] ?? ''),
        null_if_empty($_POST['address'] ?? ''),
        $logoUrl,
        $coverUrl,
        $_POST['primary_color'] ?: '#15201d',
        $_POST['secondary_color'] ?: '#b8664b',
        $companyId,
    ]);
    $message = 'Configurações atualizadas.';
}

$stmt = db()->prepare('select * from companies where id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

$pageTitle = 'Configurações da Empresa';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<form method="post" enctype="multipart/form-data" class="grid gap-5 rounded-lg border border-line bg-white p-4">
    <?php if ($message): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($message) ?></div><?php endif; ?>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-1 text-sm font-semibold">Nome da empresa
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="name" value="<?= e($company['name'] ?? '') ?>" required>
        </label>
        <label class="grid gap-1 text-sm font-semibold">CNPJ
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="document" value="<?= e($company['document'] ?? '') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">E-mail
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="email" value="<?= e($company['email'] ?? '') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Telefone
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="phone" value="<?= e($company['phone'] ?? '') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Logo URL
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="logo_url" value="<?= e($company['logo_url'] ?? '') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Enviar logo
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="file" accept="image/*" name="logo_file">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Imagem de capa URL
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="cover_image_url" value="<?= e($company['cover_image_url'] ?? '') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Enviar capa
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="file" accept="image/*" name="cover_file">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Cor principal
            <input class="min-h-10 rounded-md border border-line px-3" type="color" name="primary_color" value="<?= e($company['primary_color'] ?? '#15201d') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Cor secundária
            <input class="min-h-10 rounded-md border border-line px-3" type="color" name="secondary_color" value="<?= e($company['secondary_color'] ?? '#b8664b') ?>">
        </label>
    </div>
    <label class="grid gap-1 text-sm font-semibold">Endereço
        <textarea class="min-h-24 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="address"><?= e($company['address'] ?? '') ?></textarea>
    </label>
    <div class="flex justify-end"><button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar configurações</button></div>
</form>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
