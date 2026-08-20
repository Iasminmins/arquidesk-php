<?php

$source = file_get_contents(__DIR__ . '/../public/my-day.php');

if (!str_contains($source, 'try {') || !str_contains($source, 'Falha ao atualizar o schema do checklist')) {
    fwrite(STDERR, "As alterações opcionais do schema precisam ser protegidas contra falhas de migração.\n");
    exit(1);
}

echo "my-day-schema-test: OK\n";
