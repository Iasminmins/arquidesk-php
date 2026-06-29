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
        render_database_error($exception->getMessage(), $db, $config['env'] ?? 'local');
    }

    return $pdo;
}

function render_database_error(string $message, array $db, string $env = 'local'): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Erro de banco: {$message}" . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    error_log('[Database] Connection failed: ' . $message);

    if ($env === 'production') {
        ?>
        <!doctype html>
        <html lang="pt-BR">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Erro temporario - Arquidesk</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="min-h-screen bg-slate-100 text-slate-900">
            <main class="mx-auto grid min-h-screen max-w-xl place-items-center px-4">
                <section class="rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <h1 class="text-2xl font-bold">Nao foi possivel carregar o Arquidesk</h1>
                    <p class="mt-3 text-slate-600">
                        Tivemos um problema temporario ao conectar com o banco de dados.
                        Tente novamente em instantes ou acione o suporte.
                    </p>
                </section>
            </main>
        </body>
        </html>
        <?php
        exit;
    }

    $safeHost = htmlspecialchars($db['host'], ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8');
    $safeUser = htmlspecialchars($db['user'], ENT_QUOTES, 'UTF-8');
    $isLocal = in_array($db['host'], ['127.0.0.1', 'localhost', '::1'], true)
        && ($db['user'] === 'root' || $db['user'] === '');
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
                <h1 class="text-2xl font-bold">O banco MySQL ainda nao conectou</h1>
                <p class="mt-3 text-slate-600">
                    O PHP esta funcionando, mas o MySQL recusou a conexao em
                    <strong><?= $safeHost ?></strong> para o banco <strong><?= $safeName ?></strong>.
                </p>
                <div class="mt-4 rounded-md bg-slate-50 p-4 text-sm">
                    <p><strong>Host:</strong> <?= $safeHost ?></p>
                    <p><strong>Banco:</strong> <?= $safeName ?></p>
                    <p><strong>Usuario:</strong> <?= $safeUser ?></p>
                </div>
                <div class="mt-5 grid gap-3 rounded-md bg-slate-50 p-4 text-sm">
                    <?php if ($isLocal): ?>
                        <p><strong>1.</strong> Abra o Laragon e inicie o MySQL.</p>
                        <p><strong>2.</strong> Se o banco ainda nao existir, crie o banco e importe <code class="rounded bg-white px-1">database/schema.sql</code>.</p>
                    <?php else: ?>
                        <p><strong>1.</strong> No painel da Hostinger, crie o banco MySQL e anote host, nome, usuario e senha.</p>
                        <p><strong>2.</strong> Copie <code class="rounded bg-white px-1">app/config/config.local.example.php</code> para <code class="rounded bg-white px-1">app/config/config.local.php</code> e preencha com esses dados (host geralmente e <code class="rounded bg-white px-1">localhost</code>).</p>
                        <p><strong>3.</strong> Importe <code class="rounded bg-white px-1">database/schema.sql</code> pelo phpMyAdmin.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}
