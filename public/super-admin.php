<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
if ($user['role'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    exit('Acesso restrito ao SUPER_ADMIN.');
}

function sa_plan_options(): array
{
    return [
        'ESSENCIAL' => ['name' => 'Start', 'price' => 149, 'users' => 4],
        'PROFISSIONAL' => ['name' => 'Profissional', 'price' => 297, 'users' => 8],
        'PREMIUM' => ['name' => 'Business', 'price' => 497, 'users' => 15],
    ];
}

function sa_plan_label(?string $plan): string
{
    $plans = sa_plan_options();
    return $plans[$plan]['name'] ?? ($plan ?: '-');
}

function sa_plan_price(?string $plan): float
{
    $plans = sa_plan_options();
    return (float) ($plans[$plan]['price'] ?? 0);
}

function sa_status_badge(string $status): string
{
    return match ($status) {
        'ACTIVE' => 'bg-emerald-100 text-emerald-700',
        'TRIAL' => 'bg-amber-100 text-amber-700',
        'PAST_DUE' => 'bg-orange-100 text-orange-700',
        'CANCELED', 'BLOCKED' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-700',
    };
}

function sa_redirect(string $view, ?int $companyId = null, string $extra = ''): never
{
    $path = '/super-admin.php?view=' . urlencode($view);
    if ($companyId) {
        $path .= '&company_id=' . $companyId;
    }
    if ($extra !== '') {
        $path .= '&' . ltrim($extra, '&');
    }
    redirect($path);
}

$view = $_GET['view'] ?? 'dashboard';
$companyId = (int) ($_GET['company_id'] ?? 0);
$message = '';
$generatedPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetCompanyId = (int) ($_POST['company_id'] ?? 0);

    if ($action === 'save_subscription' && $targetCompanyId > 0) {
        $plan = $_POST['plan'] ?? 'PROFISSIONAL';
        $status = $_POST['status'] ?? 'TRIAL';
        $periodEnd = null_if_empty($_POST['current_period_end'] ?? '');
        $trialEnd = null_if_empty($_POST['trial_ends_at'] ?? '');
        $validPlans = array_keys(sa_plan_options());
        $validStatuses = ['TRIAL', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'BLOCKED'];

        if (!in_array($plan, $validPlans, true)) {
            $plan = 'PROFISSIONAL';
        }
        if (!in_array($status, $validStatuses, true)) {
            $status = 'TRIAL';
        }

        $exists = db()->prepare('select id from subscriptions where company_id = ? limit 1');
        $exists->execute([$targetCompanyId]);
        if ($exists->fetchColumn()) {
            db()->prepare('update subscriptions set plan = ?, status = ?, current_period_end = ?, trial_ends_at = ? where company_id = ?')
                ->execute([$plan, $status, $periodEnd, $trialEnd, $targetCompanyId]);
        } else {
            db()->prepare('insert into subscriptions (company_id, plan, status, current_period_end, trial_ends_at) values (?, ?, ?, ?, ?)')
                ->execute([$targetCompanyId, $plan, $status, $periodEnd, $trialEnd]);
        }
        sa_redirect('company', $targetCompanyId, 'ok=subscription');
    }

    if ($action === 'set_subscription_status' && $targetCompanyId > 0) {
        $status = $_POST['status'] ?? 'TRIAL';
        if (!in_array($status, ['TRIAL', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'BLOCKED'], true)) {
            $status = 'TRIAL';
        }
        $exists = db()->prepare('select id from subscriptions where company_id = ? limit 1');
        $exists->execute([$targetCompanyId]);
        if ($exists->fetchColumn()) {
            db()->prepare('update subscriptions set status = ? where company_id = ?')->execute([$status, $targetCompanyId]);
        } else {
            db()->prepare('insert into subscriptions (company_id, plan, status, trial_ends_at) values (?, ?, ?, date_add(curdate(), interval 30 day))')
                ->execute([$targetCompanyId, 'PROFISSIONAL', $status]);
        }
        sa_redirect($view === 'company' ? 'company' : 'companies', $view === 'company' ? $targetCompanyId : null, 'ok=status');
    }

    if ($action === 'reset_password') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $targetUserStmt = db()->prepare('select id, company_id from users where id = ? limit 1');
        $targetUserStmt->execute([$targetUserId]);
        $targetUser = $targetUserStmt->fetch();
        if ($targetUser) {
            $generatedPassword = 'Arquidesk@' . random_int(100000, 999999);
            db()->prepare('update users set password_hash = ? where id = ?')->execute([password_hash($generatedPassword, PASSWORD_DEFAULT), $targetUserId]);
            $message = 'Senha redefinida. Nova senha temporária: ' . $generatedPassword;
            $companyId = (int) $targetUser['company_id'];
            $view = 'company';
        }
    }

    if ($action === 'toggle_user') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $targetUserStmt = db()->prepare('select id, company_id from users where id = ? and role != ? limit 1');
        $targetUserStmt->execute([$targetUserId, 'SUPER_ADMIN']);
        $targetUser = $targetUserStmt->fetch();
        if ($targetUser) {
            db()->prepare('update users set active = if(active = 1, 0, 1) where id = ?')->execute([$targetUserId]);
            sa_redirect('company', (int) $targetUser['company_id'], 'ok=user');
        }
    }
}

