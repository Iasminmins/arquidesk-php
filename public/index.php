<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = current_user();

if ($user && $user['role'] === 'SUPER_ADMIN') {
    redirect('/super-admin.php');
}

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
                <a href="#compare" class="hover:text-ink">Por que trocar</a>
                <a href="#plans" class="hover:text-ink">Planos</a>
                <a href="#testimonials" class="hover:text-ink">Depoimentos</a>
            </nav>
            <a href="/login.php" class="hidden min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-fog sm:inline-flex">Entrar</a>
            <a href="/setup.php" class="inline-flex min-h-10 items-center rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95">Começar grátis</a>
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
                    <a href="/setup.php" class="inline-flex min-h-12 items-center justify-center rounded-md bg-ink px-6 font-bold text-white hover:opacity-95">Começar com 1 mês grátis</a>
                    <a href="https://wa.me/5524999327549" target="_blank" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-5 font-bold text-emerald-700 hover:bg-emerald-100">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Falar com consultor
                    </a>
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

        <section id="compare" class="border-y border-line bg-white">
            <div class="mx-auto max-w-5xl px-4 py-16 md:px-6">
                <h2 class="text-center text-3xl font-black">Chega de planilhas soltas.</h2>
                <p class="mx-auto mt-4 max-w-3xl text-center text-ink/70">Quando projetos, vendas, montagem e financeiro ficam espalhados, sua equipe perde prazo, histórico e controle.</p>
                <div class="mt-10 grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-red-200 bg-red-50 p-6">
                        <h3 class="text-lg font-black text-red-800">Com planilhas</h3>
                        <ul class="mt-4 grid gap-3 text-sm text-red-700">
                            <?php foreach (['Dados espalhados em vários arquivos', 'Sem controle de quem alterou o quê', 'Projetista vê dados de todos os colegas', 'Comissão calculada manualmente todo mês', 'Sem histórico de movimentações', 'Perde tempo montando relatórios', 'Cliente esquecido sem acompanhamento'] as $item): ?>
                                <li class="flex gap-2"><span class="mt-0.5 shrink-0">✕</span> <?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-6">
                        <h3 class="text-lg font-black text-emerald-800">Com Arquidesk</h3>
                        <ul class="mt-4 grid gap-3 text-sm text-emerald-700">
                            <?php foreach (['Tudo em uma plataforma, cada empresa isolada', 'Histórico completo de quem moveu cada projeto', 'Cada projetista vê apenas seus projetos', 'Comissão calculada automaticamente por faixa', 'Rastreio de todas as etapas do projeto', 'Exportação com 1 clique, importação por XLSX', 'Clientes Futuros com lembrete de contato'] as $item): ?>
                                <li class="flex gap-2"><span class="mt-0.5 shrink-0">✓</span> <?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
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
                        <span class="grid h-10 w-10 place-items-center rounded-md bg-fog text-emerald-900"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
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

        <section id="testimonials" class="mx-auto max-w-7xl px-4 py-16 md:px-6">
            <h2 class="text-center text-3xl font-black">O que dizem nossos clientes</h2>
            <div class="mt-10 grid gap-4 md:grid-cols-3">
                <?php foreach ([
                    ['Antes usávamos planilhas e perdia horas conferindo informações. Com o Arquidesk, tudo ficou centralizado e a equipe ganhou produtividade.', 'Letícia F.', 'Projetista — Casa Contemporânea'],
                    ['Conseguimos acompanhar cada projeto do início ao fim sem perder nenhuma informação. A equipe ficou muito mais organizada.', 'Mariana S.', 'Arquiteta — Studio MS Interiores'],
                    ['O controle de comissões e metas mudou nosso financeiro. Hoje cada projetista sabe exatamente quanto vai receber.', 'Carlos A.', 'Gerente — Planejados Premium'],
                ] as $t): ?>
                    <article class="rounded-lg border border-line bg-white p-6 shadow-sm">
                        <div class="flex gap-1 text-amber-400">★★★★★</div>
                        <p class="mt-4 text-sm text-ink/70">"<?= e($t[0]) ?>"</p>
                        <div class="mt-5 border-t border-line pt-4">
                            <strong class="text-sm"><?= e($t[1]) ?></strong>
                            <p class="text-xs text-ink/50"><?= e($t[2]) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="border-t border-line bg-ink">
            <div class="mx-auto max-w-4xl px-4 py-16 text-center md:px-6">
                <h2 class="text-3xl font-black text-white">Pronto para organizar sua operação?</h2>
                <p class="mx-auto mt-4 max-w-2xl text-white/70">Comece agora com 1 mês grátis. Sem cartão de crédito. Cancele quando quiser.</p>
                <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                    <a href="/setup.php" class="inline-flex min-h-12 items-center justify-center rounded-md bg-white px-6 font-bold text-ink hover:bg-fog">Criar conta grátis</a>
                    <a href="https://wa.me/5524999327549" target="_blank" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-white/20 px-5 font-bold text-white hover:bg-white/10">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Falar com consultor
                    </a>
                </div>
            </div>
        </section>
    </main>
    <footer class="border-t border-line bg-white">
        <div class="mx-auto max-w-7xl px-4 py-8 md:px-6">
            <div class="flex flex-col items-center gap-4 text-sm text-ink/50 md:flex-row md:justify-between">
                <span>© <?= date('Y') ?> Arquidesk. Todos os direitos reservados.</span>
                <div class="flex gap-6">
                    <a href="/login.php" class="hover:text-ink">Entrar</a>
                    <a href="/setup.php" class="hover:text-ink">Criar conta</a>
                    <a href="https://wa.me/5524999327549" target="_blank" class="hover:text-ink">Contato</a>
                </div>
            </div>
        </div>
    </footer>
    <a href="https://wa.me/5524999327549" target="_blank" class="fixed bottom-6 right-6 z-40 grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30 hover:bg-emerald-600 transition">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>
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

