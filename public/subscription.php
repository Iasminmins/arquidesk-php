<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'ADMIN_EMPRESA') {
    $stmt = db()->prepare('update subscriptions set plan = ?, status = ?, current_period_end = ? where company_id = ?');
    $stmt->execute([$_POST['plan'], $_POST['status'], null_if_empty($_POST['current_period_end'] ?? ''), $companyId]);
    redirect('/subscription.php?ok=1');
}

$stmt = db()->prepare('select * from subscriptions where company_id = ? limit 1');
$stmt->execute([$companyId]);
$subscription = $stmt->fetch();
if (!$subscription) {
    db()->prepare("insert into subscriptions (company_id, plan, status, trial_ends_at) values (?, 'PROFISSIONAL', 'TRIAL', date_add(curdate(), interval 14 day))")->execute([$companyId]);
    $stmt->execute([$companyId]);
    $subscription = $stmt->fetch();
}

$pageTitle = 'Assinatura / Plano';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Assinatura atualizada.</div><?php endif; ?>
    <div class="grid gap-3 md:grid-cols-3">
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Plano atual</span><strong class="mt-2 block text-2xl"><?= e($subscription['plan']) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Status</span><strong class="mt-2 block text-2xl"><?= e($subscription['status']) ?></strong></article>
        <article class="rounded-lg border border-line bg-white p-4"><span class="text-sm text-slate-500">Vencimento</span><strong class="mt-2 block text-2xl"><?= e(date_br($subscription['current_period_end'] ?: $subscription['trial_ends_at'])) ?></strong></article>
    </div>

    <?php if ($user['role'] === 'ADMIN_EMPRESA'): ?>
        <form method="post" class="grid gap-4 rounded-lg border border-line bg-white p-4 md:grid-cols-3 md:items-end">
            <label class="grid gap-1 text-sm font-semibold">Plano
                <select class="min-h-10 rounded-md border border-line px-3" name="plan">
                    <?php foreach (['ESSENCIAL', 'PROFISSIONAL', 'PREMIUM'] as $plan): ?><option value="<?= $plan ?>" <?= $subscription['plan'] === $plan ? 'selected' : '' ?>><?= $plan ?></option><?php endforeach; ?>
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

    <div class="grid gap-4 lg:grid-cols-3">
        <?php foreach ([['Start', 'R$ 149/mês', 'Até 3 usuários'], ['Profissional', 'R$ 297/mês', 'Até 8 usuários'], ['Business', 'R$ 497/mês', 'Até 15 usuários']] as $plan): ?>
            <article class="rounded-lg border border-line bg-white p-5"><h3 class="text-xl font-black"><?= e($plan[0]) ?></h3><p class="mt-3 text-3xl font-black"><?= e($plan[1]) ?></p><p class="mt-2 text-sm font-bold text-slate-600"><?= e($plan[2]) ?></p><p class="mt-4 text-sm text-slate-600">Plataforma completa com projetos, etapas, financeiro, metas e equipe.</p></article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>

