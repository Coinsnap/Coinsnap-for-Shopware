<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Webhook;

use Coinsnap\Shopware\Client\ClientInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Context;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\StateMachineException;
use Coinsnap\Shopware\Order\OrderService;

class BTCPayWebhookService implements WebhookServiceInterface
{
    public const REQUIRED_HEADER = 'btcpay-sig';
    private ClientInterface $client;
    private ConfigurationService $configurationService;
    private OrderTransactionStateHandler $transactionStateHandler;
    private OrderService $orderService;
    private EntityRepository $orderRepository;
    private LoggerInterface $logger;

    public function __construct(ClientInterface $client, ConfigurationService $configurationService, OrderTransactionStateHandler $transactionStateHandler, OrderService $orderService, EntityRepository $orderRepository, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->configurationService = $configurationService;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Registers a webhook on the merchant's BTCPay server.
     *
     * @param Request $request The HTTP request.
     * @param string|null $salesChannelId The ID of the sales channel (optional).
     * @return bool Returns true if the webhook was successfully registered, false otherwise.
     */
    public function register(Request $request, ?string $salesChannelId): bool
    {
        try {
            if ($this->isEnabled()) {
                $this->logger->info('Webhook exists');
                return true;
            }

            $webhookUrl = $request->server->get('APP_URL') . '/api/_action/coinsnap/webhook-endpoint';

            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/webhooks';
            $body = $this->client->sendPostRequest(
              $uri,
              [
                'url' => $webhookUrl
              ]
            );
            if (empty($body)) {
                throw new \Exception("Webhook couldn't be created");
            }

            $this->configurationService->setSetting('btcpayWebhookSecret', $body['secret']);
            $this->configurationService->setSetting('btcpayWebhookId', $body['id']);

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function isEnabled(): bool
    {
        try {
            if (empty($this->configurationService->getSetting('btcpayWebhookId'))) {
                return false;
            }
            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/webhooks/' . $this->configurationService->getSetting('btcpayWebhookId');
            $response = $this->client->sendGetRequest($uri);
            if (empty($response) || $response['enabled'] === false) {
                throw new \Exception("Webhook with ID:" . $this->configurationService->getSetting('btcpayWebhookId') .
                  (empty($response) ? " doesn't exist." : " isn't enabled."));
            }
            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function process(Request $request, Context $context): Response
    {
        $signature = $request->headers->get(self::REQUIRED_HEADER);

        if (empty($signature)) {
            $this->logger->warning('Missing signature header');
            return new Response(
              json_encode(['error' => 'Missing signature header']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }
        // Verify the HMAC against the exact raw bytes BTCPay signed, then
        // decode. Re-encoding a parsed array would not reproduce the signed
        // payload (key order, spacing, escaping all differ).
        $rawBody = $request->getContent();
        $body = json_decode($rawBody, true);

        if (empty($body) || !is_array($body)) {
            $this->logger->warning('Missing webhook data');
            return new Response(
              json_encode(['error' => 'Missing webhook data']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }

        if (empty($this->configurationService->getSetting('btcpayWebhookSecret'))) {
            $this->logger->error('Missing webhook secret');
            return new Response(
              json_encode(['error' => 'Missing webhook secret']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }

        $expectedHeader = 'sha256=' . hash_hmac('sha256', $rawBody, $this->configurationService->getSetting('btcpayWebhookSecret'));

        if (!hash_equals($expectedHeader, $signature)) {
            $this->logger->warning('Invalid signature');
            return new Response(
              json_encode(['error' => 'Invalid signature']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }
        if (empty($body['invoiceId']) || empty($body['type'])) {
            $this->logger->warning('Missing invoiceId or type in webhook payload');
            return new Response(
              json_encode(['error' => 'Missing invoiceId or type']),
              Response::HTTP_BAD_REQUEST,
              ['Content-Type' => 'application/json']
            );
        }

        try {
            // BTCPay webhook payloads don't carry the order metadata, so fetch
            // the invoice to recover the orderNumber/transactionId this plugin
            // stored on it at checkout.
            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/invoices/' . rawurlencode($body['invoiceId']);
            $responseBody = $this->client->sendGetRequest($uri);

            $orderNumber = $responseBody['metadata']['orderNumber'] ?? null;
            $transactionId = $responseBody['metadata']['transactionId'] ?? null;

            if ($orderNumber === null || $transactionId === null) {
                $this->logger->error('Missing order metadata for invoice ' . $body['invoiceId']);
                return new Response(
                  json_encode(['error' => 'Missing order metadata']),
                  Response::HTTP_UNPROCESSABLE_ENTITY,
                  ['Content-Type' => 'application/json']
                );
            }

            $orderId = $this->orderService->getId($orderNumber, $context);

            if ($orderId === null) {
                $this->logger->error('No order found for order number ' . $orderNumber);
                return new Response(
                  json_encode(['error' => 'Order not found']),
                  Response::HTTP_NOT_FOUND,
                  ['Content-Type' => 'application/json']
                );
            }

            // Terminal merchant/bank states are sticky: a replayed or late
            // webhook must not resurrect a refunded or cancelled payment.
            if ($this->isTransactionLocked($orderId, $transactionId, $context)) {
                $this->logger->info('Ignoring webhook: transaction already in a terminal state for order ' . $orderNumber);
                return new Response('ignored', Response::HTTP_OK);
            }

            switch ($body['type']) {
                case 'InvoiceReceivedPayment':
                    // A payment landed but isn't settled yet. Only act if it
                    // arrived after the invoice had already expired (a late
                    // partial payment); otherwise just wait for settlement.
                    if (!empty($body['afterExpiration'])) {
                        $this->applyTransition(fn() => $this->transactionStateHandler->payPartially($transactionId, $context));
                        $this->upsertOrderStatus($orderId, $body['invoiceId'], 'partially_paid', $context);
                        $this->logger->info('Partial payment received after expiration.');
                    } else {
                        $this->logger->info('Payment received, waiting for settlement.');
                    }
                    break;
                case 'InvoiceProcessing': // The invoice is paid in full.
                    $this->applyTransition(fn() => $this->transactionStateHandler->process($transactionId, $context));
                    $this->upsertOrderStatus($orderId, $body['invoiceId'], 'processing', $context);
                    $this->logger->info('Invoice payment received fully, waiting for settlement.');
                    break;
                case 'InvoicePaymentSettled':
                    // Fires per settled payment. Use it to recover a payment
                    // that settled after the invoice expired: if BTCPay now
                    // reports the invoice itself as settled, mark it paid.
                    if (($responseBody['status'] ?? null) === 'Settled') {
                        $this->applyTransition(fn() => $this->transactionStateHandler->paid($transactionId, $context));
                        $this->upsertOrderStatus($orderId, $body['invoiceId'], 'settled', $context);
                        $this->logger->info('Invoice payment settled.');
                    } else {
                        $this->upsertOrderStatus($orderId, $body['invoiceId'], 'partially_paid', $context);
                        $this->logger->info('Partial payment settled; invoice not fully settled yet.');
                    }
                    break;
                case 'InvoiceExpired':
                    $partiallyPaid = !empty($body['partiallyPaid']);
                    $status = $partiallyPaid ? 'partially_paid' : 'expired';
                    $this->upsertOrderStatus($orderId, $body['invoiceId'], $status, $context);
                    if ($partiallyPaid) {
                        $this->applyTransition(fn() => $this->transactionStateHandler->payPartially($transactionId, $context));
                    } else {
                        $this->applyTransition(fn() => $this->transactionStateHandler->fail($transactionId, $context));
                    }
                    $this->logger->info('Invoice expired.');
                    break;
                case 'InvoiceInvalid':
                    $this->applyTransition(fn() => $this->transactionStateHandler->fail($transactionId, $context));
                    $this->upsertOrderStatus($orderId, $body['invoiceId'], 'invalid', $context);
                    $this->logger->info('Invoice became invalid.');
                    break;
                case 'InvoiceSettled':
                    $this->upsertOrderStatus($orderId, $body['invoiceId'], 'settled', $context);
                    $this->applyTransition(fn() => $this->transactionStateHandler->paid($transactionId, $context));
                    $this->logger->info('Invoice payment settled.');
                    break;
                default:
                    $this->logger->info('Unhandled webhook event type: ' . $body['type']);
                    break;
            }
            return new Response('success', Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Webhook processing failed: ' . $e->getMessage());
            return new Response(
              json_encode(['error' => 'Webhook processing failed']),
              Response::HTTP_INTERNAL_SERVER_ERROR,
              ['Content-Type' => 'application/json']
            );
        }
    }

    /**
     * Runs a transaction state transition, swallowing state-machine rejections.
     *
     * BTCPay redelivers webhooks and can deliver them out of order. When the
     * state machine rejects a transition (illegal from the current state, or
     * unnecessary because it was already applied) the transaction is already in
     * a more advanced state, so this is treated as an idempotent no-op rather
     * than failing the webhook and triggering an endless retry loop.
     */
    private function applyTransition(callable $transition): void
    {
        try {
            $transition();
        } catch (StateMachineException $e) {
            $this->logger->info('Skipping payment state transition: ' . $e->getMessage());
        }
    }

    /**
     * Whether the transaction is in a terminal state set by a merchant or bank
     * action (refund, cancellation, chargeback) that a payment webhook must
     * never override.
     */
    private function isTransactionLocked(string $orderId, string $transactionId, Context $context): bool
    {
        $state = $this->orderService->getTransactionState($orderId, $transactionId, $context);

        return in_array($state, [
            OrderTransactionStates::STATE_REFUNDED,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            OrderTransactionStates::STATE_CANCELLED,
            OrderTransactionStates::STATE_CHARGEBACK,
        ], true);
    }

    private function upsertOrderStatus(string $orderId, string $invoiceId, string $status, Context $context): void
    {
        $this->orderRepository->upsert(
          [
            [
              'id' => $orderId,
              'customFields' => [
                'coinsnapInvoiceId' => $invoiceId,
                'coinsnapOrderStatus' => $status,
              ],
            ],
          ],
          $context
        );
    }
}
