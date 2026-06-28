<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'SUPER_ADMIN') {
    $stmt = db()->prepare('update subscriptions set plan = ?, status = ?, current_period_end = ? where company_id = ?');
    $stmt->execute([$_POST['plan'], $_POST['status'], null_if_empty($_POST['current_period_end'] ?? ''), $companyId]);
    redirect('/subscription.php?ok=1');
}

// Checkout self-service: empresa assina um plano e gera cobrança recorrente no Asaas.
$checkoutError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_plan']) && $user['role'] !== 'SUPER_ADMIN') {
    require_once __DIR__ . '/../app/includes/asaas.php';

    $planKey = $_POST['subscribe_plan'];
    $plansCfg = plan_config();
    if (!isset($plansCfg[$planKey])) {
        $checkoutError = 'Plano inválido.';
    } else {
        // Carrega dados da empresa (nome, documento, email, telefone)
        $compStmt = db()->prepare('select * from companies where id = ?');
        $compStmt->execute([$companyId]);
        $company = $compStmt->fetch();

        // CNPJ/CPF: usa o enviado no formulário (se preenchido) ou o já salvo.
        $document = preg_replace('/\D/', '', (string) ($_POST['document'] ?? ''));
        if ($document === '') {
            $document = preg_replace('/\D/', '', (string) ($company['document'] ?? ''));
        }

        if (strlen($document) < 11) {
            $checkoutError = 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido para gerar a cobrança.';
        } else {
            // Salva o documento na empresa se ainda não tinha
            if (empty($company['document'])) {
                db()->prepare('update companies set document = ? where id = ?')->execute([$document, $companyId]);
            }

            $subRow = get_subscription($companyId);
            $customerId = $subRow['external_customer_id'] ?? '';

            // 1) Cria o cliente no Asaas se ainda não existir
            if ($customerId === '') {
                $cust = asaas_create_customer([
                    'name'    => $company['name'] ?? ('Empresa #' . $companyId),
                    'cpfCnpj' => $document,
                    'email'   => $company['email'] ?? ($user['email'] ?? ''),
                    'phone'   => $company['phone'] ?? '',
                ]);
                if (empty($cust['id'])) {
                    $checkoutError = 'Não foi possível criar o cadastro de cobrança. Verifique o CPF/CNPJ e tente novamente.';
                } else {
                    $customerId = $cust['id'];
                    db()->prepare('update subscriptions set external_customer_id = ? where company_id = ?')->execute([$customerId, $companyId]);
                }
            }

            // 2) Cria a assinatura recorrente
            if (!$checkoutError) {
                $value = (float) $plansCfg[$planKey]['price'];
                $desc = 'Arquidesk - Plano ' . $plansCfg[$planKey]['name'];
                $nextDue = (new DateTimeImmutable('today'))->modify('+3 days')->format('Y-m-d');
                $sub = asaas_create_subscription($customerId, $value, $desc, $nextDue);

                if (empty($sub['id'])) {
                    $checkoutError = 'Não foi possível gerar a assinatura. Tente novamente em instantes.';
                } else {
                    $payUrl = asaas_subscription_first_payment_url($sub['id']);
                    db()->prepare(
                        'update subscriptions set selected_plan_key = ?, external_subscription_id = ?, checkout_url = ?, provider = ? where company_id = ?'
                    )->execute([$planKey, $sub['id'], $payUrl, 'asaas', $companyId]);

                    // Leva direto para a página de pagamento do Asaas (Pix/boleto/cartão)
                    if ($payUrl !== '') {
                        header('Location: ' . $payUrl);
                        exit;
                    }
                    redirect('/subscription.php?pending=1');
                }
            }
        }
    }
}

$subscription = get_subscription($companyId);
$plans = plan_config();
$features = plan_included_features();
$blocked = is_subscription_blocked($subscription);
$currentPlan = $subscription['plan'] ?? 'PROFISSIONAL';
$status = $subscription['status'] ?? 'TRIAL';

