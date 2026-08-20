<?php

$source = file_get_contents(__DIR__ . '/../public/index.php');

if (!str_contains($source, 'name="period" value="custom"') || !str_contains($source, 'Aplicar filtro')) {
    fwrite(STDERR, "O período personalizado precisa de um botão que envie period=custom.\n");
    exit(1);
}

echo "dashboard-custom-filter-test: OK\n";
