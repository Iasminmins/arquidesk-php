<?php

require_once __DIR__ . '/../app/includes/intern-permissions.php';

function assert_access(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$intern = ['id' => 12, 'role' => 'ESTAGIARIO'];
assert_access(true, intern_can_access_project($intern, ['intern_user_id' => 12]), 'A estagiária deve acessar projeto atribuído.');
assert_access(false, intern_can_access_project($intern, ['intern_user_id' => 13]), 'A estagiária não deve acessar projeto de outra pessoa.');
assert_access(true, intern_can_move_project($intern, 'PROJETO', 'NEGOCIACAO'), 'A estagiária deve mover Projeto para Negociação.');
assert_access(false, intern_can_move_project($intern, 'NEGOCIACAO', 'CONFERENCIA'), 'A estagiária não deve avançar para Conferência.');
assert_access(['PROJETO', 'NEGOCIACAO'], intern_allowed_project_stages($intern), 'A estagiária deve ver somente Projeto e Negociação.');
assert_access(true, designer_can_manage_project(['id' => 5, 'role' => 'PROJETISTA'], ['designer_id' => 9, 'intern_user_id' => 12]), 'Qualquer projetista deve administrar projeto de estagiária.');
assert_access(false, designer_can_manage_project(['id' => 5, 'role' => 'PROJETISTA'], ['designer_id' => 9, 'intern_user_id' => null]), 'Projeto comum deve continuar restrito ao responsável.');

$regularScope = project_assignment_scope_sql('PROJETISTA', '');
assert_access(true, str_contains($regularScope, 'intern_user_id is null'), 'A lista comum do projetista deve excluir projetos de estagiários.');
$internScope = project_assignment_scope_sql('PROJETISTA', 'intern');
assert_access(true, str_contains($internScope, 'intern_user_id is not null'), 'A aba de estagiários deve mostrar apenas projetos atribuídos.');

echo "intern-project-access-test: OK\n";
