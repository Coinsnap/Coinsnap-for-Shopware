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

class BTCPayWebhookService implements WebhookServiceInterface
{
    public const REQUIRED_HEADER = 'btcpay-sig';
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

    public function register(Request $request, ?string $salesChannelId): bool
    {
        try {
            if ($this->isEnabled()) {
                $this->logger->info('Webhook exists');
                return true;
            }

            $webhookUrl =  $request->server->get('APP_URL') . '/api/_action/coinsnap/webhook-endpoint';

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
        $body = $request->request->all();

        $expectedHeader = 'sha256=' . hash_hmac('sha256', $request->getContent(), $this->configurationService->getSetting('btcpayWebhookSecret'));
        if ($signature !== $expectedHeader) {
            $this->logger->error('Invalid signature');
            return new Response();
        }
        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/invoices/' . $body['invoiceId'];
        $responseBody = $body['type'] == 'InvoicePaymentSettled' ? $this->client->sendGetRequest($uri) : '';
        // $criteria = new Criteria();
        // $criteria->addFilter(new EqualsFilter('orderNumber', $body['metadata']['orderNumber']));
        // $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();

        $orderId = $this->orderService->getId($responseBody['metadata']['orderNumber'], $context);


        switch ($body['type']) {
            case 'InvoiceReceivedPayment':
                if ($body['afterExpiration']) {
                    $this->transactionStateHandler->payPartially($body['metadata']['transactionId'], $context);
                    $this->logger->info('Invoice (partial) payment incoming (unconfirmed) after invoice was already expired.');
                    $this->orderRepository->upsert(
                        [
                            [
                                'id' => $orderId,
                                'customFields' => [
                                    'invoiceId' => $body['invoiceId'],
                                    'btcpayOrderStatus' => 'paidPartially',
                                    'paidAfterExpiration' => true,
                                    'overpaid'      => false
                                ],
                            ],
                        ],
                        $context
                    );
                } else {
                    $this->logger->info('Invoice (partial) payment incoming (unconfirmed). Waiting for settlement.');
                }

                break;
            case 'InvoicePaymentSettled':
                // We can't use $body->afterExpiration here as there is a bug affecting all version prior to
                // BTCPay Server v1.7.0.0, see https://github.com/btcpayserver/btcpayserver/issues/
                // Therefore we check if the invoice is in expired or expired paid partial status, instead.
                if (
                    $responseBody['status'] == 'Expired'
                    || ($responseBody['status'] == 'Expired' && $responseBody['additionalStatus'] == 'PaidPartial')
                ) {
                    // Check if also the invoice is now fully paid.
                    if ($this->orderService->invoiceIsFullyPaid($body['invoiceId'])) {
                        $this->orderRepository->upsert(
                            [
                                [
                                    'id' => $orderId,
                                    'customFields' => [
                                        'invoiceId' => $body['invoiceId'],
                                        'btcpayOrderStatus' => 'settled',
                                        'paidAfterExpiration' => true,
                                        'overpaid'      =>  false
                                    ],
                                ],
                            ],
                            $context
                        );
                        $this->logger->info('Invoice fully settled after invoice was already expired. Needs manual checking.');
                    } else {
                        $this->orderRepository->upsert(
                            [
                                [
                                    'id' => $orderId,
                                    'customFields' => [
                                        'invoiceId' => $body['invoiceId'],
                                        'btcpayOrderStatus' => 'paidPartially',
                                        'paidAfterExpiration' => true,
                                        'overpaid'      => false
                                    ],
                                ],
                            ],
                            $context
                        );
                        $this->transactionStateHandler->payPartially($body['metadata']['transactionId'], $context);
                        $this->logger->debug('Invoice with orderId:' . $body['metadata']['orderId'] . ' NOT fully paid.');
                        $this->logger->info('(Partial) payment settled but invoice not settled yet (could be more transactions incoming). Needs manual checking.');
                    }
                } else {
                    // No need to change order status here, only leave a note.
                    $this->logger->info('Invoice (partial) payment settled.');
                }

                break;
            case 'InvoiceProcessing': // The invoice is paid in full.
                $this->transactionStateHandler->process($body['metadata']['transactionId'], $context);
                if ($body['overPaid']) {

                    $this->logger->info('Invoice payment received fully with overpayment, waiting for settlement.');
                } else {
                    $this->logger->info('Invoice payment received fully, waiting for settlement.');
                }
                break;
            case 'InvoiceInvalid':
                $this->transactionStateHandler->fail($body['metadata']['transactionId'], $context);
                if ($body['manuallyMarked']) {
                    $this->orderRepository->upsert(
                        [
                            [
                                'id' => $orderId,
                                'customFields' => [
                                    'invoiceId' => $body['invoiceId'],
                                    'btcpayOrderStatus' => 'manuallyMarked',
                                    'overpaid'  => false,
                                    'paidAfterExpiration' => false
                                ],
                            ],
                        ],
                        $context
                    );
                    $this->logger->info('Invoice manually marked invalid.');
                } else {
                    $this->logger->info('Invoice became invalid.');
                }
                break;
            case 'InvoiceExpired':
                if ($body['partiallyPaid']) {

                    $this->orderRepository->upsert(
                        [
                            [
                                'id' => $orderId,
                                'customFields' => [
                                    'invoiceId' => $body['invoiceId'],
                                    'btcpayOrderStatus' => 'invoiceExpired',
                                    'paidAfterExpiration' => true,
                                    'overpaid'  => false
                                ],
                            ],
                        ],
                        $context
                    );
                    $this->transactionStateHandler->payPartially($body['metadata']['transactionId'], $context);
                    $this->logger->info('Invoice expired but was paid partially, please check.');
                } else {
                    $this->orderRepository->upsert(
                        [
                            [
                                'id' => $orderId,
                                'customFields' => [
                                    'invoiceId' => $body['invoiceId'],
                                    'btcpayOrderStatus' => 'invoiceExpired',
                                    'paidAfterExpiration' => false,
                                    'overpaid'  => false
                                ],
                            ],
                        ],
                        $context
                    );
                    $this->transactionStateHandler->fail($body['metadata']['transactionId'], $context);
                    $this->logger->info('Invoice expired.');
                }
                break;
            case 'InvoiceSettled':
                //Webhook doesn't send overPaid
                if (isset($body['overPaid']) && $body['overPaid']) {

                    $this->orderRepository->upsert(
                        [
                            [
                                'id' => $orderId,
                                'customFields' => [
                                    'invoiceId' => $body['invoiceId'],
                                    'btcpayOrderStatus' => 'paid',
                                    'paidAfterExpiration' => false,
                                    'overpaid' => true
                                ],
                            ],
                        ],
                        $context
                    );
                    $this->transactionStateHandler->paid($body['metadata']['transactionId'], $context);
                    $this->logger->info('Invoice payment settled but was overpaid.');
                } else {
                    $this->orderRepository->upsert(
                        [
                            [
                                'id' => $orderId,
                                'customFields' => [
                                    'invoiceId' => $body['invoiceId'],
                                    'btcpayOrderStatus' => 'paid',
                                    'paidAfterExpiration' => false,
                                    'overpaid' => false
                                ],
                            ],
                        ],
                        $context
                    );
                    $this->logger->info('Invoice payment settled.');
                    $this->transactionStateHandler->paid($body['metadata']['transactionId'], $context);
                }
                break;
        }
        return new Response('success', Response::HTTP_OK);
    }
}
