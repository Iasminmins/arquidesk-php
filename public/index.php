<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = current_user();

if (!$user) {
    $pageTitle = 'Arquidesk';
    require __DIR__ . '/../app/includes/header.php';
    ?>
    <header class="sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 md:px-6">
            <a href="/" class="flex items-center gap-2 font-black">
                <span class="grid h-10 w-10 place-items-center rounded-md bg-ink text-white">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 21V7h6v14M10 21V3h10v18M7 10h.01M7 14h.01M7 18h.01M14 7h2M14 11h2M14 15h2M3 21h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                Arquidesk
            </a>
            <nav class="ml-auto hidden items-center gap-6 text-sm font-semibold text-ink/70 md:flex">
                <a href="#features" class="hover:text-ink">Funcionalidades</a>
                <a href="#how" class="hover:text-ink">Como funciona</a>
                <a href="#plans" class="hover:text-ink">Planos</a>
            </nav>
            <a href="/login.php" class="hidden min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-fog sm:inline-flex">Entrar</a>
            <a href="/setup.php" class="inline-flex min-h-10 items-center rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95">Começar com 1 mês grátis</a>
        </div>
    </header>

    <main class="bg-fog text-ink">
        <section class="mx-auto grid min-h-[calc(100vh-65px)] max-w-7xl items-center gap-10 px-4 py-12 md:px-6 lg:grid-cols-[1.02fr_0.98fr]">
            <div>
                <span class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-xs font-bold text-emerald-900">SaaS para arquitetura, interiores e planejados</span>
                <h1 class="mt-5 max-w-4xl text-4xl font-black leading-tight md:text-6xl lg:text-7xl">
                    Gestão completa para arquitetura, interiores e móveis planejados.
                </h1>
                <p class="mt-5 max-w-2xl text-lg text-ink/70">
                    Controle projetos, negociações, conferencia, montagem, assistência, financeiro e metas da equipe em uma única plataforma.
                </p>
                <p class="mt-4 max-w-2xl font-semibold text-emerald-900">
                    Todas as funcionalidades liberadas em todos os planos. O valor muda apenas conforme o tamanho da sua equipe.
                </p>
                <p class="mt-3 max-w-2xl text-sm font-bold text-ink/70">
                    Já utilizado por empresas do setor, como a Casa Contemporânea.
                </p>
                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <a href="/setup.php" class="inline-flex min-h-12 items-center justify-center rounded-md bg-ink px-5 font-bold text-white hover:opacity-95">Começar com 1 mês grátis</a>
                    <a href="/login.php" class="inline-flex min-h-12 items-center justify-center rounded-md border border-line bg-white px-5 font-bold hover:bg-fog">Entrar</a>
                </div>
            </div>

            <div class="rounded-lg border border-line bg-white p-4 shadow-2xl shadow-ink/10">
                <div class="flex items-center justify-between border-b border-line pb-4">
                    <div>
                        <p class="text-sm text-ink/60">Dashboard Arquidesk</p>
                        <strong>Visao operacional</strong>
                    </div>
                    <span class="rounded-full bg-fog px-3 py-1 text-xs font-bold text-emerald-900">Ao vivo</span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?php foreach ([['Projetos em andamento', '32'], ['Vendas do mês', 'R$ 186.400'], ['Comissões calculadas', 'R$ 12.940'], ['Assistências abertas', '6']] as $card): ?>
                        <div class="rounded-md border border-line bg-fog p-4">
                            <p class="text-sm text-ink/60"><?= e($card[0]) ?></p>
                            <strong class="mt-2 block text-2xl"><?= e($card[1]) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 rounded-md border border-line p-4">
                    <div class="mb-3 flex items-center gap-2 text-sm font-bold">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 17 6-6 4 4 7-7M14 8h6v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Etapas operacionais
                    </div>
                    <?php foreach ([['Projeto', 82], ['Negociação', 70], ['Conferência', 58], ['Montagem', 46], ['Assistência', 34]] as $bar): ?>
                        <div class="mb-2 grid grid-cols-[110px_1fr] items-center gap-3 text-sm">
                            <span class="text-ink/60"><?= e($bar[0]) ?></span>
                            <span class="h-2 rounded-full bg-stone-100"><span class="block h-2 rounded-full bg-emerald-900" style="width: <?= (int) $bar[1] ?>%"></span></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="border-y border-line bg-white">
            <div class="mx-auto max-w-5xl px-4 py-14 text-center md:px-6">
                <h2 class="text-3xl font-black">Chega de depender de planilhas soltas para controlar sua operação.</h2>
                <p class="mx-auto mt-4 max-w-3xl text-ink/70">Quando projetos, vendas, montagem, assistência e financeiro ficam espalhados, sua equipe perde prazo, histórico e controle.</p>
            </div>
        </section>

        <section id="features" class="mx-auto max-w-7xl px-4 py-16 md:px-6">
            <div class="max-w-3xl">
                <h2 class="text-3xl font-black">Uma plataforma completa para controlar sua operação.</h2>
                <p class="mt-3 text-ink/70">Do primeiro atendimento a finalizacao do projeto, o Arquidesk organiza as etapas mais importantes da sua empresa.</p>
            </div>
            <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <?php foreach ([['Fluxo de projetos', 'Acompanhe cada cliente desde o projeto inicial até a finalizacao.'], ['Dashboard gerencial', 'Vejá indicadores por período e acompanhe a operação.'], ['Financeiro', 'Controle vendas, pagamentos recebidos e comissoes.'], ['Metas da equipe', 'Defina metas e acompanhe desempenho individual e coletivo.'], ['Agenda', 'Organize apresentacoes, faturamentos, montagens e assistências.'], ['Permissoes por funcao', 'Separe acessos para admin, projetista e conferente.'], ['Importação e exportação', 'Facilite migração, relatórios e conferencias operacionais.'], ['Identidade da empresa', 'Configure dados e cores da empresa.']] as $feature): ?>
                    <article class="rounded-lg border border-line bg-white p-5 shadow-sm">
                        <span class="grid h-10 w-10 place-items-center rounded-md bg-fog text-emerald-900">âœ“</span>
                        <h3 class="mt-4 font-bold"><?= e($feature[0]) ?></h3>
                        <p class="mt-2 text-sm text-ink/65"><?= e($feature[1]) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="how" class="border-y border-line bg-white">
            <div class="mx-auto max-w-7xl px-4 py-16 md:px-6">
                <h2 class="text-3xl font-black">Como funciona</h2>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <?php foreach ([['1', 'Crie sua conta', 'Cadastre sua empresa e comece com um ambiente próprio.'], ['2', 'Cadastre equipe e projetos', 'Organize usuários, clientes, projetos e etapas da operação.'], ['3', 'Acompanhe tudo em um só lugar', 'Controle prazos, financeiro, metas e histórico sem várias planilhas.']] as $step): ?>
                        <article class="rounded-lg border border-line bg-fog p-5">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-ink font-black text-white"><?= e($step[0]) ?></span>
                            <h3 class="mt-4 font-bold"><?= e($step[1]) ?></h3>
                            <p class="mt-2 text-sm text-ink/65"><?= e($step[2]) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="plans" class="border-y border-line bg-white">
            <div class="mx-auto max-w-7xl px-4 py-16 md:px-6">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl font-black">Escolha o plano de acordo com o tamanho da sua equipe.</h2>
                    <p class="mt-3 text-ink/70">Todos os planos incluem a plataforma completa.</p>
                    <p class="mt-2 text-sm font-bold text-emerald-700">1 mês grátis para começar.</p>
                </div>
                <?php $allPlans = plan_config(); $features = plan_included_features(); ?>
                <div class="mt-8 grid gap-4 lg:grid-cols-3">
                    <?php foreach ($allPlans as $planKey => $plan): ?>
                        <article class="relative rounded-lg border bg-white p-6 shadow-sm <?= $plan['highlighted'] ? 'border-emerald-600 ring-2 ring-emerald-600/20' : 'border-line' ?>">
                            <?php if ($plan['badge']): ?>
                                <span class="absolute right-4 top-4 rounded-full bg-orange-500 px-3 py-1 text-xs font-black text-white"><?= e($plan['badge']) ?></span>
                            <?php endif; ?>
                            <h3 class="text-xl font-black"><?= e($plan['name']) ?></h3>
                            <p class="mt-3 text-3xl font-black"><?= e($plan['priceLabel']) ?></p>
                            <p class="mt-2 text-sm font-bold text-emerald-700"><?= e($plan['users']) ?></p>
                            <p class="mt-3 text-sm text-ink/65"><?= e($plan['description']) ?></p>
                            <ul class="mt-5 grid gap-2 text-sm text-ink/70">
                                <?php foreach ($features as $feature): ?>
                                    <li class="flex gap-2"><span class="mt-0.5 shrink-0 text-emerald-600">✓</span> <?= e($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="/setup.php?plan=<?= e($planKey) ?>" class="mt-5 flex min-h-11 w-full items-center justify-center rounded-md px-4 font-bold <?= $plan['highlighted'] ? 'bg-ink text-white' : 'border border-line hover:bg-fog' ?>">Começar com 1 mês grátis</a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="mt-6 text-center text-sm text-ink/60"><strong class="text-ink">Usuários adicionais:</strong> R$ 49/mês por usuário. Todas as funcionalidades continuam liberadas.</p>
            </div>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$pageTitle = 'Dashboard';
require_active_subscription($user);

$companyId = (int) $user['company_id'];
$period = $_GET['period'] ?? 'month';
$customStart = $_GET['start'] ?? date('Y-m-d');
$customEnd = $_GET['end'] ?? date('Y-m-d');

if ($period === 'today') {
    $periodStart = date('Y-m-d');
    $periodEnd = date('Y-m-d');
    $periodLabel = 'hoje';
} elseif ($period === 'week') {
    $periodStart = date('Y-m-d', strtotime('monday this week'));
    $periodEnd = date('Y-m-d', strtotime('sunday this week'));
    $periodLabel = 'semana';
} elseif ($period === 'year') {
    $periodStart = date('Y-01-01');
    $periodEnd = date('Y-12-31');
    $periodLabel = 'ano';
} elseif ($period === 'custom') {
    $periodStart = $customStart;
    $periodEnd = $customEnd;
    $periodLabel = 'período';
} else {
    [$periodStart, $periodEnd] = month_range((int) date('Y'), (int) date('n'));
    $periodLabel = 'mês';
}

$projectSql = "select current_stage, count(*) as total from client_projects where company_id = ? and date(coalesce(updated_at, created_at)) between ? and ?";
$projectParams = [$companyId, $periodStart, $periodEnd];
if ($user['role'] === 'PROJETISTA') {
    $projectSql .= " and designer_id = ?";
    $projectParams[] = (int) $user['id'];
}
$projectSql .= " group by current_stage";
$stageStmt = db()->prepare($projectSql);
$stageStmt->execute($projectParams);
$stageCounts = [];
foreach ($stageStmt->fetchAll() as $row) {
    $stageCounts[$row['current_stage']] = (int) $row['total'];
}
$totalProjects = array_sum($stageCounts);

$salesSql = "select coalesce(sum(sold_value), 0) from financial_sales where company_id = ? and sale_date between ? and ?";
$salesParams = [$companyId, $periodStart, $periodEnd];
if ($user['role'] === 'PROJETISTA') {
    $salesSql .= " and designer_id = ?";
    $salesParams[] = (int) $user['id'];
}
$salesStmt = db()->prepare($salesSql);
$salesStmt->execute($salesParams);
$totalSold = (float) $salesStmt->fetchColumn();

$paymentsSql = "select coalesce(sum(fp.amount), 0) from financial_payments fp join financial_sales fs on fs.id = fp.financial_sale_id where fp.company_id = ? and fp.payment_date between ? and ?";
$paymentsParams = [$companyId, $periodStart, $periodEnd];
if ($user['role'] === 'PROJETISTA') {
    $paymentsSql .= " and fs.designer_id = ?";
    $paymentsParams[] = (int) $user['id'];
}
$paymentsStmt = db()->prepare($paymentsSql);
$paymentsStmt->execute($paymentsParams);
$totalReceived = (float) $paymentsStmt->fetchColumn();
$commissionRate = commission_rate($totalReceived);

$recentSql = "select p.*, u.name as designer_name from client_projects p left join users u on u.id = p.designer_id where p.company_id = ? and date(coalesce(p.updated_at, p.created_at)) between ? and ?";
$recentParams = [$companyId, $periodStart, $periodEnd];
if ($user['role'] === 'PROJETISTA') {
    $recentSql .= " and p.designer_id = ?";
    $recentParams[] = (int) $user['id'];
}
$recentSql .= " order by p.updated_at desc, p.created_at desc limit 6";
$recentStmt = db()->prepare($recentSql);
$recentStmt->execute($recentParams);
$recentProjects = $recentStmt->fetchAll();

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-6">
    <form method="get" class="rounded-lg border border-line bg-white p-4">
        <div class="flex flex-col gap-4">
            <div>
                <h2 class="font-bold">Visão geral do negócio</h2>
                <p class="mt-1 text-sm text-slate-500">Indicadores filtrados por período.</p>
            </div>
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex w-full max-w-2xl flex-nowrap overflow-x-auto rounded-md border border-line bg-fog p-1 text-sm">
                    <?php foreach (['today' => 'Hoje', 'week' => 'Semana', 'month' => 'Mês', 'year' => 'Ano', 'custom' => 'Personalizado'] as $key => $label): ?>
                        <button name="period" value="<?= e($key) ?>" class="min-h-9 flex-1 whitespace-nowrap rounded px-3 font-semibold <?= $period === $key ? 'bg-white text-ink shadow-sm' : 'text-slate-500 hover:text-ink' ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php if ($period === 'custom'): ?>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input class="min-h-10 rounded-md border border-line bg-white px-3 text-sm" type="date" name="start" value="<?= e($customStart) ?>">
                        <input class="min-h-10 rounded-md border border-line bg-white px-3 text-sm" type="date" name="end" value="<?= e($customEnd) ?>">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <?php if ($user['role'] === 'ADMIN_EMPRESA'): ?>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Projetos em andamento no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= max(0, $totalProjects - ($stageCounts['FINALIZADO'] ?? 0)) ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Total de venda no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= money_br($totalSold) ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Total que entrou no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= money_br($totalReceived) ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Valor da comissão no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= money_br($totalReceived * $commissionRate) ?></strong>
                <span class="mt-1 block text-xs text-slate-500"><?= (int) ($commissionRate * 100) ?>% aplicado</span>
            </article>
        <?php elseif ($user['role'] === 'PROJETISTA'): ?>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Meus projetos no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= $totalProjects ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Minhas negociações no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= $stageCounts['NEGOCIACAO'] ?? 0 ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Meu total fechado no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= money_br($totalSold) ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Meu percentual atingido</span>
                <strong class="mt-2 block text-3xl"><a href="/goals.php?mode=my-goal" class="underline hover:no-underline">Ver Minha Meta</a></strong>
            </article>
        <?php else: ?>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Conferências pendentes no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= $stageCounts['CONFERENCIA'] ?? 0 ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Itens em montagem no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= $stageCounts['MONTAGEM'] ?? 0 ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Assistências abertas no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= $stageCounts['ASSISTENCIA'] ?? 0 ?></strong>
            </article>
            <article class="rounded-lg border border-line bg-white p-4">
                <span class="text-sm text-slate-500">Prazos importantes no <?= e($periodLabel) ?></span>
                <strong class="mt-2 block text-3xl"><?= count(array_filter($recentProjects, fn($p) => !empty($p['billing_date']) || !empty($p['assembly_finished_date']))) ?></strong>
            </article>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
        <section class="rounded-lg border border-line bg-white p-4">
            <h2 class="font-bold">Etapas operacionais</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <?php foreach (['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'] as $stage): ?>
                    <a class="rounded-md bg-fog p-4 transition hover:bg-slate-100" href="/projects.php?stage=<?= e($stage) ?>">
                        <span class="text-sm text-slate-500"><?= e(stage_label($stage)) ?></span>
                        <strong class="mt-1 block text-2xl"><?= $stageCounts[$stage] ?? 0 ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-lg border border-line bg-white p-4">
            <h2 class="font-bold">Ultimas movimentacoes</h2>
            <div class="mt-3 grid gap-3">
                <?php if (!$recentProjects): ?>
                    <div class="rounded-md border border-line p-3 text-sm text-slate-500">Nenhum projeto cadastrado ainda.</div>
                <?php endif; ?>
                <?php foreach ($recentProjects as $project): ?>
                    <article class="rounded-md border border-line p-3 text-sm">
                        <strong><?= e($project['project_name']) ?></strong>
                        <span class="block text-slate-500">
                            <?= e($project['client_name']) ?> · <?= e(stage_label($project['current_stage'])) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>

