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
$currentPlan = $subscription['plan'] ?? 'PROFISSIONAL';
$status = $subscription['status'] ?? 'TRIAL';
// Calculate days remaining
$endDate = $subscription['current_period_end'] ?: $subscription['trial_ends_at'];
$daysLeft = 0;
$expired = false;
if ($endDate) {
    $diff = (new DateTime($endDate))->diff(new DateTime('today'));
    $daysLeft = $diff->invert ? $diff->days : -$diff->days;
    $expired = $daysLeft < 0;
}

$statusLabel = match($status) {
    'TRIAL' => 'Período de teste',
    'ACTIVE' => 'Ativo',
    'PAST_DUE' => 'Pagamento pendente',
    'CANCELED' => 'Cancelado',
    'BLOCKED' => 'Bloqueado',
    default => $status,
};
$statusColor = match($status) {
    'TRIAL' => 'text-amber-700 bg-amber-100',
    'ACTIVE' => 'text-emerald-700 bg-emerald-100',
    'PAST_DUE' => 'text-orange-700 bg-orange-100',
    'CANCELED', 'BLOCKED' => 'text-red-700 bg-red-100',
    default => 'text-slate-700 bg-slate-100',
};

// Count users
$userCountStmt = db()->prepare('select count(*) from users where company_id = ?');
$userCountStmt->execute([$companyId]);
$userCount = (int) $userCountStmt->fetchColumn();
$userLimit = plan_user_limit($currentPlan);

$pageTitle = 'Assinatura / Plano';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Assinatura atualizada.</div>
    <?php endif; ?>

    <?php if ($blocked || $expired): ?>
        <div class="rounded-lg border border-red-300 bg-red-50 p-5">
            <h3 class="font-bold text-red-800">Sua assinatura expirou</h3>
            <p class="mt-2 text-sm text-red-700">Regularize seu plano para continuar utilizando todos os módulos. Entre em contato pelo WhatsApp para renovar.</p>
            <a href="https://wa.me/5524999327549" target="_blank" class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Falar sobre renovação
            </a>
        </div>
    <?php elseif ($status === 'TRIAL' && $daysLeft <= 7 && $daysLeft >= 0): ?>
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h3 class="font-bold text-amber-800">Seu teste gratuito acaba em <?= $daysLeft ?> dia<?= $daysLeft !== 1 ? 's' : '' ?></h3>
            <p class="mt-2 text-sm text-amber-700">Após esse período, os módulos serão bloqueados. Entre em contato para ativar seu plano.</p>
            <a href="https://wa.me/5524999327549" target="_blank" class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">
                Ativar plano agora
            </a>
        </div>
    <?php endif; ?>

    <!-- CARDS DO PLANO ATUAL -->
    <div class="grid gap-3 md:grid-cols-4">
        <article class="rounded-lg border border-line bg-white p-5">
            <span class="text-sm text-slate-500">Plano atual</span>
            <strong class="mt-2 block text-2xl"><?= e(plan_label($currentPlan)) ?></strong>
            <span class="mt-1 block text-sm text-slate-500"><?= e($plans[$currentPlan]['priceLabel'] ?? '') ?></span>
        </article>
        <article class="rounded-lg border border-line bg-white p-5">
            <span class="text-sm text-slate-500">Status</span>
            <strong class="mt-2 block"><span class="rounded-full px-3 py-1 text-sm font-bold <?= $statusColor ?>"><?= e($statusLabel) ?></span></strong>
        </article>
        <article class="rounded-lg border border-line bg-white p-5">
            <span class="text-sm text-slate-500"><?= $status === 'TRIAL' ? 'Trial expira em' : 'Próximo vencimento' ?></span>
            <strong class="mt-2 block text-2xl <?= $daysLeft <= 5 && $daysLeft >= 0 ? 'text-red-600' : '' ?>"><?= $daysLeft >= 0 ? $daysLeft . ' dias' : 'Expirado' ?></strong>
            <span class="mt-1 block text-sm text-slate-500"><?= e(date_br($endDate)) ?></span>
        </article>
        <article class="rounded-lg border border-line bg-white p-5">
            <span class="text-sm text-slate-500">Usuários</span>
            <strong class="mt-2 block text-2xl"><?= $userCount ?> / <?= $userLimit ?></strong>
            <span class="mt-1 block text-sm text-slate-500"><?= e($plans[$currentPlan]['users'] ?? '') ?></span>
        </article>
    </div>

    <?php if ($user['role'] === 'SUPER_ADMIN'): ?>
        <form method="post" class="grid gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4 md:grid-cols-3 md:items-end">
            <label class="grid gap-1 text-sm font-semibold">Plano
                <select class="min-h-10 rounded-md border border-line bg-white px-3" name="plan">
                    <?php foreach ($plans as $key => $plan): ?><option value="<?= e($key) ?>" <?= $currentPlan === $key ? 'selected' : '' ?>><?= e($plan['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Status
                <select class="min-h-10 rounded-md border border-line bg-white px-3" name="status">
                    <?php foreach (['TRIAL', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'BLOCKED'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold">Vencimento
                <input class="min-h-10 rounded-md border border-line bg-white px-3" type="date" name="current_period_end" value="<?= e($subscription['current_period_end'] ?? '') ?>">
            </label>
            <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white md:col-span-3" type="submit">Salvar (SUPER_ADMIN)</button>
        </form>
    <?php endif; ?>

    <!-- PLANOS -->
    <div class="rounded-lg border border-line bg-white p-5">
        <h2 class="font-bold">Comparar planos</h2>
        <p class="mt-1 text-sm text-slate-500">Todos os planos incluem a plataforma completa. A diferença é apenas o número de usuários.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <?php foreach ($plans as $key => $plan):
            $isActive = $currentPlan === $key;
        ?>
            <article class="relative rounded-lg border bg-white p-5 shadow-sm <?= $isActive ? 'border-emerald-600 ring-2 ring-emerald-600/20' : ($plan['highlighted'] ? 'border-emerald-300' : 'border-line') ?>">
                <?php if ($isActive): ?>
                    <span class="absolute right-4 top-4 rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white">Seu plano</span>
                <?php elseif ($plan['badge']): ?>
                    <span class="absolute right-4 top-4 rounded-full bg-orange-500 px-3 py-1 text-xs font-bold text-white"><?= e($plan['badge']) ?></span>
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
                <?php if ($isActive): ?>
                    <div class="mt-5 flex min-h-10 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-700">Plano ativo</div>
                <?php else: ?>
                    <a href="https://wa.me/5524999327549?text=<?= urlencode('Olá! Gostaria de mudar para o plano ' . $plan['name'] . ' do Arquidesk.') ?>" target="_blank" class="mt-5 flex min-h-10 items-center justify-center rounded-md border border-line px-4 text-sm font-bold hover:bg-fog">Mudar para <?= e($plan['name']) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="rounded-lg border border-line bg-white p-5 text-sm text-slate-500">
        <strong class="text-ink">Usuários adicionais:</strong> R$ 49/mês por usuário. Todas as funcionalidades continuam liberadas em todos os planos.
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
