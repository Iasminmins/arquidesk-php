<?php

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'arquidesk-php-sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/contracts.php';

contracts_bootstrap();

$token = $_GET['t'] ?? $_POST['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Contrato nao encontrado.');
}

$stmt = db()->prepare(
    'select pc.*, p.client_name, p.client_phone, p.client_address, p.project_name, p.closed_value, p.closing_date,
            c.name as company_name, c.logo_url, c.primary_color
     from project_contracts pc
     join client_projects p on p.id = pc.client_project_id and p.company_id = pc.company_id
     join companies c on c.id = pc.company_id
     where pc.public_token = ?
     limit 1'
);
$stmt->execute([$token]);
$contract = $stmt->fetch();

if (!$contract || $contract['status'] === 'CANCELED') {
    http_response_code(404);
    exit('Contrato nao encontrado ou cancelado.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $alreadyCompleted = in_array($contract['status'], ['ACCEPTED', 'SIGNED_GOVBR', 'SIGNED_MANUAL'], true);

    if ($alreadyCompleted) {
        $error = 'Este contrato ja foi finalizado.';
    } elseif ($action === 'upload_govbr') {
        if (empty($_FILES['signed_file']) || $_FILES['signed_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Envie o PDF assinado pelo gov.br.';
        } else {
            $maxBytes = 15 * 1024 * 1024;
            $originalName = basename($_FILES['signed_file']['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['signed_file']['tmp_name']);

            if ($_FILES['signed_file']['size'] > $maxBytes) {
                $error = 'Arquivo muito grande. Maximo: 15 MB.';
            } elseif ($ext !== 'pdf' || $mimeType !== 'application/pdf') {
                $error = 'Envie somente o PDF assinado.';
            } else {
                $storedName = sprintf('contrato_%d_govbr_%s.pdf', (int) $contract['id'], bin2hex(random_bytes(5)));
                $uploadDir = __DIR__ . '/../uploads/contracts/' . (int) $contract['company_id'] . '/' . (int) $contract['client_project_id'] . '/' . (int) $contract['id'] . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($_FILES['signed_file']['tmp_name'], $uploadDir . $storedName)) {
                    db()->prepare(
                        'update project_contracts
                         set status = ?, signature_method = ?, signed_file_original = ?, signed_file_stored = ?,
                             signed_file_size = ?, signed_file_mime = ?, signed_file_uploaded_at = now()
                         where id = ?'
                    )->execute([
                        'SIGNED_GOVBR',
                        'GOVBR_UPLOAD',
                        $originalName,
                        $storedName,
                        (int) $_FILES['signed_file']['size'],
                        $mimeType,
                        (int) $contract['id'],
                    ]);
                    redirect('/contract-public.php?t=' . $token . '&govbr=1');
                } else {
                    $error = 'Nao foi possivel salvar o arquivo.';
                }
            }
        }
    } elseif ($action === 'draw_signature') {
        $name = trim($_POST['accepted_name'] ?? '');
        $document = trim($_POST['accepted_document'] ?? '');
        $email = trim($_POST['accepted_email'] ?? '');
        $signatureData = $_POST['signature_data'] ?? '';
        $agree = !empty($_POST['agree']);

        if ($name === '' || $document === '' || !$agree) {
            $error = 'Preencha nome, documento e confirme o aceite.';
        } elseif (!is_string($signatureData) || !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signatureData)) {
            $error = 'Desenhe a assinatura no campo indicado.';
        } elseif (strlen($signatureData) > 900000) {
            $error = 'A assinatura ficou muito grande. Limpe e assine novamente.';
        } else {
            $signedAt = date('Y-m-d H:i:s');
            db()->prepare(
                'update project_contracts
                 set status = ?, signature_method = ?, accepted_name = ?, accepted_document = ?, accepted_email = ?,
                     accepted_ip = ?, accepted_user_agent = ?, accepted_at = ?, manual_signature_data = ?
                 where id = ?'
            )->execute([
                'SIGNED_MANUAL',
                'MANUAL_DRAW',
                $name,
                $document,
                $email !== '' ? $email : null,
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $signedAt,
                $signatureData,
                (int) $contract['id'],
            ]);
            $signedPdf = contract_generate_manual_signed_pdf($contract, $signatureData, $name, date('d/m/Y H:i', strtotime($signedAt)));
            if ($signedPdf) {
                db()->prepare(
                    'update project_contracts
                     set signed_file_original = ?, signed_file_stored = ?, signed_file_size = ?,
                         signed_file_mime = ?, signed_file_uploaded_at = ?
                     where id = ?'
                )->execute([
                    $signedPdf['original'],
                    $signedPdf['stored'],
                    $signedPdf['size'],
                    $signedPdf['mime'],
                    $signedAt,
                    (int) $contract['id'],
                ]);
            }
            redirect('/contract-public.php?t=' . $token . '&manual=1');
        }
    }

    $stmt->execute([$token]);
    $contract = $stmt->fetch();
}

if (!empty($_GET['accepted'])) {
    $message = 'Contrato aceito com sucesso.';
}
if (!empty($_GET['govbr'])) {
    $message = 'PDF assinado recebido com sucesso.';
}
if (!empty($_GET['manual'])) {
    $message = 'Contrato assinado com sucesso.';
}

$primaryColor = $contract['primary_color'] ?: '#15201d';
$isCompleted = in_array($contract['status'], ['ACCEPTED', 'SIGNED_GOVBR', 'SIGNED_MANUAL'], true);
$sourceFileUrl = !empty($contract['source_file_stored']) ? contract_template_file_url($contract) : '';
$signedFileUrl = !empty($contract['signed_file_stored']) ? contract_signed_file_url($contract) : '';
$displayFileUrl = $signedFileUrl !== '' ? $signedFileUrl : $sourceFileUrl;
$displayFileName = $signedFileUrl !== '' ? ($contract['signed_file_original'] ?? 'PDF assinado') : ($contract['source_file_original'] ?? 'Contrato anexado');
$displayMime = $signedFileUrl !== '' ? ($contract['signed_file_mime'] ?? '') : ($contract['source_file_mime'] ?? '');
$isDisplayPdf = $displayFileUrl !== '' && (
    $displayMime === 'application/pdf'
    || strtolower(pathinfo($displayFileName, PATHINFO_EXTENSION)) === 'pdf'
);
$pageTitle = 'Assinatura de contrato';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { fog: '#f6f4ef', ink: '#15201d', line: '#e6e1d8' } } } };
    </script>
