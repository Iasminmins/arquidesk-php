<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
$stage = $_GET['stage'] ?? 'PROJETO';
$allowedStages = ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'];
if (!in_array($stage, $allowedStages, true)) {
    $stage = 'PROJETO';
}

$view = $_GET['view'] ?? 'active';
$pageTitle = stage_label($stage);
$companyId = (int) $user['company_id'];
$search = trim($_GET['q'] ?? '');
$currentOrder = stage_order($stage);

$baseSql = "select p.*, u.name as designer_name
        from client_projects p
        left join users u on u.id = p.designer_id
        where p.company_id = ?";
$params = [$companyId];

if ($user['role'] === 'PROJETISTA') {
    $baseSql .= " and p.designer_id = ?";
    $params[] = (int) $user['id'];
}

if ($stage === 'FINALIZADO') {
    $baseSql .= " and p.current_stage = 'FINALIZADO'";
} elseif ($view === 'completed') {
    $later = array_values(array_filter($allowedStages, fn($item) => stage_order($item) > $currentOrder));
    $placeholders = implode(',', array_fill(0, count($later), '?'));
    $baseSql .= " and p.current_stage in ({$placeholders})";
    array_push($params, ...$later);
} elseif ($view === 'desistidas' && $stage === 'NEGOCIACAO') {
    $baseSql .= " and p.current_stage = 'NEGOCIACAO' and p.negotiation_status = 'Desistida'";
} else {
    $baseSql .= " and p.current_stage = ?";
    $params[] = $stage;
    if ($stage === 'NEGOCIACAO') {
        $baseSql .= " and (p.negotiation_status IS NULL or p.negotiation_status != 'Desistida')";
    }
}

if ($search !== '') {
    $baseSql .= " and (p.client_name like ? or p.project_name like ? or u.name like ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$baseSql .= " order by p.updated_at desc, p.created_at desc";
$stmt = db()->prepare($baseSql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

$countSql = "select current_stage, count(*) total from client_projects where company_id = ?";
$countParams = [$companyId];
if ($user['role'] === 'PROJETISTA') {
    $countSql .= " and designer_id = ?";
    $countParams[] = (int) $user['id'];
}
$countSql .= " group by current_stage";
$countStmt = db()->prepare($countSql);
$countStmt->execute($countParams);
$counts = [];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['current_stage']] = (int) $row['total'];
}
$activeCount = $counts[$stage] ?? 0;
$completedCount = 0;
foreach ($counts as $itemStage => $count) {
    if ($stage !== 'FINALIZADO' && stage_order($itemStage) > $currentOrder) {
        $completedCount += $count;
    }
}

// Count desistidas for NEGOCIACAO
$desistidasCount = 0;
if ($stage === 'NEGOCIACAO') {
    $dSql = "select count(*) from client_projects where company_id = ? and current_stage = 'NEGOCIACAO' and negotiation_status = 'Desistida'";
    $dParams = [$companyId];
    if ($user['role'] === 'PROJETISTA') { $dSql .= " and designer_id = ?"; $dParams[] = (int) $user['id']; }
    $dStmt = db()->prepare($dSql);
    $dStmt->execute($dParams);
    $desistidasCount = (int) $dStmt->fetchColumn();
    // Adjust active count to exclude desistidas
    $activeCount = max(0, $activeCount - $desistidasCount);
}

$canCreate = can_create_project($user, $stage);
$canEdit = can_write_project($user, $stage);
$canDelete = can_delete_project($user);

function project_status_for_stage(array $project, string $stage): string
{
    $field = status_field_for_stage($stage);
    if ($field && !empty($project[$field])) {
        return $project[$field];
    }

    return match ($stage) {
        'PROJETO' => 'Sondagem',
        'NEGOCIACAO' => 'Detalhamento de venda',
        'CONFERENCIA' => 'Medição',
        'MONTAGEM' => 'Vistoria de montagem',
        'ASSISTENCIA' => 'Aberta',
        default => 'Finalizado',
    };
}

