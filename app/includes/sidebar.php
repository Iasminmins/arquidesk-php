<?php
$nav = role_nav($user['role']);
$currentPath = strtok($_SERVER['REQUEST_URI'], '?');
$currentStage = $_GET['stage'] ?? '';
$currentView = $_GET['view'] ?? '';
?>
<div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
    <aside class="border-r border-line bg-white">
        <div class="flex h-16 items-center gap-3 border-b border-line px-4">
            <div class="grid h-10 w-10 place-items-center rounded-md bg-ink text-white font-bold">A</div>
            <div class="min-w-0">
                <strong class="block truncate"><?= e($user['company_name'] ?: 'Arquidesk') ?></strong>
                <span class="text-xs text-slate-500"><?= e(str_replace('_', ' ', $user['role'])) ?></span>
            </div>
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
                <a class="rounded-md px-3 py-2 text-sm font-semibold <?= $active ? 'bg-ink text-white' : 'text-slate-700 hover:bg-fog' ?>" href="<?= e($href) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="min-w-0">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur">
            <div>
                <h1 class="text-lg font-bold"><?= e($pageTitle ?? 'Arquidesk') ?></h1>
                <p class="text-xs text-slate-500"><?= e($user['name']) ?></p>
            </div>
            <a href="/logout.php" class="ml-auto rounded-md px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-fog">Sair</a>
        </header>
        <div class="p-4 md:p-6">
