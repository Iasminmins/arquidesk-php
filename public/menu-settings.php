<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if ($user['role'] !== 'PROJETISTA') {
    http_response_code(403);
    exit('Acesso restrito.');
}

ensure_nav_preferences_schema();
$companyId = (int) $user['company_id'];
$userId = (int) $user['id'];
$groups = designer_nav_groups();
$configurable = designer_configurable_nav();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $visibleKeys = array_values(array_intersect((array) ($_POST['visible'] ?? []), array_keys($configurable)));
    $hiddenKeys = array_values(array_diff(array_keys($configurable), $visibleKeys));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('delete from user_nav_preferences where user_id = ? and company_id = ?')->execute([$userId, $companyId]);
        $stmt = $pdo->prepare('insert into user_nav_preferences (company_id, user_id, nav_key, visible) values (?, ?, ?, 0)');
        foreach ($hiddenKeys as $navKey) {
            $stmt->execute([$companyId, $userId, $navKey]);
        }
        $pdo->commit();
        redirect('/menu-settings.php?ok=1');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

$hiddenKeys = hidden_nav_keys_for_user($userId, $companyId);
$pageTitle = 'Configurações do menu';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="mx-auto grid max-w-4xl gap-5">
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">Menu atualizado.</div><?php endif; ?>

    <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm text-blue-950">
        <h2 class="font-bold">Como funcionam as abas?</h2>
        <p class="mt-2"><strong>Fluxo em sequência:</strong> Projeto → Negociação → Conferência → Montagem → Assistência → Finalizados. Essas abas formam uma escadinha: uma etapa vem depois da outra.</p>
        <p class="mt-2"><strong>Abas independentes:</strong> funcionam separadamente e não representam uma etapa do projeto.</p>
        <p class="mt-2">Ao ocultar uma aba, somente o atalho lateral desaparece. Nenhum dado é apagado e o fluxo continua funcionando normalmente.</p>
    </div>

    <form method="post" class="grid gap-5">
        <?= csrf_field() ?>
        <section class="rounded-lg border border-line bg-white p-5">
            <h2 class="text-lg font-bold">Fluxo em sequência</h2>
            <p class="mt-1 text-sm text-slate-500">Escolha quais degraus do fluxo deseja manter no menu.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($groups['flow'] as $href => $label): ?>
                    <label class="flex min-h-12 items-center gap-3 rounded-md border border-line px-4 py-3 text-sm font-semibold">
                        <input class="h-4 w-4" type="checkbox" name="visible[]" value="<?= e($href) ?>" <?= in_array($href, $hiddenKeys, true) ? '' : 'checked' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-lg border border-line bg-white p-5">
            <h2 class="text-lg font-bold">Abas independentes</h2>
            <p class="mt-1 text-sm text-slate-500">Essas áreas podem ser exibidas ou ocultadas individualmente.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($groups['independent'] as $href => $label): ?>
                    <label class="flex min-h-12 items-center gap-3 rounded-md border border-line px-4 py-3 text-sm font-semibold">
                        <input class="h-4 w-4" type="checkbox" name="visible[]" value="<?= e($href) ?>" <?= in_array($href, $hiddenKeys, true) ? '' : 'checked' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="flex items-center justify-between gap-3 rounded-lg border border-line bg-white p-4">
            <span class="text-sm text-slate-500">Configurações do menu permanece sempre visível.</span>
            <button class="min-h-11 rounded-md bg-ink px-5 font-bold text-white" type="submit">Salvar menu</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
