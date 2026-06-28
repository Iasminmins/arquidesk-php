<?php

/**
 * Endpoint receptor de Webhooks do Asaas.
 *
 * URL pública (cadastrar no painel Asaas):
 *   https://arquidesk.com.br/asaas-webhook.php
 *
 * Segurança: o Asaas envia o token configurado no painel no header
 * 'asaas-access-token'. Comparamos com asaas_webhook_token() do config.
 *
 * Eventos tratados (assinaturas):
 *   PAYMENT_CONFIRMED / PAYMENT_RECEIVED  -> assinatura ACTIVE
 *   PAYMENT_OVERDUE                       -> assinatura PAST_DUE
 *   PAYMENT_DELETED / PAYMENT_REFUNDED    -> assinatura CANCELED
 *
 * Sempre responde HTTP 200 rapidamente quando o evento é aceito, para o
 * Asaas não reenfileirar. Erros de validação retornam 401/400.
 */

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/asaas.php';

header('Content-Type: application/json; charset=utf-8');

// 1) Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 2) Valida o token de autenticação enviado pelo Asaas
$expectedToken = asaas_webhook_token();
$receivedToken = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';

if ($expectedToken === '') {
    error_log('[AsaasWebhook] webhook_token não configurado no config.');
    http_response_code(500);
    echo json_encode(['error' => 'Webhook não configurado']);
    exit;
}
if (!hash_equals($expectedToken, $receivedToken)) {
    error_log('[AsaasWebhook] Token inválido. Recebido: ' . substr($receivedToken, 0, 6) . '...');
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// 3) Lê e decodifica o corpo do evento
$raw = file_get_contents('php://input');
$event = json_decode($raw ?: '{}', true);
if (!is_array($event) || empty($event['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

$eventType = (string) $event['event'];
$payment = $event['payment'] ?? [];
$customerId = (string) ($payment['customer'] ?? '');
$subscriptionId = (string) ($payment['subscription'] ?? '');

// 4) Localiza a assinatura pela referência externa do Asaas
$sub = null;
if ($subscriptionId !== '') {
    $stmt = db()->prepare('select * from subscriptions where external_subscription_id = ? limit 1');
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch();
}
if (!$sub && $customerId !== '') {
    $stmt = db()->prepare('select * from subscriptions where external_customer_id = ? limit 1');
    $stmt->execute([$customerId]);
    $sub = $stmt->fetch();
}

if (!$sub) {
    // Não achou assinatura correspondente. Responde 200 mesmo assim para o
    // Asaas não reenviar indefinidamente, mas registra para auditoria.
    error_log("[AsaasWebhook] {$eventType}: assinatura não encontrada (customer={$customerId}, subscription={$subscriptionId})");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'subscription not found']);
    exit;
}

// 5) Decide o novo status conforme o evento
$newStatus = null;
$extendPeriod = false;
switch ($eventType) {
    case 'PAYMENT_CONFIRMED':
    case 'PAYMENT_RECEIVED':
        $newStatus = 'ACTIVE';
        $extendPeriod = true;
        break;
    case 'PAYMENT_OVERDUE':
        $newStatus = 'PAST_DUE';
        break;
    case 'PAYMENT_DELETED':
    case 'PAYMENT_REFUNDED':
        $newStatus = 'CANCELED';
        break;
    default:
        // Evento que não nos interessa: aceita sem alterar nada.
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'event' => $eventType]);
        exit;
}

// 6) Atualiza a assinatura
try {
    if ($extendPeriod) {
        // Pagamento confirmado: ativa e estende o período por 30 dias a partir
        // de hoje (ou da data de vencimento atual, o que for maior).
        $today = new DateTimeImmutable('today');
        $currentEnd = !empty($sub['current_period_end'])
            ? new DateTimeImmutable($sub['current_period_end'])
            : $today;
        $base = $currentEnd > $today ? $currentEnd : $today;
        $newEnd = $base->modify('+30 days')->format('Y-m-d');
        $periodStart = $today->format('Y-m-d');

        $upd = db()->prepare(
            'update subscriptions
                set status = ?, current_period_start = ?, current_period_end = ?, canceled_at = null
              where id = ?'
        );
        $upd->execute([$newStatus, $periodStart, $newEnd, (int) $sub['id']]);
    } elseif ($newStatus === 'CANCELED') {
        $upd = db()->prepare(
            'update subscriptions set status = ?, canceled_at = now() where id = ?'
        );
        $upd->execute([$newStatus, (int) $sub['id']]);
    } else {
        $upd = db()->prepare('update subscriptions set status = ? where id = ?');
        $upd->execute([$newStatus, (int) $sub['id']]);
    }

    error_log("[AsaasWebhook] {$eventType}: assinatura #{$sub['id']} -> {$newStatus}");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'event' => $eventType, 'new_status' => $newStatus]);
} catch (Throwable $e) {
    error_log('[AsaasWebhook] Erro ao atualizar: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
}
