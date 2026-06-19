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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Context;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Coinsnap\Shopware\Order\OrderService;

class CoinsnapWebhookService implements WebhookServiceInterface
{
    public const REQUIRED_HEADER = 'x-coinsnap-sig';
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
     * Registers a webhook for the API.
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

            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId') . '/webhooks';
            $body = $this->client->sendPostRequest(
              $uri,
              [
                'url' => $webhookUrl
              ]
            );
            if (empty($body)) {
                throw new \Exception("Webhook couldn't be created");
            }

            $this->configurationService->setSetting('coinsnapWebhookSecret', $body['secret']);
            $this->configurationService->setSetting('coinsnapWebhookId', $body['id']);

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function isEnabled(): bool
    {
        try {

            if (empty($this->configurationService->getSetting('coinsnapWebhookId'))) {
                return false;
            }
            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId') . '/webhooks/' . $this->configurationService->getSetting('coinsnapWebhookId');
            $response = $this->client->sendGetRequest($uri);
            if (empty($response) || $response['enabled'] === false) {
                throw new \Exception("Webhook with ID:" . $this->configurationService->getSetting('coinsnapWebhookId') .
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
            $this->logger->error('Missing signature header');
            return new Response(
              json_encode(['error' => 'Missing signature header']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }
        // Verify the HMAC against the exact raw bytes Coinsnap signed, then
        // decode. Re-encoding a parsed array would not reproduce the signed
        // payload (key order, spacing, escaping all differ).
        $rawBody = $request->getContent();
        $body = json_decode($rawBody, true);

        if (empty($body) || !is_array($body)) {
            $this->logger->error('Missing webhook data');
            return new Response(
              json_encode(['error' => 'Missing webhook data']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }

        if (empty($this->configurationService->getSetting('coinsnapWebhookSecret'))) {
            $this->logger->error('Missing webhook secret');
            return new Response(
              json_encode(['error' => 'Missing webhook secret']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }

        $expectedHeader = 'sha256=' . hash_hmac('sha256', $rawBody, $this->configurationService->getSetting('coinsnapWebhookSecret'));

        if (!hash_equals($expectedHeader, $signature)) {
            $this->logger->error('Invalid signature');
            return new Response(
              json_encode(['error' => 'Invalid signature']),
              Response::HTTP_UNAUTHORIZED,
              ['Content-Type' => 'application/json']
            );
        }
        if (empty($body['invoiceId']) || empty($body['type'])) {
            $this->logger->error('Missing invoiceId or type in webhook payload');
            return new Response(
              json_encode(['error' => 'Missing invoiceId or type']),
              Response::HTTP_BAD_REQUEST,
              ['Content-Type' => 'application/json']
            );
        }

        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId') . '/invoices/' . $body['invoiceId'];
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

        switch ($body['type']) {
            case 'Processing': // The invoice is paid in full.
                $this->transactionStateHandler->process($transactionId, $context);
                $this->orderRepository->upsert(
                  [
                    [
                      'id' => $orderId,
                      'customFields' => [
                        'coinsnapInvoiceId' => $body['invoiceId'],
                        'coinsnapOrderStatus' => 'processing',
                      ],
                    ],
                  ],
                  $context
                );
                $this->logger->info('Invoice settled, waiting for payment to settle.');
                break;
            case 'Expired':
                $underpaid = !empty($body['underpaid']);
                $status = $underpaid ? 'partially_paid' : 'expired';
                $this->orderRepository->upsert(
                  [
                    [
                      'id' => $orderId,
                      'customFields' => [
                        'coinsnapInvoiceId' => $body['invoiceId'],
                        'coinsnapOrderStatus' => $status,
                      ],
                    ],
                  ],
                  $context
                );
                if ($underpaid) {
                    $this->transactionStateHandler->payPartially($transactionId, $context);
                }
                $this->logger->info('Invoice expired.');
                break;
            case 'Settled':
                $this->orderRepository->upsert(
                  [
                    [
                      'id' => $orderId,
                      'customFields' => [
                        'coinsnapInvoiceId' => $body['invoiceId'],
                        'coinsnapOrderStatus' => 'settled',
                      ],
                    ],
                  ],
                  $context
                );
                $this->transactionStateHandler->paid($transactionId, $context);
                $this->logger->info('Invoice payment settled.');
                break;
            default:
                $this->logger->info('Unhandled webhook event type: ' . $body['type']);
                break;
        }
        return new Response('success', Response::HTTP_OK);
    }
}