function project_dates_for_stage(array $project, string $stage): array
{
    return match ($stage) {
        'PROJETO' => [['Entrada', $project['entry_date']], ['Medição', $project['measurement_date']], ['Apresentação', $project['presentation_date']]],
        'NEGOCIACAO' => [['Entrada', $project['entry_date']], ['Apresentação', $project['presentation_date']], ['Fechamento', $project['closing_date']]],
        'CONFERENCIA' => [['Medição', $project['measurement_date']], ['Envio fábrica', $project['sent_to_factory_date']], ['Faturamento', $project['billing_date']]],
        'MONTAGEM' => [['Início montagem', $project['assembly_started_date']], ['Fim montagem', $project['assembly_finished_date']]],
        'ASSISTENCIA' => [['Pedido', $project['order_date']], ['Assistência', $project['assistance_date']]],
        default => [['Finalizado', $project['finished_at']]],
    };
}

require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/includes/sidebar.php';
?>
<section class="grid gap-4">
    <?php if (!empty($_GET['ok'])): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"><?= e($_GET['msg'] ?? 'Operação concluída com sucesso.') ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <form method="get" class="flex flex-1 gap-2">
            <input type="hidden" name="stage" value="<?= e($stage) ?>">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input class="min-h-10 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-ink md:max-w-sm" name="q" value="<?= e($search) ?>" placeholder="Filtrar por cliente, projeto ou projetista">
            <button class="rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" type="submit">Filtrar</button>
        </form>
        <a class="inline-flex min-h-10 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-bold hover:bg-fog" href="/export.php?type=stage&stage=<?= e($stage) ?>&view=<?= e($view) ?>">Exportar</a>
        <?php if ($canCreate): ?>
            <a class="inline-flex min-h-10 items-center justify-center rounded-md bg-ink px-4 text-sm font-bold text-white" href="/project-form.php?stage=<?= e($stage) ?>"><?= $stage === 'ASSISTENCIA' ? 'Criar assistência' : 'Criar projeto' ?></a>
        <?php endif; ?>
    </div>

    <?php if ($stage !== 'FINALIZADO'): ?>
        <div class="grid gap-2 rounded-lg border border-line bg-white p-2 text-sm font-semibold sm:inline-grid sm:w-fit <?= $stage === 'NEGOCIACAO' ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?>">
            <a class="rounded-md px-4 py-2 <?= $view === 'active' || $view === '' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?stage=<?= e($stage) ?>&view=active">
                <?= e(stage_label($stage)) ?> em andamento <span class="ml-2 opacity-70"><?= $activeCount ?></span>
            </a>
            <a class="rounded-md px-4 py-2 <?= $view === 'completed' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?stage=<?= e($stage) ?>&view=completed">
                <?= e(stage_label($stage)) ?> finalizados <span class="ml-2 opacity-70"><?= $completedCount ?></span>
            </a>
            <?php if ($stage === 'NEGOCIACAO'): ?>
            <a class="rounded-md px-4 py-2 <?= $view === 'desistidas' ? 'bg-ink text-white' : 'hover:bg-fog' ?>" href="/projects.php?stage=NEGOCIACAO&view=desistidas">
                Desistidas <span class="ml-2 opacity-70"><?= $desistidasCount ?></span>
            </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="overflow-hidden rounded-lg border border-line bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-fog text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Projeto</th>
                        <th class="p-3">Projetista</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Datas</th>
                        <th class="p-3">Valor fechado</th>
                        <th class="p-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$projects): ?>
                        <tr><td colspan="7" class="p-6 text-center text-slate-500">Nenhum registro em <?= e(stage_label($stage)) ?>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($projects as $project): ?>
                        <tr class="border-t border-line align-top">
                            <td class="p-3"><strong><?= e($project['client_name']) ?></strong><span class="flex items-center gap-1 text-xs text-slate-500"><?= e($project['client_phone']) ?> <?= whatsapp_link($project['client_phone'] ?? '') ?></span></td>
                            <td class="p-3"><?= e($project['project_name']) ?></td>
                            <td class="p-3"><?= e($project['designer_name'] ?: '-') ?></td>
                            <td class="p-3">
                                <?php $field = status_field_for_stage($stage); ?>
                                <?php if ($canEdit && $field): ?>
                                    <form method="post" action="/project-status.php" class="js-status-form" data-id="<?= (int) $project['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                        <input type="hidden" name="stage" value="<?= e($stage) ?>">
                                        <input type="hidden" name="view" value="<?= e($view) ?>">
                                        <select class="min-h-10 w-full min-w-44 rounded-md border border-line bg-white px-2 text-sm outline-none focus:border-ink js-status-select" name="status" data-prev="<?= e(project_status_for_stage($project, $stage)) ?>">
                                            <?php foreach (status_options($stage) as $option): ?>
                                                <option value="<?= e($option) ?>" <?= project_status_for_stage($project, $stage) === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <?= e(project_status_for_stage($project, $stage)) ?>
                                <?php endif; ?>
                                <?php if ($project['current_stage'] !== $stage): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Hoje em <?= e(stage_label($project['current_stage'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-xs text-slate-600">
                                <?php foreach (project_dates_for_stage($project, $stage) as [$label, $value]): ?>
                                    <span class="block"><?= e($label) ?>: <?= e(date_br($value)) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="p-3"><?= money_br($project['closed_value'] ?? 0) ?></td>
                            <td class="p-3">
                                <div class="flex justify-end gap-1.5">
                                    <?php if ($canEdit): ?><a class="grid h-9 w-9 place-items-center rounded-md border border-line hover:bg-fog" href="/project-form.php?id=<?= (int) $project['id'] ?>" title="Editar"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></a><?php endif; ?>
                                    <a class="grid h-9 w-9 place-items-center rounded-md border border-line hover:bg-fog" href="/project-history.php?id=<?= (int) $project['id'] ?>" title="Histórico"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></a>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="/project-delete.php" class="js-delete-form" data-id="<?= (int) $project['id'] ?>" data-client="<?= e($project['client_name']) ?>" data-project="<?= e($project['project_name']) ?>" onsubmit="return confirm('Excluir este projeto?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="grid h-9 w-9 place-items-center rounded-md border border-red-200 text-red-600 hover:bg-red-50" type="submit" title="Excluir"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($stage === 'NEGOCIACAO' && $project['current_stage'] === 'NEGOCIACAO' && ($project['negotiation_status'] ?? '') !== 'Desistida' && $user['role'] !== 'CONFERENTE'): ?>
                                        <form method="post" action="/project-desistir.php" class="js-action-form" data-action="desistir" data-id="<?= (int) $project['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50" type="submit">Desistir</button>
                                        </form>
                                        <form method="post" action="/project-to-future.php" class="js-action-form" data-action="futuro" data-id="<?= (int) $project['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" type="submit">Futuro</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($stage === 'NEGOCIACAO' && ($project['negotiation_status'] ?? '') === 'Desistida' && $user['role'] !== 'CONFERENTE'): ?>
                                        <form method="post" action="/project-reativar.php" class="js-action-form" data-action="reativar" data-id="<?= (int) $project['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="inline-flex h-9 items-center whitespace-nowrap rounded-md bg-ink px-3 text-xs font-bold text-white" type="submit">Voltar para Negociação</button>
                                        </form>
                                    <?php elseif ($project['current_stage'] === $stage && next_stage($project['current_stage']) && $user['role'] !== 'CONFERENTE' && ($project['negotiation_status'] ?? '') !== 'Desistida'): ?>
                                        <?php $nextLabel = stage_label(next_stage($project['current_stage'])); ?>
                                        <form method="post" action="/project-move.php" class="js-move-form" data-id="<?= (int) $project['id'] ?>" data-from="<?= e($project['current_stage']) ?>" data-client="<?= e($project['client_name']) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                            <button class="inline-flex h-9 items-center gap-1.5 whitespace-nowrap rounded-md bg-ink px-3 text-xs font-bold text-white" type="submit">Enviar para <?= e($nextLabel) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Container de notificações (toast) -->
<div id="toast-root" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script>
(function () {
    const csrf = <?= json_encode(csrf_token()) ?>;
    const toastRoot = document.getElementById('toast-root');

    function showToast(message, undoCallback) {
        const el = document.createElement('div');
        el.className = 'flex items-center gap-3 rounded-lg bg-ink px-4 py-3 text-sm text-white shadow-lg';
        el.style.minWidth = '260px';

        const text = document.createElement('span');
        text.className = 'flex-1';
        text.textContent = message;
        el.appendChild(text);

        let timer = null;
        if (undoCallback) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'shrink-0 rounded-md bg-white/15 px-3 py-1 font-bold hover:bg-white/25';
            btn.textContent = 'Desfazer';
            btn.addEventListener('click', function () {
                if (timer) clearTimeout(timer);
                el.remove();
                undoCallback();
            });
            el.appendChild(btn);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'shrink-0 text-white/60 hover:text-white';
        close.textContent = '✕';
        close.addEventListener('click', function () { if (timer) clearTimeout(timer); el.remove(); });
        el.appendChild(close);

        toastRoot.appendChild(el);
        timer = setTimeout(function () { el.remove(); }, 6000);
    }

    async function postForm(url, data) {
        const body = new URLSearchParams(data);
        body.set('csrf_token', csrf);
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        });
        return resp.json();
    }

    // ---- Mudança de status ----
    document.querySelectorAll('.js-status-select').forEach(function (select) {
        select.addEventListener('change', async function () {
            const form = select.closest('.js-status-form');
            const id = form.dataset.id;
            const stage = form.querySelector('[name="stage"]').value;
            const view = form.querySelector('[name="view"]').value;
            const newStatus = select.value;
            const oldStatus = select.dataset.prev;
            select.disabled = true;

            try {
                const res = await postForm('/project-status.php', { id, stage, view, status: newStatus });
                if (!res.ok) {
                    select.value = oldStatus;
                    showToast(res.error || 'Não foi possível salvar.', null);
                } else {
                    select.dataset.prev = newStatus;
                    showToast('Status salvo.', async function () {
                        const undo = await postForm('/project-status.php', { id, stage, view, status: oldStatus });
                        if (undo.ok) {
                            select.value = oldStatus;
                            select.dataset.prev = oldStatus;
                            showToast('Alteração desfeita.', null);
                        }
                    });
                }
            } catch (e) {
                select.value = oldStatus;
                showToast('Erro de conexão.', null);
            } finally {
                select.disabled = false;
            }
        });
    });

    // ---- Mover etapa ----
    document.querySelectorAll('.js-move-form').forEach(function (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const id = form.dataset.id;
            const fromStage = form.dataset.from;
            const client = form.dataset.client || 'Projeto';
            const btn = form.querySelector('button[type="submit"]');
            const row = form.closest('tr');
            if (btn) btn.disabled = true;

            try {
                const res = await postForm('/project-move.php', { id });
                if (!res.ok) {
                    showToast(res.error || 'Não foi possível mover.', null);
                    if (btn) btn.disabled = false;
                    return;
                }
                // A linha sai deste estágio: removemos visualmente
                if (row) row.style.transition = 'opacity .3s';
                if (row) row.style.opacity = '0.4';

                showToast(res.message || 'Movido com sucesso.', async function () {
                    const undo = await postForm('/project-move-undo.php', {
                        id, from_stage: res.from_stage, to_stage: res.to_stage,
                    });
                    if (undo.ok) {
                        if (row) row.style.opacity = '1';
                        showToast('Movimentação desfeita.', null);
                    } else {
                        showToast(undo.error || 'Não foi possível desfazer.', null);
                    }
                });

                // Remove a linha de vez após a janela de desfazer
                setTimeout(function () {
                    if (row && row.style.opacity === '0.4') row.remove();
                }, 6200);
            } catch (e) {
                showToast('Erro de conexão.', null);
                if (btn) btn.disabled = false;
            }
        });
    });

    // ---- Desistir / Futuro / Reativar (com desfazer) ----
    const actionConfig = {
        desistir: {
            url: '/project-desistir.php',
            success: 'Marcado como desistida.',
            removeRow: true,
            undo: function (res, id) {
                return postForm('/project-negotiation-undo.php', { id, target_status: res.prev_status || '' });
            },
        },
        futuro: {
            url: '/project-to-future.php',
            success: 'Enviado para Clientes Futuros.',
            removeRow: true,
            undo: function (res, id) {
                return postForm('/project-to-future-undo.php', { id, future_id: res.future_id || 0, prev_status: res.prev_status || '' });
            },
        },
        reativar: {
            url: '/project-reativar.php',
            success: 'Negociação reativada.',
            removeRow: true,
            undo: function (res, id) {
                return postForm('/project-negotiation-undo.php', { id, target_status: 'Desistida' });
            },
        },
    };

    document.querySelectorAll('.js-action-form').forEach(function (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const action = form.dataset.action;
            const cfg = actionConfig[action];
            if (!cfg) return;
            const id = form.dataset.id;
            const btn = form.querySelector('button[type="submit"]');
            const row = form.closest('tr');
            if (btn) btn.disabled = true;

            try {
                const res = await postForm(cfg.url, { id });
                if (!res.ok) {
                    showToast(res.error || 'Não foi possível concluir.', null);
                    if (btn) btn.disabled = false;
                    return;
                }
                if (cfg.removeRow && row) { row.style.transition = 'opacity .3s'; row.style.opacity = '0.4'; }

                showToast(cfg.success, async function () {
                    const undo = await cfg.undo(res, id);
                    if (undo.ok) {
                        if (row) row.style.opacity = '1';
                        showToast('Ação desfeita.', null);
                    } else {
                        showToast(undo.error || 'Não foi possível desfazer.', null);
                    }
                });

                setTimeout(function () {
                    if (row && row.style.opacity === '0.4') row.remove();
                }, 6200);
            } catch (e) {
                showToast('Erro de conexão.', null);
                if (btn) btn.disabled = false;
            }
        });
    });

    // ---- Excluir (modal de confirmação, sem desfazer pois é destrutivo) ----
    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.removeAttribute('onsubmit');
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const client = form.dataset.client || '';
            const projectName = form.dataset.project || '';
            const id = form.dataset.id;
            const row = form.closest('tr');

            const confirmed = await confirmDelete(client, projectName);
            if (!confirmed) return;

            try {
                const res = await postForm('/project-delete.php', { id });
                if (!res.ok) {
                    showToast(res.error || 'Não foi possível excluir.', null);
                    return;
                }
                if (row) { row.style.transition = 'opacity .3s'; row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                showToast('Projeto excluído.', null);
            } catch (e) {
                showToast('Erro de conexão.', null);
            }
        });
    });

    // Modal de confirmação de exclusão
    function confirmDelete(client, projectName) {
        return new Promise(function (resolve) {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4';

            const box = document.createElement('div');
            box.className = 'w-full max-w-sm rounded-lg bg-white p-5 shadow-xl';
            const nome = [client, projectName].filter(Boolean).join(' — ') || 'este projeto';
            box.innerHTML =
                '<h3 class="text-lg font-bold text-ink">Excluir projeto</h3>' +
                '<p class="mt-2 text-sm text-slate-600">Tem certeza que deseja excluir <strong></strong>? Esta ação não pode ser desfeita.</p>' +
                '<div class="mt-5 flex justify-end gap-2">' +
                '<button type="button" class="rounded-md border border-line px-4 py-2 text-sm font-semibold hover:bg-fog" data-cancel>Cancelar</button>' +
                '<button type="button" class="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700" data-ok>Excluir</button>' +
                '</div>';
            box.querySelector('strong').textContent = nome;

            overlay.appendChild(box);
            document.body.appendChild(overlay);

            function close(result) { overlay.remove(); resolve(result); }
            box.querySelector('[data-cancel]').addEventListener('click', function () { close(false); });
            box.querySelector('[data-ok]').addEventListener('click', function () { close(true); });
            overlay.addEventListener('click', function (e) { if (e.target === overlay) close(false); });
        });
    }
})();
</script>
<?php require __DIR__ . '/../app/includes/footer.php'; ?>