// Régua de carência: fase atual da assinatura (active/grace/critical/blocked)
$subState = subscription_state($subscription);
$phase = $subState['phase'];
$graceTotal = subscription_grace_days();

// Documento (CNPJ/CPF) da empresa — se vazio, o checkout pede na hora.
$docStmt = db()->prepare('select document from companies where id = ?');
$docStmt->execute([$companyId]);
$company_document = (string) ($docStmt->fetchColumn() ?: '');
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
    <?php if (!empty($_GET['pending'])): ?>
        <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-700">Assinatura gerada! Assim que o pagamento for confirmado, seu acesso é liberado automaticamente.</div>
    <?php endif; ?>
    <?php if (!empty($checkoutError)): ?>
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($checkoutError) ?></div>
    <?php endif; ?>

    <?php if ($phase === 'blocked'): ?>
        <div class="rounded-lg border border-red-300 bg-red-50 p-5">
            <h3 class="font-bold text-red-800">Acesso bloqueado — pagamento pendente</h3>
            <p class="mt-2 text-sm text-red-700">Seu acesso aos módulos está suspenso por falta de pagamento. <strong>Seus dados continuam guardados com segurança</strong> e voltam assim que o pagamento for confirmado. Escolha um plano abaixo para regularizar.</p>
        </div>
    <?php elseif ($phase === 'critical'): ?>
        <div class="rounded-lg border border-red-300 bg-red-50 p-5">
            <h3 class="font-bold text-red-800">Atenção: faltam <?= (int) $subState['days_left'] ?> dia<?= $subState['days_left'] !== 1 ? 's' : '' ?> para o bloqueio</h3>
            <p class="mt-2 text-sm text-red-700">Seu pagamento está vencido há <?= (int) $subState['days_overdue'] ?> dia(s). Regularize agora para não perder o acesso aos módulos. Seus dados não serão apagados.</p>
        </div>
    <?php elseif ($phase === 'grace'): ?>
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h3 class="font-bold text-amber-800">Seu pagamento venceu</h3>
            <p class="mt-2 text-sm text-amber-700">Regularize para manter o acesso. Você tem mais <?= (int) $subState['days_left'] ?> dia(s) antes do bloqueio. Escolha seu plano abaixo para pagar com Pix, boleto ou cartão.</p>
        </div>
    <?php elseif ($status === 'TRIAL' && $daysLeft <= 7 && $daysLeft >= 0): ?>
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h3 class="font-bold text-amber-800">Seu teste gratuito acaba em <?= $daysLeft ?> dia<?= $daysLeft !== 1 ? 's' : '' ?></h3>
            <p class="mt-2 text-sm text-amber-700">Após esse período, os módulos serão bloqueados. Assine um plano abaixo para continuar sem interrupção.</p>
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
            <?= csrf_field() ?>
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
                <?php if ($isActive && in_array($status, ['ACTIVE'], true)): ?>
                    <div class="mt-5 flex min-h-10 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-700">Plano ativo</div>
                <?php elseif ($user['role'] === 'SUPER_ADMIN'): ?>
                    <div class="mt-5 flex min-h-10 items-center justify-center rounded-md border border-line px-4 text-sm text-slate-400">Gerenciado pelo painel admin</div>
                <?php else: ?>
                    <form method="post" class="mt-5">
                        <?= csrf_field() ?>
                        <input type="hidden" name="subscribe_plan" value="<?= e($key) ?>">
                        <?php if (empty($company_document)): ?>
                            <input name="document" inputmode="numeric" placeholder="CPF ou CNPJ (só números)" required
                                   class="mb-2 min-h-10 w-full rounded-md border border-line px-3 text-sm outline-none focus:border-ink">
                        <?php endif; ?>
                        <button type="submit" class="flex min-h-10 w-full items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">
                            <?= $isActive ? 'Pagar / Renovar' : 'Assinar ' . e($plan['name']) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="rounded-lg border border-line bg-white p-5 text-sm text-slate-500">
        <strong class="text-ink">Usuários adicionais:</strong> R$ 49/mês por usuário. Todas as funcionalidades continuam liberadas em todos os planos.
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
