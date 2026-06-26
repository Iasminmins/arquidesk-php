<?php
$config = require __DIR__ . '/../config/config.php';
$pageTitle = $pageTitle ?? $config['app_name'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fog: '#f6f4ef',
                        ink: '#15201d',
                        clay: '#b8664b',
                        line: '#e6e1d8'
                    }
                }
            }
        };
    </script>
</head>
<body class="min-h-screen bg-fog text-ink">
