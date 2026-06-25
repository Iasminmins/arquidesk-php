<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$pageTitle = 'Importar / Exportar';

$imports = db()->prepare('select * from import_batches where company_id = ? order by created_at desc limit 10');
$imports->execute([$companyId]);
$imports = $imports->fetchAll();

$exports = db()->prepare('select * from export_logs where company_id = ? order by created_at desc limit 10');
$exports->execute([$companyId]);
$exports = $exports->fetchAll();

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <div class="rounded-lg border border-line bg-white p-4">
        <h2 class="font-bold">Importação e exportação</h2>
        <p class="mt-1 text-sm text-slate-500">Nesta versão PHP inicial a exportação CSV já funciona. A importacao em massa pode ser evoluida depois com leitura XLSX.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="grid gap-4 rounded-lg border border-line bg-white p-4">
            <h3 class="font-bold">Exportar dados</h3>
            <form method="get" action="/export.php" class="grid gap-3">
                <label class="grid gap-1 text-sm font-semibold">Dados
                    <select class="min-h-10 rounded-md border border-line px-3" name="type">
                        <option value="projects">Projetos</option>
                        <option value="finance">Financeiro</option>
                        <option value="payments">Pagamentos</option>
                        <option value="goals">Metas</option>
                        <option value="employees">Funcionários</option>
                        <option value="history">Historico</option>
                    </select>
                </label>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Baixar CSV</button>
            </form>
        </section>

        <section class="grid gap-4 rounded-lg border border-line bg-white p-4">
            <h3 class="font-bold">Importar dados</h3>
            <p class="text-sm text-slate-600">Para manter seguro na Hostinger, a importacao em massa sera feita por CSV validado em uma proxima etapa. Por enquanto cadastre pelos formulários ou importe o SQL pelo phpMyAdmin.</p>
            <a class="inline-flex min-h-10 w-fit items-center rounded-md border border-line px-4 text-sm font-semibold hover:bg-fog" href="/projects.php?stage=PROJETO">Cadastrar projetos</a>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Ultimas importacoes</div>
            <table class="w-full text-left text-sm"><thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Tipo</th><th class="p-3">Status</th><th class="p-3">Linhas</th><th class="p-3">Data</th></tr></thead><tbody><?php foreach ($imports as $row): ?><tr class="border-t border-line"><td class="p-3"><?= e($row['type']) ?></td><td class="p-3"><?= e($row['status']) ?></td><td class="p-3"><?= (int) $row['success_rows'] ?>/<?= (int) $row['total_rows'] ?></td><td class="p-3"><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table>
        </section>
        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="border-b border-line p-4 font-bold">Ultimas exportacoes</div>
            <table class="w-full text-left text-sm"><thead class="bg-fog text-xs uppercase text-slate-500"><tr><th class="p-3">Tipo</th><th class="p-3">Formato</th><th class="p-3">Data</th></tr></thead><tbody><?php foreach ($exports as $row): ?><tr class="border-t border-line"><td class="p-3"><?= e($row['type']) ?></td><td class="p-3"><?= e($row['format']) ?></td><td class="p-3"><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table>
        </section>
    </div>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>

