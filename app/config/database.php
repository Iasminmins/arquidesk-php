<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

    try {
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        render_database_error($exception->getMessage(), $db);
    }

    return $pdo;
}

function render_database_error(string $message, array $db): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Erro de banco: {$message}" . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    $safeHost = htmlspecialchars($db['host'], ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8');
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Configurar banco - Arquidesk</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <main class="mx-auto grid min-h-screen max-w-3xl place-items-center px-4">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-bold">O banco MySQL ainda não conectou</h1>
                <p class="mt-3 text-slate-600">
                    O PHP esta funcionando, mas o MySQL recusou a conexao em
                    <strong><?= $safeHost ?></strong> para o banco <strong><?= $safeName ?></strong>.
                </p>
                <div class="mt-5 grid gap-3 rounded-md bg-slate-50 p-4 text-sm">
                    <p><strong>1.</strong> Abra o Laragon e inicie o MySQL.</p>
                    <p><strong>2.</strong> Se o banco ainda não existir, acesse <code class="rounded bg-white px-1">/install-database.php</code>.</p>
                    <p><strong>3.</strong> Se estiver na Hostinger, ajuste usuário, senha e nome do banco em <code class="rounded bg-white px-1">app/config/config.php</code>.</p>
                </div>
                <a class="mt-5 inline-flex min-h-10 items-center rounded-md bg-slate-900 px-4 text-sm font-bold text-white" href="/install-database.php">
                    Tentar instalar banco
                </a>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

