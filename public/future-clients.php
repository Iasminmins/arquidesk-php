<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true)) {
    redirect('/');
}

$companyId = (int) $user['company_id'];
$isDesigner = $user['role'] === 'PROJETISTA';

$statusLabels = [
    'NOVO' => 'Novo',
    'EM_CONTATO' => 'Em contato',
    'NEGOCIANDO' => 'Negociando',
    'AGUARDANDO' => 'Aguardando retorno',
    'CONVERTIDO' => 'Convertido',
    'PERDIDO' => 'Perdido',
];

$sourceOptions = ['Indicação', 'Instagram', 'Facebook', 'Google', 'Site', 'Evento', 'Outro'];

// Save (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $designerId = $isDesigner ? (int) $user['id'] : ((int) ($_POST['designer_id'] ?? 0) ?: null);
    $payload = [
        $companyId, $designerId,
        trim($_POST['name'] ?? ''),
        trim($_POST['phone'] ?? ''),
        null_if_empty($_POST['email'] ?? ''),
        null_if_empty($_POST['address'] ?? ''),
        null_if_empty($_POST['interest'] ?? ''),
        ((float) ($_POST['estimated_value'] ?? 0)) ?: null,
        null_if_empty($_POST['contact_date'] ?? ''),
        null_if_empty($_POST['next_contact_date'] ?? ''),
        null_if_empty($_POST['source'] ?? ''),
        $_POST['status'] ?? 'NOVO',
        null_if_empty($_POST['notes'] ?? ''),
    ];
    if ($id) {
        $stmt = db()->prepare('update future_clients set company_id=?, designer_id=?, name=?, phone=?, email=?, address=?, interest=?, estimated_value=?, contact_date=?, next_contact_date=?, source=?, status=?, notes=? where id=? and company_id=?');
        $stmt->execute([...$payload, $id, $companyId]);
    } else {
        $stmt = db()->prepare('insert into future_clients (company_id, designer_id, name, phone, email, address, interest, estimated_value, contact_date, next_contact_date, source, status, notes) values (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute($payload);
    }
    redirect('/future-clients.php?ok=1');
}

// Convert to project
if (!empty($_GET['convert'])) {
    $fcId = (int) $_GET['convert'];
    $fcStmt = db()->prepare('select * from future_clients where id = ? and company_id = ? and status != ? limit 1');
    $fcStmt->execute([$fcId, $companyId, 'CONVERTIDO']);
    $fc = $fcStmt->fetch();
    if ($fc) {
        $pdo = db();
        $pdo->beginTransaction();
        $ins = $pdo->prepare('insert into client_projects (company_id, designer_id, client_name, client_address, client_phone, project_name, current_stage, project_status, entry_date, notes) values (?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([
            $companyId,
            $fc['designer_id'],
            $fc['name'],
            $fc['address'],
            $fc['phone'],
            $fc['interest'] ?: 'Projeto de ' . $fc['name'],
            'PROJETO',
            'Sondagem',
            date('Y-m-d'),
            'Convertido de Cliente Futuro. ' . ($fc['notes'] ?? ''),
        ]);
        $projectId = (int) $pdo->lastInsertId();
        $pdo->prepare('update future_clients set status = ?, converted_project_id = ? where id = ?')->execute(['CONVERTIDO', $projectId, $fcId]);
        $pdo->prepare('insert into flow_history (company_id, client_project_id, to_stage, action, user_id) values (?,?,?,?,?)')->execute([
            $companyId, $projectId, 'PROJETO', 'Criado a partir de Cliente Futuro', (int) $user['id'],
        ]);
        $pdo->commit();
        redirect('/future-clients.php?converted=1');
    }
}

// Delete
if (!empty($_GET['delete'])) {
    db()->prepare('delete from future_clients where id = ? and company_id = ?')->execute([(int) $_GET['delete'], $companyId]);
    redirect('/future-clients.php?ok=1');
}

