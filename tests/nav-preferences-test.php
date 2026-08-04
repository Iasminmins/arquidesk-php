<?php

require_once __DIR__ . '/../app/includes/nav-preferences.php';

function assert_nav(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$nav = [
    '/' => 'Dashboard',
    '/projects.php?stage=PROJETO' => 'Projeto',
    '/finance.php' => 'Financeiro',
    '/menu-settings.php' => 'Configurações do menu',
];

assert_nav($nav, filter_nav_by_preferences($nav, []), 'Sem preferências, o menu deve permanecer completo.');
$filtered = filter_nav_by_preferences($nav, ['/finance.php', '/menu-settings.php']);
assert_nav(false, isset($filtered['/finance.php']), 'A preferência deve ocultar Financeiro.');
assert_nav(true, isset($filtered['/menu-settings.php']), 'Configurações deve permanecer sempre visível.');

$groups = designer_nav_groups();
assert_nav(true, isset($groups['flow']['/projects.php?stage=PROJETO']), 'Projeto deve pertencer ao fluxo em sequência.');
assert_nav(true, isset($groups['independent']['/my-day.php']), 'Meu Dia deve ser classificado como aba independente.');

echo "nav-preferences-test: OK\n";
