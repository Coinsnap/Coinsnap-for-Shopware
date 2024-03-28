<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Coinsnap
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CoinsnapWebhookService implements WebhookServiceInterface
{
    public const REQUIRED_HEADER = 'x-coinsnap-sig';
    private ClientInterface $client;
    private ConfigurationService $configurationService;
    private OrderTransactionStateHandler $transactionStateHandler;
    private $orderService;
    private EntityRepository $orderRepository;
    private LoggerInterface $logger;

    public function __construct(ClientInterface $client, ConfigurationService $configurationService, OrderTransactionStateHandler $transactionStateHandler, $orderService, EntityRepository $orderRepository, LoggerInterface $logger)
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

            $webhookUrl =  $request->server->get('APP_URL') . '/api/_action/coinsnap/webhook-endpoint';

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
        $body = $request->request->all();

        $expectedHeader = 'sha256=' . hash_hmac('sha256', $request->getContent(), $this->configurationService->getSetting('coinsnapWebhookSecret'));

        if ($signature !== $expectedHeader) {
            $this->logger->error('Invalid signature');
            return new Response();
        }
        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId') . '/invoices/' . $body['invoiceId'];
        $responseBody = $this->client->sendGetRequest($uri);
        // $criteria = new Criteria();
        // $criteria->addFilter(new EqualsFilter('orderNumber', $responseBody['metadata']['orderNumber']));
        // $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();


        $orderId = $this->orderService->getId($responseBody['metadata']['orderNumber'], $context);


        switch ($body['type']) {
            case 'Processing': // The invoice is paid in full.
                $this->transactionStateHandler->process($responseBody['metadata']['transactionId'], $context);
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
                //TODO: Check if invoice was partially paid
                $status = $body['underpaid'] ? 'partially_paid' : 'expired';
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
                //TODO: Check if paid partially
                if ($body['underpaid']) {
                    $this->transactionStateHandler->payPartially($responseBody['metadata']['transactionId'], $context);
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
                $this->transactionStateHandler->paid($responseBody['metadata']['transactionId'], $context);
                $this->logger->info('Invoice payment settled.');
                break;
        }
        return new Response('success', Response::HTTP_OK);
    }
}
