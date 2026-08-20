<?php

$source = file_get_contents(__DIR__ . '/../public/my-day.php');

if (!str_contains($source, "my_day_posted_text('description')")) {
    fwrite(STDERR, "O campo de observação precisa ser normalizado antes de trim().\n");
    exit(1);
}

if (!str_contains($source, "my_day_posted_text('title')")) {
    fwrite(STDERR, "O título do item precisa usar a mesma normalização segura.\n");
    exit(1);
}

echo "my-day-observation-test: OK\n";