</head>
<body class="min-h-screen bg-fog text-ink">
    <main class="mx-auto grid max-w-5xl gap-5 p-4 md:p-8">
        <header class="rounded-lg border border-line bg-white p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-slate-500"><?= e($contract['company_name']) ?></p>
                    <h1 class="text-2xl font-bold"><?= e($contract['title']) ?></h1>
                    <p class="mt-1 text-sm text-slate-500"><?= e($contract['client_name']) ?> · <?= e($contract['project_name']) ?></p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold text-white" style="background:<?= e($primaryColor) ?>"><?= e(contract_status_label($contract['status'])) ?></span>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-col gap-3 border-b border-line p-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="font-bold">Contrato para assinatura</h2>
                    <p class="mt-1 text-sm text-slate-500"><?= $displayFileUrl ? e($displayFileName) : 'Contrato gerado pelo Arquidesk' ?></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if ($sourceFileUrl): ?>
                        <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="<?= e($sourceFileUrl) ?>" target="_blank" rel="noopener">Abrir original</a>
                    <?php endif; ?>
                    <?php if ($isCompleted): ?>
                        <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="<?= e($signedFileUrl ?: '/contract-print.php?t=' . $token) ?>" target="_blank" rel="noopener">Ver contrato assinado</a>
                    <?php elseif (!$sourceFileUrl): ?>
                        <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="/contract-print.php?t=<?= e($token) ?>" target="_blank" rel="noopener">Abrir contrato</a>
                    <?php endif; ?>
                    <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-white px-4 text-sm font-semibold hover:bg-fog" href="https://assinador.iti.br" target="_blank" rel="noopener">Assinar com gov.br</a>
                </div>
            </div>
            <?php if ($isDisplayPdf): ?>
                <iframe class="h-[72vh] w-full bg-white" src="<?= e($displayFileUrl) ?>#toolbar=1" title="Contrato para assinatura"></iframe>
            <?php elseif ($displayFileUrl): ?>
                <div class="p-6 text-sm text-slate-600">
                    Este contrato foi anexado como Word. Abra o arquivo original para visualizar ou baixar.
                </div>
            <?php else: ?>
                <article class="prose max-w-none whitespace-pre-wrap bg-fog p-5 text-sm leading-relaxed"><?= e($contract['body']) ?></article>
            <?php endif; ?>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">

            <div class="rounded-lg border border-line bg-white p-5">
                <h2 class="font-bold">Assinar desenhando</h2>
                <p class="mt-1 text-sm text-slate-500">Desenhe sua assinatura na tela. Ela vai aparecer visualmente no PDF do contrato.</p>
                <?php if ($contract['status'] === 'SIGNED_MANUAL'): ?>
                    <div class="mt-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                        Assinado por <?= e($contract['accepted_name']) ?> em <?= e(date('d/m/Y H:i', strtotime($contract['accepted_at']))) ?>.
                    </div>
                <?php elseif ($isCompleted): ?>
                    <div class="mt-4 rounded-md border border-line bg-fog p-3 text-sm text-slate-600">Contrato ja finalizado por outra forma de assinatura.</div>
                <?php else: ?>
                    <form method="post" class="mt-4 grid gap-3" id="draw-signature-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="draw_signature">
                        <input type="hidden" name="signature_data" id="signature-data">
                        <label class="grid gap-1 text-sm font-semibold">Nome completo
                            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="accepted_name" required>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">CPF/CNPJ
                            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" name="accepted_document" required>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold">E-mail
                            <input class="min-h-10 rounded-md border border-line px-3 outline-none focus:border-ink" type="email" name="accepted_email">
                        </label>
                        <div>
                            <label class="mb-1 block text-sm font-semibold">Assinatura</label>
                            <canvas id="signature-pad" class="h-36 w-full touch-none rounded-md border border-line bg-white"></canvas>
                            <button type="button" id="clear-signature" class="mt-2 rounded-md border border-line px-3 py-2 text-xs font-semibold hover:bg-fog">Limpar assinatura</button>
                        </div>
                        <label class="flex gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="agree" value="1" required>
                            Li e concordo com o contrato apresentado nesta pagina.
                        </label>
                        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Assinar contrato</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="rounded-lg border border-line bg-white p-5">
                <h2 class="font-bold">Assinar com gov.br</h2>
                <p class="mt-1 text-sm text-slate-500">Abra o PDF, assine no portal oficial do gov.br e envie o arquivo assinado aqui.</p>
                <ol class="mt-4 grid gap-2 text-sm text-slate-600">
                    <li>1. Baixe o contrato original da empresa ou abra o contrato e salve em PDF.</li>
                    <li>2. Acesse o Assinador gov.br.</li>
                    <li>3. Envie aqui o PDF assinado.</li>
                </ol>
                <?php if ($contract['status'] === 'SIGNED_GOVBR'): ?>
                    <div class="mt-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                        PDF assinado recebido em <?= e(date('d/m/Y H:i', strtotime($contract['signed_file_uploaded_at']))) ?>.
                    </div>
                <?php elseif ($isCompleted): ?>
                    <div class="mt-4 rounded-md border border-line bg-fog p-3 text-sm text-slate-600">Contrato ja finalizado por outra forma de assinatura.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="upload_govbr">
                        <label class="grid gap-1 text-sm font-semibold">PDF assinado pelo gov.br
                            <input class="min-h-10 rounded-md border border-line bg-white px-3 py-2 outline-none focus:border-ink" type="file" name="signed_file" accept="application/pdf,.pdf" required>
                        </label>
                        <button class="min-h-10 rounded-md bg-ink px-4 text-sm font-bold text-white" type="submit">Enviar PDF assinado</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script>
    (function () {
        const canvas = document.getElementById('signature-pad');
        const form = document.getElementById('draw-signature-form');
        if (!canvas || !form) return;

        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasInk = false;

        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            const image = hasInk ? canvas.toDataURL('image/png') : null;
            canvas.width = Math.max(1, Math.floor(rect.width * window.devicePixelRatio));
            canvas.height = Math.max(1, Math.floor(rect.height * window.devicePixelRatio));
            ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#15201d';
            if (image) {
                const img = new Image();
                img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
                img.src = image;
            }
        }

        function point(e) {
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches && e.touches[0] ? e.touches[0] : e;
            return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
        }

        function start(e) {
            e.preventDefault();
            drawing = true;
            const p = point(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }

        function move(e) {
            if (!drawing) return;
            e.preventDefault();
            const p = point(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            hasInk = true;
        }

        function end(e) {
            if (!drawing) return;
            e.preventDefault();
            drawing = false;
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end, { passive: false });

        document.getElementById('clear-signature')?.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasInk = false;
        });

        form.addEventListener('submit', function (e) {
            if (!hasInk) {
                e.preventDefault();
                alert('Desenhe a assinatura antes de enviar.');
                return;
            }
            document.getElementById('signature-data').value = canvas.toDataURL('image/png');
        });
    })();
    </script>
</body>
</html>
