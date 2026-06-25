<?php

$config = require __DIR__ . '/../app/config/config.php';
$db = $config['db'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = "mysql:host={$db['host']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $database = str_replace('`', '', $db['name']);
        $charset = $db['charset'];
        $pdo->exec("create database if not exists `{$database}` character set {$charset} collate {$charset}_unicode_ci");
        $pdo->exec("use `{$database}`");

        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        foreach ([
            "alter table subscriptions add column if not exists provider varchar(80) null",
            "alter table subscriptions add column if not exists external_customer_id varchar(160) null",
            "alter table subscriptions add column if not exists external_subscription_id varchar(160) null",
            "alter table subscriptions add column if not exists checkout_url varchar(255) null",
            "alter table subscriptions add column if not exists selected_plan_key varchar(40) null",
        ] as $alter) {
            try {
                $pdo->exec($alter);
            } catch (Throwable $ignored) {
            }
        }
        $message = 'Banco criado/atualizado com sucesso. Agora crie o primeiro administrador ou entre no sistema.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar banco - Arquidesk</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto grid min-h-screen max-w-2xl place-items-center px-4">
        <section class="w-full rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold">Instalar banco MySQL</h1>
            <p class="mt-2 text-sm text-slate-600">
                Esta tela cria o banco <strong><?= h($db['name']) ?></strong> e importa o schema inicial.
                Antes disso, o MySQL precisa estar ligado.
            </p>

            <div class="mt-5 rounded-md bg-slate-50 p-4 text-sm">
                <p><strong>Host:</strong> <?= h($db['host']) ?></p>
                <p><strong>Banco:</strong> <?= h($db['name']) ?></p>
                <p><strong>Usuário:</strong> <?= h($db['user']) ?></p>
            </div>

            <?php if ($message): ?>
                <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= h($message) ?></div>
                <a class="mt-5 inline-flex min-h-10 items-center rounded-md bg-slate-900 px-4 text-sm font-bold text-white" href="/setup.php">Ir para setup</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="mt-5">
                    <button class="min-h-10 rounded-md bg-slate-900 px-4 text-sm font-bold text-white" type="submit">
                        Criar/importar banco
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