$pageTitle = match ($view) {
    'companies' => 'Empresas',
    'company' => 'Detalhes da Empresa',
    'subscriptions' => 'Assinaturas',
    'users' => 'Usuários Globais',
    default => 'Dashboard SaaS',
};

$companySql = "
    select c.*,
           coalesce(s.plan, 'PROFISSIONAL') as plan,
           coalesce(s.status, 'TRIAL') as subscription_status,
           s.current_period_end,
           s.trial_ends_at,
           (select count(*) from users u where u.company_id = c.id) as users_total,
           (select count(*) from users u where u.company_id = c.id and u.active = 1) as users_active,
           (select count(*) from client_projects p where p.company_id = c.id) as projects_total,
           (select count(*) from future_clients fc where fc.company_id = c.id and fc.status not in ('CONVERTIDO','PERDIDO')) as future_clients_active,
           (select coalesce(sum(fs.sold_value), 0) from financial_sales fs where fs.company_id = c.id and fs.sale_date between date_format(curdate(), '%Y-%m-01') and last_day(curdate())) as sales_month,
           (select coalesce(sum(fp.amount), 0) from financial_payments fp where fp.company_id = c.id and fp.payment_date between date_format(curdate(), '%Y-%m-01') and last_day(curdate())) as received_month,
           (select max(u.created_at) from users u where u.company_id = c.id) as last_user_at,
           (select max(p.updated_at) from client_projects p where p.company_id = c.id) as last_project_at,
           (select max(fs.updated_at) from financial_sales fs where fs.company_id = c.id) as last_sale_at
      from companies c
      left join subscriptions s on s.company_id = c.id
     order by c.created_at desc";
$companies = db()->query($companySql)->fetchAll();

$totalMrr = 0;
$trialPotential = 0;
$activeCompanies = 0;
$trialCompanies = 0;
$blockedCompanies = 0;
$totalUsers = 0;
$totalProjects = 0;
$totalReceivedMonth = 0;

foreach ($companies as $company) {
    $status = $company['subscription_status'];
    $price = sa_plan_price($company['plan']);
    if ($status === 'ACTIVE') {
        $totalMrr += $price;
        $activeCompanies++;
    }
    if ($status === 'TRIAL') {
        $trialPotential += $price;
        $trialCompanies++;
    }
    if (in_array($status, ['BLOCKED', 'CANCELED'], true)) {
        $blockedCompanies++;
    }
    $totalUsers += (int) $company['users_total'];
    $totalProjects += (int) $company['projects_total'];
    $totalReceivedMonth += (float) $company['received_month'];
}

