<?php

require_once __DIR__ . '/../app/includes/intern-permissions.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nEsperado: " . var_export($expected, true) . "\nObtido: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

assert_same(true, intern_level_allows('DELETE', 'VIEW'), 'Excluir deve incluir visualização.');
assert_same(true, intern_level_allows('EDIT', 'EDIT'), 'Editar deve permitir edição.');
assert_same(false, intern_level_allows('VIEW', 'EDIT'), 'Visualizar não deve permitir edição.');
assert_same(false, intern_level_allows('NONE', 'VIEW'), 'Sem acesso deve bloquear visualização.');

$permissions = ['projects' => 'VIEW', 'finance' => 'EDIT'];
assert_same(true, intern_has_permission($permissions, 'projects', 'VIEW'), 'A aba Projetos deve permitir visualização.');
assert_same(false, intern_has_permission($permissions, 'projects', 'EDIT'), 'A aba Projetos não deve permitir edição.');
assert_same(true, intern_has_permission($permissions, 'finance', 'VIEW'), 'Editar Financeiro deve incluir visualização.');
assert_same(false, intern_has_permission($permissions, 'contracts', 'VIEW'), 'Aba ausente deve ser bloqueada.');
assert_same('projects', intern_tab_for_request('/project-delete.php', []), 'A exclusão de projeto deve usar a aba Projetos.');
assert_same('DELETE', intern_required_level_for_request('/future-clients.php', 'POST', ['delete' => 10]), 'Excluir cliente deve exigir nível de exclusão.');
assert_same('EDIT', intern_required_level_for_request('/project-form.php', 'GET', []), 'Abrir formulário de projeto deve exigir edição.');

$nav = ['/' => 'Dashboard', '/projects.php?stage=PROJETO' => 'Projeto', '/finance.php' => 'Financeiro'];
assert_same(
    ['/projects.php?stage=PROJETO' => 'Projeto', '/finance.php' => 'Financeiro'],
    intern_filter_nav($nav, $permissions),
    'O menu deve conter somente abas autorizadas.'
);

echo "intern-permissions-test: OK\n";
