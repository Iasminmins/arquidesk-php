<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
if ($user['role'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    exit('Acesso restrito ao SUPER_ADMIN.');
}

$view = $_GET['view'] ?? 'dashboard';
$pageTitle = match ($view) {
    'companies' => 'Empresas',
    'plans' => 'Planos',
    'subscriptions' => 'Assinaturas',
    'users' => 'Usuários Globais',
    'settings' => 'Configurações SaaS',
    default => 'Dashboard SaaS',
};

$companies = db()->query('select * from companies order by created_at desc')->fetchAll();
$subs = db()->query('select s.*, c.name as company_name, c.email as company_email from subscriptions s join companies c on c.id = s.company_id order by s.created_at desc')->fetchAll();
$users = db()->query('select u.*, c.name as company_name from users u left join companies c on c.id = u.company_id order by u.created_at desc')->fetchAll();

$activeSubs = array_filter($subs, fn($sub) => $sub['status'] === 'ACTIVE');
$blockedSubs = array_filter($subs, fn($sub) => in_array($sub['status'], ['BLOCKED', 'CANCELED'], true));

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if ($view === 'dashboard'): ?>
        <div class="grid gap-3 md:grid-cols-3">
            <article class="rounded-lg border border-line bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">Empresas cadastradas</p><strong class="mt-2 block text-2xl"><?= count($companies) ?></strong></article>
            <article class="rounded-lg border border-line bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">Assinaturas ativas</p><strong class="mt-2 block text-2xl"><?= count($activeSubs) ?></strong></article>
            <article class="rounded-lg border border-line bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">Bloqueadas</p><strong class="mt-2 block text-2xl"><?= count($blockedSubs) ?></strong></article>
        </div>
    <?php endif; ?>

    <?php if (in_array($view, ['dashboard', 'companies'], true)): ?>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Empresas</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Empresa</th><th class="p-3">E-mail</th><th class="p-3">Documento</th><th class="p-3">Criada em</th></tr></thead>
                    <tbody><?php foreach ($companies as $company): ?><tr class="border-t border-line"><td class="p-3"><?= e($company['name']) ?></td><td class="p-3"><?= e($company['email'] ?: '-') ?></td><td class="p-3"><?= e($company['document'] ?: '-') ?></td><td class="p-3"><?= e(date_br($company['created_at'])) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (in_array($view, ['dashboard', 'subscriptions'], true)): ?>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Assinaturas</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Empresa</th><th class="p-3">E-mail</th><th class="p-3">Plano</th><th class="p-3">Status</th><th class="p-3">Trial</th></tr></thead>
                    <tbody><?php foreach ($subs as $sub): ?><tr class="border-t border-line"><td class="p-3"><?= e($sub['company_name']) ?></td><td class="p-3"><?= e($sub['company_email'] ?: '-') ?></td><td class="p-3"><?= e($sub['plan']) ?></td><td class="p-3"><?= e($sub['status']) ?></td><td class="p-3"><?= e(date_br($sub['trial_ends_at'])) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view === 'users'): ?>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Usuários Globais</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Nome</th><th class="p-3">E-mail</th><th class="p-3">Empresa</th><th class="p-3">Role</th><th class="p-3">Status</th></tr></thead>
                    <tbody><?php foreach ($users as $item): ?><tr class="border-t border-line"><td class="p-3"><?= e($item['name']) ?></td><td class="p-3"><?= e($item['email']) ?></td><td class="p-3"><?= e($item['company_name'] ?: '-') ?></td><td class="p-3"><?= e($item['role']) ?></td><td class="p-3"><?= $item['active'] ? 'Ativo' : 'Inativo' ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view === 'plans' || $view === 'settings'): ?>
        <section class="rounded-lg border border-line bg-white p-5">
            <h2 class="font-bold"><?= e($pageTitle) ?></h2>
            <p class="mt-2 text-sm text-slate-600">Esta área replica o painel SaaS do app original. A edição avançada pode ser expandida conforme a operação de planos e cobrança.</p>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