$selectedCompany = null;
$companyUsers = [];
$globalUsers = [];
$recentProjects = [];
$recentSales = [];
$recentImports = [];

if ($view === 'users') {
    $globalUsers = db()->query(
        'select u.*, c.name as company_name, c.email as company_email
           from users u
           left join companies c on c.id = u.company_id
          order by u.created_at desc'
    )->fetchAll();
}

if ($companyId > 0) {
    foreach ($companies as $company) {
        if ((int) $company['id'] === $companyId) {
            $selectedCompany = $company;
            break;
        }
    }

    if ($selectedCompany) {
        $usersStmt = db()->prepare('select * from users where company_id = ? order by active desc, role, name');
        $usersStmt->execute([$companyId]);
        $companyUsers = $usersStmt->fetchAll();

        $projectsStmt = db()->prepare('select p.*, u.name as designer_name from client_projects p left join users u on u.id = p.designer_id where p.company_id = ? order by p.updated_at desc, p.created_at desc limit 8');
        $projectsStmt->execute([$companyId]);
        $recentProjects = $projectsStmt->fetchAll();

        $salesStmt = db()->prepare('select * from financial_sales where company_id = ? order by sale_date desc, id desc limit 8');
        $salesStmt->execute([$companyId]);
        $recentSales = $salesStmt->fetchAll();

        $importsStmt = db()->prepare('select * from import_batches where company_id = ? order by created_at desc limit 5');
        $importsStmt->execute([$companyId]);
        $recentImports = $importsStmt->fetchAll();
    }
}

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">Operação concluída.</div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-lg border border-line bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">MRR ativo</p>
            <strong class="mt-2 block text-2xl"><?= money_br($totalMrr) ?></strong>
        </article>
        <article class="rounded-lg border border-line bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Potencial em trial</p>
            <strong class="mt-2 block text-2xl"><?= money_br($trialPotential) ?></strong>
        </article>
        <article class="rounded-lg border border-line bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Empresas</p>
            <strong class="mt-2 block text-2xl"><?= count($companies) ?></strong>
            <span class="text-xs text-slate-500"><?= $activeCompanies ?> ativas · <?= $trialCompanies ?> trial · <?= $blockedCompanies ?> bloqueadas</span>
        </article>
        <article class="rounded-lg border border-line bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Usuários na base</p>
            <strong class="mt-2 block text-2xl"><?= $totalUsers ?></strong>
        </article>
        <article class="rounded-lg border border-line bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Recebido nas empresas</p>
            <strong class="mt-2 block text-2xl"><?= money_br($totalReceivedMonth) ?></strong>
        </article>
    </div>

    <div class="grid gap-2 rounded-lg border border-line bg-white p-2 text-sm font-semibold sm:inline-grid sm:w-fit sm:grid-cols-4">
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'dashboard' || $view === '' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/super-admin.php">Visão geral</a>
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'companies' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/super-admin.php?view=companies">Empresas</a>
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'subscriptions' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/super-admin.php?view=subscriptions">Assinaturas</a>
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'users' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/super-admin.php?view=users">Usuários</a>
    </div>

    <?php if ($view === 'users'): ?>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-col gap-2 border-b border-line p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold">Usuários Globais</h2>
                    <p class="text-sm text-slate-500">Todos os usuários cadastrados por empresa.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-fog text-xs uppercase text-slate-500">
                        <tr>
                            <th class="p-3">Usuário</th>
                            <th class="p-3">Empresa</th>
                            <th class="p-3">Função</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Criado em</th>
                            <th class="p-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$globalUsers): ?>
                            <tr><td colspan="6" class="p-6 text-center text-slate-500">Nenhum usuário cadastrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($globalUsers as $item): ?>
                            <tr class="border-t border-line">
                                <td class="p-3">
                                    <strong><?= e($item['name']) ?></strong>
                                    <span class="block text-xs text-slate-500"><?= e($item['email']) ?></span>
                                </td>
                                <td class="p-3">
                                    <?= e($item['company_name'] ?: 'Sem empresa') ?>
                                    <?php if ($item['company_email']): ?><span class="block text-xs text-slate-500"><?= e($item['company_email']) ?></span><?php endif; ?>
                                </td>
                                <td class="p-3"><?= e(role_label($item['role'])) ?></td>
                                <td class="p-3"><?= $item['active'] ? 'Ativo' : 'Inativo' ?></td>
                                <td class="p-3"><?= e(date_br($item['created_at'])) ?></td>
                                <td class="p-3">
                                    <div class="flex justify-end gap-2">
                                        <?php if ($item['company_id']): ?>
                                            <a class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" href="/super-admin.php?view=company&company_id=<?= (int) $item['company_id'] ?>">Empresa</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view !== 'company' && $view !== 'users'): ?>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-col gap-2 border-b border-line p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold">Quem está usando a plataforma</h2>
                    <p class="text-sm text-slate-500">Empresas, uso, plano, receita e ações rápidas.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] text-left text-sm">
                    <thead class="bg-fog text-xs uppercase text-slate-500">
                        <tr>
                            <th class="p-3">Empresa</th>
                            <th class="p-3">Plano</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Usuários</th>
                            <th class="p-3">Projetos</th>
                            <th class="p-3">Clientes futuros</th>
                            <th class="p-3">Vendas mês</th>
                            <th class="p-3">Recebido mês</th>
                            <th class="p-3">Última atividade</th>
                            <th class="p-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$companies): ?>
                            <tr><td colspan="10" class="p-6 text-center text-slate-500">Nenhuma empresa cadastrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($companies as $company): ?>
                            <?php
                            $lastActivity = max(array_filter([
                                $company['last_user_at'],
                                $company['last_project_at'],
                                $company['last_sale_at'],
                                $company['updated_at'],
                                $company['created_at'],
                            ]));
                            ?>
                            <tr class="border-t border-line align-top">
                                <td class="p-3">
                                    <strong><?= e($company['name']) ?></strong>
                                    <span class="block text-xs text-slate-500"><?= e($company['email'] ?: '-') ?></span>
                                    <span class="block text-xs text-slate-400">Criada em <?= e(date_br($company['created_at'])) ?></span>
                                </td>
                                <td class="p-3">
                                    <strong><?= e(sa_plan_label($company['plan'])) ?></strong>
                                    <span class="block text-xs text-slate-500"><?= money_br(sa_plan_price($company['plan'])) ?>/mês</span>
                                </td>
                                <td class="p-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold <?= sa_status_badge($company['subscription_status']) ?>"><?= e($company['subscription_status']) ?></span>
                                    <span class="mt-1 block text-xs text-slate-500">Vence: <?= e(date_br($company['current_period_end'] ?: $company['trial_ends_at'])) ?></span>
                                </td>
                                <td class="p-3"><?= (int) $company['users_active'] ?> ativos / <?= (int) $company['users_total'] ?></td>
                                <td class="p-3"><?= (int) $company['projects_total'] ?></td>
                                <td class="p-3"><?= (int) $company['future_clients_active'] ?></td>
                                <td class="p-3"><?= money_br($company['sales_month']) ?></td>
                                <td class="p-3"><?= money_br($company['received_month']) ?></td>
                                <td class="p-3"><?= e(date_br($lastActivity)) ?></td>
                                <td class="p-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" href="/super-admin.php?view=company&company_id=<?= (int) $company['id'] ?>">Detalhes</a>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set_subscription_status">
                                            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                                            <?php if (in_array($company['subscription_status'], ['BLOCKED', 'CANCELED'], true)): ?>
                                                <input type="hidden" name="status" value="ACTIVE">
                                                <button class="rounded-md border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" type="submit">Liberar</button>
                                            <?php else: ?>
                                                <input type="hidden" name="status" value="BLOCKED">
                                                <button class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50" type="submit">Bloquear</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view === 'company'): ?>
        <?php if (!$selectedCompany): ?>
            <section class="rounded-lg border border-line bg-white p-6 text-sm text-slate-500">Empresa não encontrada.</section>
        <?php else: ?>
            <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
                <div class="grid gap-4">
                    <section class="rounded-lg border border-line bg-white p-5">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="text-xl font-black"><?= e($selectedCompany['name']) ?></h2>
                                <p class="mt-1 text-sm text-slate-500"><?= e($selectedCompany['email'] ?: '-') ?> · <?= e($selectedCompany['phone'] ?: '-') ?></p>
                                <p class="mt-1 text-xs text-slate-400">CNPJ: <?= e($selectedCompany['document'] ?: '-') ?> · criada em <?= e(date_br($selectedCompany['created_at'])) ?></p>
                            </div>
                            <a class="inline-flex min-h-10 items-center rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog" href="/super-admin.php?view=companies">Voltar</a>
                        </div>
                    </section>

                    <div class="grid gap-3 md:grid-cols-4">
                        <article class="rounded-lg border border-line bg-white p-4"><p class="text-sm text-slate-500">Usuários</p><strong class="mt-2 block text-2xl"><?= (int) $selectedCompany['users_active'] ?>/<?= (int) $selectedCompany['users_total'] ?></strong></article>
                        <article class="rounded-lg border border-line bg-white p-4"><p class="text-sm text-slate-500">Projetos</p><strong class="mt-2 block text-2xl"><?= (int) $selectedCompany['projects_total'] ?></strong></article>
                        <article class="rounded-lg border border-line bg-white p-4"><p class="text-sm text-slate-500">Venda mês</p><strong class="mt-2 block text-2xl"><?= money_br($selectedCompany['sales_month']) ?></strong></article>
                        <article class="rounded-lg border border-line bg-white p-4"><p class="text-sm text-slate-500">Recebido mês</p><strong class="mt-2 block text-2xl"><?= money_br($selectedCompany['received_month']) ?></strong></article>
                    </div>

                    <section class="overflow-hidden rounded-lg border border-line bg-white">
                        <div class="border-b border-line p-4 font-bold">Usuários da empresa</div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] text-left text-sm">
                                <thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Nome</th><th class="p-3">E-mail</th><th class="p-3">Função</th><th class="p-3">Status</th><th class="p-3 text-right">Ações</th></tr></thead>
                                <tbody>
                                    <?php foreach ($companyUsers as $item): ?>
                                        <tr class="border-t border-line">
                                            <td class="p-3 font-semibold"><?= e($item['name']) ?></td>
                                            <td class="p-3"><?= e($item['email']) ?></td>
                                            <td class="p-3"><?= e(role_label($item['role'])) ?></td>
                                            <td class="p-3"><?= $item['active'] ? 'Ativo' : 'Inativo' ?></td>
                                            <td class="p-3">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <form method="post">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                        <button class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" type="submit" onclick="return confirm('Gerar nova senha temporária para este usuário?')">Resetar senha</button>
                                                    </form>
                                                    <?php if ($item['role'] !== 'SUPER_ADMIN'): ?>
                                                        <form method="post">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="toggle_user">
                                                            <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                            <button class="rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog" type="submit"><?= $item['active'] ? 'Desativar' : 'Ativar' ?></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="grid gap-4 xl:grid-cols-2">
                        <div class="overflow-hidden rounded-lg border border-line bg-white">
                            <div class="border-b border-line p-4 font-bold">Projetos recentes</div>
                            <div class="divide-y divide-line">
                                <?php if (!$recentProjects): ?><p class="p-4 text-sm text-slate-500">Nenhum projeto cadastrado.</p><?php endif; ?>
                                <?php foreach ($recentProjects as $project): ?>
                                    <div class="p-4 text-sm">
                                        <strong><?= e($project['client_name']) ?></strong>
                                        <span class="block text-slate-500"><?= e($project['project_name']) ?> · <?= e(stage_label($project['current_stage'])) ?></span>
                                        <span class="block text-xs text-slate-400">Projetista: <?= e($project['designer_name'] ?: '-') ?> · <?= e(date_br($project['updated_at'] ?: $project['created_at'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-line bg-white">
                            <div class="border-b border-line p-4 font-bold">Vendas recentes</div>
                            <div class="divide-y divide-line">
                                <?php if (!$recentSales): ?><p class="p-4 text-sm text-slate-500">Nenhuma venda cadastrada.</p><?php endif; ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <div class="p-4 text-sm">
                                        <strong><?= e($sale['client_name']) ?></strong>
                                        <span class="block text-slate-500"><?= e($sale['project_name']) ?> · <?= money_br($sale['sold_value']) ?></span>
                                        <span class="block text-xs text-slate-400">Venda em <?= e(date_br($sale['sale_date'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="grid gap-4 content-start">
                    <form method="post" class="grid gap-4 rounded-lg border border-line bg-white p-5">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_subscription">
                        <input type="hidden" name="company_id" value="<?= (int) $selectedCompany['id'] ?>">
                        <h3 class="font-bold">Assinatura</h3>
                        <label class="grid gap-1 text-sm font-semibold">Plano
                            <select class="min-h-10 rounded-md border border-line px-3" name="plan">
                                <?php foreach (sa_plan_options() as $planKey => $plan): ?>
                                    <option value="<?= e($planKey) ?>" <?= $selectedCompany['plan'] === $planKey ? 'selected' : '' ?>><?= e($plan['name']) ?> · <?= money_br($plan['price']) ?>/mês</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">Status
                            <select class="min-h-10 rounded-md border border-line px-3" name="status">
                                <?php foreach (['TRIAL', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'BLOCKED'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $selectedCompany['subscription_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">Fim do trial
                            <input class="min-h-10 rounded-md border border-line px-3" type="date" name="trial_ends_at" value="<?= e($selectedCompany['trial_ends_at'] ?? '') ?>">
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">Vencimento
                            <input class="min-h-10 rounded-md border border-line px-3" type="date" name="current_period_end" value="<?= e($selectedCompany['current_period_end'] ?? '') ?>">
                        </label>
                        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar assinatura</button>
                    </form>

                    <section class="rounded-lg border border-line bg-white p-5">
                        <h3 class="font-bold">Resumo financeiro</h3>
                        <div class="mt-4 grid gap-3 text-sm">
                            <div class="flex justify-between rounded-md bg-fog px-3 py-2"><span>MRR do plano</span><strong><?= money_br(sa_plan_price($selectedCompany['plan'])) ?></strong></div>
                            <div class="flex justify-between rounded-md bg-fog px-3 py-2"><span>Vendido no mês</span><strong><?= money_br($selectedCompany['sales_month']) ?></strong></div>
                            <div class="flex justify-between rounded-md bg-fog px-3 py-2"><span>Recebido no mês</span><strong><?= money_br($selectedCompany['received_month']) ?></strong></div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-line bg-white p-5">
                        <h3 class="font-bold">Últimas importações</h3>
                        <div class="mt-3 grid gap-2 text-sm">
                            <?php if (!$recentImports): ?><p class="text-slate-500">Nenhuma importação.</p><?php endif; ?>
                            <?php foreach ($recentImports as $import): ?>
                                <div class="rounded-md border border-line p-3">
                                    <strong><?= e($import['type']) ?></strong>
                                    <span class="block text-xs text-slate-500"><?= (int) $import['success_rows'] ?>/<?= (int) $import['total_rows'] ?> registros · <?= e(date_br($import['created_at'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </aside>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
