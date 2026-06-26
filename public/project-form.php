<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
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

$statusOptions = status_options($stage);
$pageTitle = $project ? 'Editar projeto' : ($stage === 'ASSISTENCIA' ? 'Criar assistência' : 'Criar projeto');

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<form method="post" action="/project-save.php" class="grid gap-4">
    <?= csrf_field() ?>
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
            <label class="grid gap-1 text-sm font-semibold">Endereço
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="client_address" value="<?= e($project['client_address'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Nome do projeto
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="project_name" required value="<?= e($project['project_name'] ?? '') ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Projetista responsável
                <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="designer_id" required <?= $user['role'] === 'PROJETISTA' ? 'disabled' : '' ?>>
                    <option value="">Selecione</option>
                    <?php foreach ($designers as $designer): ?>
                        <option value="<?= (int) $designer['id'] ?>" <?= (int) ($project['designer_id'] ?? ($user['role'] === 'PROJETISTA' ? $user['id'] : 0)) === (int) $designer['id'] ? 'selected' : '' ?>>
                            <?= e($designer['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($user['role'] === 'PROJETISTA'): ?>
                    <input type="hidden" name="designer_id" value="<?= (int) $user['id'] ?>">
                <?php endif; ?>
            </label>
        </div>
    </section>

    <section class="rounded-lg border border-line bg-white p-4">
        <h2 class="font-bold">Etapa: <?= e(stage_label($stage)) ?></h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <?php if ($stage === 'PROJETO'): ?>
                <label class="grid gap-1 text-sm font-semibold">Status
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="project_status">
                        <?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ($project['project_status'] ?? 'Sondagem') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de entrada
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="entry_date" value="<?= e($project['entry_date'] ?? date('Y-m-d')) ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de medição
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="measurement_date" value="<?= e($project['measurement_date'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de apresentação
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="presentation_date" value="<?= e($project['presentation_date'] ?? '') ?>">
                </label>
            <?php elseif ($stage === 'NEGOCIACAO'): ?>
                <label class="grid gap-1 text-sm font-semibold">Status
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="negotiation_status">
                        <?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ($project['negotiation_status'] ?? 'Detalhamento de venda') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Valor da nova proposta
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="new_proposal_value" value="<?= e((string) ($project['new_proposal_value'] ?? '')) ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Valor fechado
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="closed_value" value="<?= e((string) ($project['closed_value'] ?? '')) ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de fechamento
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="closing_date" value="<?= e($project['closing_date'] ?? '') ?>">
                </label>
            <?php elseif ($stage === 'CONFERENCIA'): ?>
                <label class="grid gap-1 text-sm font-semibold">Status
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="conference_status">
                        <?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ($project['conference_status'] ?? 'Medição') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de medição
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="measurement_date" value="<?= e($project['measurement_date'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de envio para fábrica
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="sent_to_factory_date" value="<?= e($project['sent_to_factory_date'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de faturamento
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="billing_date" value="<?= e($project['billing_date'] ?? '') ?>">
                </label>
            <?php elseif ($stage === 'MONTAGEM'): ?>
                <label class="grid gap-1 text-sm font-semibold">Status
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="assembly_status">
                        <?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ($project['assembly_status'] ?? 'Vistoria de montagem') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de início da montagem
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="assembly_started_date" value="<?= e($project['assembly_started_date'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de finalização da montagem
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="assembly_finished_date" value="<?= e($project['assembly_finished_date'] ?? '') ?>">
                </label>
            <?php elseif ($stage === 'ASSISTENCIA'): ?>
                <label class="grid gap-1 text-sm font-semibold">Status
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="assistance_status">
                        <?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ($project['assistance_status'] ?? 'Aberta') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data da assistência
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="assistance_date" value="<?= e($project['assistance_date'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data do pedido
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="order_date" value="<?= e($project['order_date'] ?? '') ?>">
                </label>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($project && $stage !== 'NEGOCIACAO'): ?>
    <section class="rounded-lg border border-line bg-white p-4">
        <h2 class="font-bold">Dados da negociação</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="grid gap-1 text-sm font-semibold">Valor fechado
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="closed_value" value="<?= e((string) ($project['closed_value'] ?? '')) ?>">
            </label>
            <label class="grid gap-1 text-sm font-semibold">Data de fechamento
                <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="closing_date" value="<?= e($project['closing_date'] ?? '') ?>">
            </label>
        </div>
    </section>
    <?php endif; ?>

    <section class="rounded-lg border border-line bg-white p-4">
        <label class="grid gap-1 text-sm font-semibold">Observações
            <textarea class="min-h-28 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="notes"><?= e($project['notes'] ?? '') ?></textarea>
        </label>
    </section>

    <div class="flex justify-end gap-2">
        <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="/projects.php?stage=<?= e($stage) ?>">Cancelar</a>
        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar</button>
    </div>
</form>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
