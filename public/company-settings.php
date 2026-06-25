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
    $stmt = db()->prepare(
        'update companies set name = ?, document = ?, email = ?, phone = ?, address = ?, logo_url = ?, cover_image_url = ?, primary_color = ?, secondary_color = ? where id = ?'
    );
    $stmt->execute([
        trim($_POST['name'] ?? ''),
        null_if_empty($_POST['document'] ?? ''),
        null_if_empty($_POST['email'] ?? ''),
        null_if_empty($_POST['phone'] ?? ''),
        null_if_empty($_POST['address'] ?? ''),
        null_if_empty($_POST['logo_url'] ?? ''),
        null_if_empty($_POST['cover_image_url'] ?? ''),
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
<form method="post" class="grid gap-5 rounded-lg border border-line bg-white p-4">
    <?php if ($message): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($message) ?></div><?php endif; ?>
    <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ([
            'name' => 'Nome da empresa',
            'document' => 'CNPJ',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'logo_url' => 'Logo URL',
            'cover_image_url' => 'Imagem de capa URL',
        ] as $field => $label): ?>
            <label class="grid gap-1 text-sm font-semibold"><?= e($label) ?>
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="<?= e($field) ?>" value="<?= e($company[$field] ?? '') ?>" <?= $field === 'name' ? 'required' : '' ?>>
            </label>
        <?php endforeach; ?>
        <label class="grid gap-1 text-sm font-semibold">Cor principal
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="color" name="primary_color" value="<?= e($company['primary_color'] ?? '#15201d') ?>">
        </label>
        <label class="grid gap-1 text-sm font-semibold">Cor secundaria
            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="color" name="secondary_color" value="<?= e($company['secondary_color'] ?? '#b8664b') ?>">
        </label>
    </div>
    <label class="grid gap-1 text-sm font-semibold">Endereço
        <textarea class="min-h-24 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="address"><?= e($company['address'] ?? '') ?></textarea>
    </label>
    <div class="flex justify-end"><button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar configurações</button></div>
</form>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
