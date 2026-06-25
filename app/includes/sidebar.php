<?php
$nav = role_nav($user['role']);
$currentPath = strtok($_SERVER['REQUEST_URI'], '?');
$currentStage = $_GET['stage'] ?? '';
$currentView = $_GET['view'] ?? '';
$primaryColor = $user['primary_color'] ?? '#15201d';
$secondaryColor = $user['secondary_color'] ?? '#b8664b';

$companyStmt = db()->prepare('select logo_url from companies where id = ? limit 1');
$companyStmt->execute([(int) $user['company_id']]);
$companyLogo = $companyStmt->fetchColumn() ?: '';
?>
<div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-line bg-white transition lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0 lg:overflow-y-auto">
        <div class="flex h-16 items-center gap-3 border-b border-line px-4">
            <?php if ($companyLogo): ?>
                <img src="<?= e($companyLogo) ?>" class="h-10 w-10 rounded-md object-cover" alt="">
            <?php else: ?>
                <div class="grid h-10 w-10 place-items-center rounded-md text-white font-bold" style="background:<?= e($primaryColor) ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 21V7h6v14M10 21V3h10v18M3 21h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <strong class="block truncate"><?= e($user['company_name'] ?: 'Arquidesk') ?></strong>
                <span class="text-xs text-slate-500"><?= e(str_replace('_', ' ', $user['role'])) ?></span>
            </div>
            <button onclick="document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden')" class="ml-auto grid h-9 w-9 place-items-center rounded-md hover:bg-fog lg:hidden" aria-label="Fechar">✕</button>
        </div>
        <nav class="grid gap-1 p-3">
            <?php foreach ($nav as $href => $label): ?>
                <?php
                $isStage = str_contains($href, 'stage=');
                $targetStage = $isStage ? substr($href, strpos($href, 'stage=') + 6) : '';
                $isView = str_contains($href, 'view=');
                $targetView = $isView ? substr($href, strpos($href, 'view=') + 5) : '';
                $pathOnly = strtok($href, '?');
                $active = $href === '/'
                    ? $currentPath === '/' || $currentPath === '/index.php'
                    : ($currentPath === '/projects.php' && $currentStage === $targetStage);
                if ($isView) {
                    $active = $currentPath === $pathOnly && $currentView === $targetView;
                }
                if (!$isStage && $href !== '/') {
                    $active = $currentPath === $pathOnly;
                }
                ?>
                <a class="rounded-md px-3 py-2 text-sm font-semibold <?= $active ? 'text-white' : 'text-slate-700 hover:bg-fog' ?>" <?= $active ? 'style="background:' . e($primaryColor) . '"' : '' ?> href="<?= e($href) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-ink/35 lg:hidden" onclick="document.getElementById('sidebar').classList.add('-translate-x-full');this.classList.add('hidden')"></div>
    <main class="min-w-0">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur">
            <button onclick="document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden')" class="grid h-10 w-10 place-items-center rounded-md hover:bg-fog lg:hidden" aria-label="Menu">☰</button>
            <div>
                <h1 class="text-lg font-bold"><?= e($pageTitle ?? 'Arquidesk') ?></h1>
                <p class="text-xs text-slate-500"><?= e($user['name']) ?></p>
            </div>
            <a href="/logout.php" class="ml-auto rounded-md px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-fog">Sair</a>
        </header>
        <div class="p-4 md:p-6">
