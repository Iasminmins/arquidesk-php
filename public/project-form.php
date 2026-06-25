<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stage = $_GET['stage'] ?? 'PROJETO';
$project = null;

if ($id) {
    $stmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
    $stmt->execute([$id, $companyId]);
    $project = $stmt->fetch();
    if (!$project) {
        redirect('/projects.php');
    }
    $stage = $project['current_stage'];
}

$designersStmt = db()->prepare("select id, name from users where company_id = ? and active = 1 and role in ('ADMIN_EMPRESA','PROJETISTA') order by name");
$designersStmt->execute([$companyId]);
$designers = $designersStmt->fetchAll();

$pageTitle = $project ? 'Editar projeto' : 'Criar projeto';

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<form method="post" action="/project-save.php" class="grid gap-4">
    <input type="hidden" name="id" value="<?= (int) ($project['id'] ?? 0) ?>">
    <input type="hidden" name="current_stage" value="<?= e($stage) ?>">

    <section class="rounded-lg border border-line bg-white p-4">
        <h2 class="font-bold">Dados do cliente e projeto</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="grid gap-1 text-sm font-semibold">Cliente
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="client_name" required value="<?= e($project['client_name'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Telefone
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="client_phone" required value="<?= e($project['client_phone'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold md:col-span-2">Endereço
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="client_address" value="<?= e($project['client_address'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Nome do projeto
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="project_name" required value="<?= e($project['project_name'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Projetista
                <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="designer_id">
                    <option value="">Sem responsavel</option>
                    <?php foreach ($designers as $designer): ?>
                        <option value="<?= (int) $designer['id'] ?>" <?= (int) ($project['designer_id'] ?? 0) === (int) $designer['id'] ? 'selected' : '' ?>>
                            <?= e($designer['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <section class="rounded-lg border border-line bg-white p-4">
        <h2 class="font-bold">Etapa e valores</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="grid gap-1 text-sm font-semibold">Status do projeto
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="project_status" value="<?= e($project['project_status'] ?? 'Sondagem') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Status negociacao
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="negotiation_status" value="<?= e($project['negotiation_status'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Valor fechado
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="closed_value" value="<?= e((string) ($project['closed_value'] ?? '')) ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Entrada
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="entry_date" value="<?= e($project['entry_date'] ?? date('Y-m-d')) ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Apresentação
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="presentation_date" value="<?= e($project['presentation_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Fechamento
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="closing_date" value="<?= e($project['closing_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Status conferência
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="conference_status" value="<?= e($project['conference_status'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Envio fábrica
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="sent_to_factory_date" value="<?= e($project['sent_to_factory_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Faturamento
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="billing_date" value="<?= e($project['billing_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Status montagem
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="assembly_status" value="<?= e($project['assembly_status'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Início montagem
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="assembly_started_date" value="<?= e($project['assembly_started_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Fim montagem
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="assembly_finished_date" value="<?= e($project['assembly_finished_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Status assistência
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="assistance_status" value="<?= e($project['assistance_status'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Data pedido
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="order_date" value="<?= e($project['order_date'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Data assistência
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="assistance_date" value="<?= e($project['assistance_date'] ?? '') ?>">
            </label>
        </div>
        <label class="mt-4 grid gap-1 text-sm font-semibold">Observacoes
            <textarea class="min-h-28 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="notes"><?= e($project['notes'] ?? '') ?></textarea>
        </label>
    </section>

    <div class="flex justify-end gap-2">
        <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="/projects.php?stage=<?= e($stage) ?>">Cancelar</a>
        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar projeto</button>
    </div>
</form>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>