// Inline status update
if (!empty($_POST['update_status'])) {
    $fcId = (int) ($_POST['fc_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    if ($fcId && $newStatus) {
        db()->prepare('update future_clients set status = ? where id = ? and company_id = ?')->execute([$newStatus, $fcId, $companyId]);
    }
    redirect('/future-clients.php');
}

// Load data
$view = $_GET['view'] ?? 'active';
$search = trim($_GET['q'] ?? '');

$sql = "select fc.*, u.name as designer_name from future_clients fc left join users u on u.id = fc.designer_id where fc.company_id = ?";
$params = [$companyId];
if ($isDesigner) {
    $sql .= " and fc.designer_id = ?";
    $params[] = (int) $user['id'];
}
if ($view === 'active') {
    $sql .= " and fc.status not in ('CONVERTIDO','PERDIDO')";
} elseif ($view === 'converted') {
    $sql .= " and fc.status = 'CONVERTIDO'";
} elseif ($view === 'lost') {
    $sql .= " and fc.status = 'PERDIDO'";
}
if ($search !== '') {
    $sql .= " and (fc.name like ? or fc.phone like ? or fc.interest like ?)";
    $term = "%{$search}%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}
$sql .= " order by fc.updated_at desc, fc.created_at desc";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Count by view
$countStmt = db()->prepare("select status, count(*) as total from future_clients where company_id = ?" . ($isDesigner ? " and designer_id = ?" : "") . " group by status");
$countParams = $isDesigner ? [$companyId, (int) $user['id']] : [$companyId];
$countStmt->execute($countParams);
$statusCounts = [];
foreach ($countStmt->fetchAll() as $row) { $statusCounts[$row['status']] = (int) $row['total']; }
$activeCount = ($statusCounts['NOVO'] ?? 0) + ($statusCounts['EM_CONTATO'] ?? 0) + ($statusCounts['NEGOCIANDO'] ?? 0) + ($statusCounts['AGUARDANDO'] ?? 0);
$convertedCount = $statusCounts['CONVERTIDO'] ?? 0;
$lostCount = $statusCounts['PERDIDO'] ?? 0;

// Edit
$edit = null;
if (!empty($_GET['edit'])) {
    $editStmt = db()->prepare('select * from future_clients where id = ? and company_id = ?');
    $editStmt->execute([(int) $_GET['edit'], $companyId]);
    $edit = $editStmt->fetch();
}

// Designers list
$designersStmt = db()->prepare("select id, name from users where company_id = ? and active = 1 and role in ('ADMIN_EMPRESA','PROJETISTA') order by name");
$designersStmt->execute([$companyId]);
$designers = $designersStmt->fetchAll();

$pageTitle = 'Clientes Futuros';
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-5">
    <?php if (!empty($_GET['ok'])): ?><div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">Operação concluída.</div><?php endif; ?>
    <?php if (!empty($_GET['converted'])): ?><div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Cliente convertido em projeto com sucesso! Ele já aparece na etapa Projeto.</div><?php endif; ?>

    <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <form method="get" class="flex flex-1 gap-2">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input class="min-h-10 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-ink md:max-w-sm" name="q" value="<?= e($search) ?>" placeholder="Buscar por nome, telefone ou interesse">
            <button class="rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Filtrar</button>
        </form>
        <a class="inline-flex min-h-10 items-center justify-center rounded-md bg-ink px-4 text-sm font-bold text-white hover:opacity-95" href="/future-clients.php?edit=new">+ Novo cliente futuro</a>
    </div>

    <div class="grid gap-2 rounded-lg border border-line bg-white p-2 text-sm font-semibold sm:inline-grid sm:w-fit sm:grid-cols-3">
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'active' || $view === '' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/future-clients.php?view=active">Ativos <span class="ml-1 opacity-70"><?= $activeCount ?></span></a>
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'converted' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/future-clients.php?view=converted">Convertidos <span class="ml-1 opacity-70"><?= $convertedCount ?></span></a>
        <a class="rounded-md px-4 py-2 text-center <?= $view === 'lost' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/future-clients.php?view=lost">Perdidos <span class="ml-1 opacity-70"><?= $lostCount ?></span></a>
    </div>

    <?php if ($edit !== null || isset($_GET['edit'])): ?>
    <?php $isNew = ($_GET['edit'] ?? '') === 'new'; ?>
    <section class="rounded-lg border border-line bg-white p-5">
        <h3 class="text-lg font-bold"><?= $isNew ? 'Novo cliente futuro' : 'Editar cliente futuro' ?></h3>
        <form method="post" class="mt-4 grid gap-4">
            <input type="hidden" name="save" value="1">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="grid gap-1 text-sm font-semibold">Nome *
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="name" required value="<?= e($edit['name'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Telefone *
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="phone" required value="<?= e($edit['phone'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">E-mail
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="email" name="email" value="<?= e($edit['email'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Endereço
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="address" value="<?= e($edit['address'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Interesse (tipo de projeto)
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="interest" placeholder="Ex: Cozinha planejada, Dormitório" value="<?= e($edit['interest'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Valor estimado
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="number" step="0.01" name="estimated_value" value="<?= e((string) ($edit['estimated_value'] ?? '')) ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Data de contato
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="contact_date" value="<?= e($edit['contact_date'] ?? date('Y-m-d')) ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Próximo contato
                    <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="date" name="next_contact_date" value="<?= e($edit['next_contact_date'] ?? '') ?>">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Origem
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="source">
                        <option value="">Selecione</option>
                        <?php foreach ($sourceOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ($edit['source'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Status
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="status">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <?php if ($key === 'CONVERTIDO') continue; ?>
                            <option value="<?= e($key) ?>" <?= ($edit['status'] ?? 'NOVO') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Responsável
                    <select class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="designer_id" <?= $isDesigner ? 'disabled' : '' ?>>
                        <option value="">Sem responsável</option>
                        <?php foreach ($designers as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) ($edit['designer_id'] ?? ($isDesigner ? $user['id'] : 0)) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($isDesigner): ?><input type="hidden" name="designer_id" value="<?= (int) $user['id'] ?>"><?php endif; ?>
                </label>
            </div>
            <label class="grid gap-1 text-sm font-semibold">Observações
                <textarea class="min-h-24 rounded-md border border-line px-3 py-2 outline-none focus:border-ink" name="notes"><?= e($edit['notes'] ?? '') ?></textarea>
            </label>
            <div class="flex justify-end gap-2">
                <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="/future-clients.php">Cancelar</a>
                <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Salvar</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Nome</th>
                        <th class="p-3">Telefone</th>
                        <th class="p-3">Interesse</th>
                        <th class="p-3">Valor estimado</th>
                        <th class="p-3">Origem</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Próx. contato</th>
                        <th class="p-3">Responsável</th>
                        <th class="p-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$clients): ?>
                        <tr><td colspan="9" class="p-8 text-center text-slate-500">Nenhum cliente futuro nessa visualização.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($clients as $fc): ?>
                        <?php
                        $statusColor = match($fc['status']) {
                            'NOVO' => 'bg-blue-100 text-blue-700',
                            'EM_CONTATO' => 'bg-amber-100 text-amber-700',
                            'NEGOCIANDO' => 'bg-purple-100 text-purple-700',
                            'AGUARDANDO' => 'bg-slate-100 text-slate-600',
                            'CONVERTIDO' => 'bg-emerald-100 text-emerald-700',
                            'PERDIDO' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-600',
                        };
                        ?>
                        <tr class="border-t border-line align-top hover:bg-fog/40">
                            <td class="p-3">
                                <strong><?= e($fc['name']) ?></strong>
                                <?php if ($fc['email']): ?><span class="block text-xs text-slate-500"><?= e($fc['email']) ?></span><?php endif; ?>
                            </td>
                            <td class="p-3"><span class="flex items-center gap-1"><?= e($fc['phone']) ?> <?= whatsapp_link($fc['phone']) ?></span></td>
                            <td class="p-3"><?= e($fc['interest'] ?: '-') ?></td>
                            <td class="p-3"><?= $fc['estimated_value'] ? money_br($fc['estimated_value']) : '-' ?></td>
                            <td class="p-3"><?= e($fc['source'] ?: '-') ?></td>
                            <td class="p-3">
                                <?php if ($fc['status'] !== 'CONVERTIDO'): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="fc_id" value="<?= (int) $fc['id'] ?>">
                                    <select class="min-h-8 rounded-md border border-line px-2 text-xs font-semibold <?= $statusColor ?>" name="new_status" onchange="this.form.submit()">
                                        <?php foreach ($statusLabels as $key => $label): ?>
                                            <?php if ($key === 'CONVERTIDO') continue; ?>
                                            <option value="<?= e($key) ?>" <?= $fc['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <?php else: ?>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusColor ?>"><?= e($statusLabels[$fc['status']]) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-xs">
                                <?php if ($fc['next_contact_date']): ?>
                                    <?php $isOverdue = $fc['next_contact_date'] < date('Y-m-d') && $fc['status'] !== 'CONVERTIDO' && $fc['status'] !== 'PERDIDO'; ?>
                                    <span class="<?= $isOverdue ? 'font-bold text-red-600' : '' ?>"><?= e(date_br($fc['next_contact_date'])) ?></span>
                                    <?php if ($isOverdue): ?><span class="block text-red-500">Atrasado</span><?php endif; ?>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="p-3"><?= e($fc['designer_name'] ?: '-') ?></td>
                            <td class="p-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a class="rounded-md border border-line px-3 py-1.5 text-xs font-semibold hover:bg-fog" href="/future-clients.php?edit=<?= (int) $fc['id'] ?>">Editar</a>
                                    <?php if ($fc['status'] !== 'CONVERTIDO'): ?>
                                        <a class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-800" href="/future-clients.php?convert=<?= (int) $fc['id'] ?>" onclick="return confirm('Converter <?= e($fc['name']) ?> em projeto?')">Converter em Projeto</a>
                                    <?php else: ?>
                                        <a class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700" href="/projects.php?stage=PROJETO">Ver projeto</a>
                                    <?php endif; ?>
                                    <a class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600" href="/future-clients.php?delete=<?= (int) $fc['id'] ?>" onclick="return confirm('Excluir <?= e($fc['name']) ?>?')">Excluir</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
