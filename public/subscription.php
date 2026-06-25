<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'SUPER_ADMIN') {
    $stmt = db()->prepare('update subscriptions set plan = ?, status = ?, current_period_end = ? where company_id = ?');
    $stmt->execute([$_POST['plan'], $_POST['status'], null_if_empty($_POST['current_period_end'] ?? ''), $companyId]);
    redirect('/subscription.php?ok=1');
}

$subscription = get_subscription($companyId);
$plans = plan_config();
$features = plan_included_features();
$blocked = is_subscription_blocked($subscription);

$pageTitle = 'Assinatura / Plano';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Assinatura atualizada.</div>
    <?php endif; ?>
    <?php if ($blocked || !empty($_GET['blocked'])): ?>
        <div class="rounded-lg border border-red-300 bg-red-50 p-5 text-red-700">
            Assinatura cancelada ou bloqueada. Regularize o plano para liberar os módulos principais.
        </div>
    <?php endif; ?>

    <div class="grid gap-3 md:grid-cols-3">
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Plano atual</span><strong class="mt-2 block text-2xl"><?= e(plan_label($subscription['plan'])) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Status</span><strong class="mt-2 block text-2xl"><?= e($subscription['status']) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Período atual</span><strong class="mt-2 block text-2xl"><?= e(date_br($subscription['current_period_end'] ?: $subscription['trial_ends_at'])) ?></strong></article>
    </div>

    <?php if ($user['role'] === 'SUPER_ADMIN'): ?>
        <form method="post" class="grid gap-4 rounded-lg border border-line bg-white p-4 md:grid-cols-3 md:items-end">
            <label class="grid gap-1 text-sm font-semibold">Plano
                <select class="min-h-10 rounded-md border border-line px-3" name="plan">
                    <?php foreach ($plans as $key => $plan): ?><option value="<?= e($key) ?>" <?= $subscription['plan'] === $key ? 'selected' : '' ?>><?= e($plan['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Status
                <select class="min-h-10 rounded-md border border-line px-3" name="status">
                    <?php foreach (['TRIAL', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'BLOCKED'] as $status): ?><option value="<?= $status ?>" <?= $subscription['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Vencimento
                <input class="min-h-10 rounded-md border border-line px-3" type="date" name="current_period_end" value="<?= e($subscription['current_period_end'] ?? '') ?>">
            </label>
            <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white md:col-span-3" type="submit">Salvar assinatura</button>
        </form>
    <?php endif; ?>

    <div class="rounded-lg border border-line bg-white p-5">
        <h2 class="font-bold">Planos Arquidesk</h2>
        <p class="mt-1 text-sm text-slate-500">Todos os planos incluem a plataforma completa. Escolha apenas de acordo com o tamanho da sua equipe.</p>
        <p class="mt-2 text-sm font-bold text-emerald-700">1 mês grátis para começar.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <?php foreach ($plans as $key => $plan): ?>
            <article class="relative rounded-lg border bg-white p-5 shadow-sm <?= $plan['highlighted'] ? 'border-emerald-600 ring-2 ring-emerald-600/20' : 'border-line' ?>">
                <?php if ($plan['badge']): ?>
                    <span class="absolute right-4 top-4 rounded-full bg-orange-500 px-3 py-1 text-xs font-black text-white"><?= e($plan['badge']) ?></span>
                <?php endif; ?>
                <h3 class="text-xl font-black"><?= e($plan['name']) ?></h3>
                <p class="mt-3 text-3xl font-black"><?= e($plan['priceLabel']) ?></p>
                <p class="mt-2 text-sm font-bold text-emerald-700"><?= e($plan['users']) ?></p>
                <p class="mt-3 text-sm text-slate-600"><?= e($plan['description']) ?></p>
                <ul class="mt-5 grid gap-2 text-sm text-slate-600">
                    <?php foreach ($features as $feature): ?>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 text-emerald-600">✓</span> <?= e($feature) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="/setup.php" class="mt-5 flex min-h-10 items-center justify-center rounded-md px-4 text-sm font-bold <?= $plan['highlighted'] ? 'bg-ink text-white' : 'border border-line hover:bg-fog' ?>">Começar com 1 mês grátis</a>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="rounded-lg border border-line bg-white p-5 text-sm text-slate-500">
        <strong class="text-ink">Usuários adicionais:</strong> R$ 49/mês por usuário. Todas as funcionalidades continuam liberadas em todos os planos.
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
