<?php

require_once __DIR__ . '/../app/includes/functions.php';

function assert_abandon(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

assert_abandon(true, project_can_abandon('PROJETO'), 'A etapa Projeto deve permitir Desistir e Futuro.');
assert_abandon(true, project_can_abandon('NEGOCIACAO'), 'A etapa Negociação deve continuar permitindo Desistir e Futuro.');
assert_abandon(false, project_can_abandon('CONFERENCIA'), 'Etapas posteriores não devem permitir Desistir e Futuro.');
assert_abandon(true, project_is_abandoned(['negotiation_status' => 'Desistida']), 'Status Desistida deve entrar na aba Desistidos.');
assert_abandon(false, project_is_abandoned(['negotiation_status' => 'Em negociação']), 'Status ativo não deve entrar na aba Desistidos.');

echo "project-abandon-test: OK\n";
