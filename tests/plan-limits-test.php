<?php

require_once __DIR__ . '/../app/includes/functions.php';

function assert_plan_value(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . " Esperado: " . var_export($expected, true) . '; obtido: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$plans = plan_config();
assert_plan_value(5, plan_user_limit('ESSENCIAL'), 'Start deve comportar proprietário e quatro usuários.');
assert_plan_value(9, plan_user_limit('PROFISSIONAL'), 'Profissional deve comportar proprietário e oito usuários.');
assert_plan_value(16, plan_user_limit('PREMIUM'), 'Business deve comportar proprietário e quinze usuários.');
assert_plan_value('Proprietário + até 4 usuários', $plans['ESSENCIAL']['users'], 'O texto do Start deve explicar a vaga adicional do proprietário.');

echo "plan-limits-test: OK\n";
