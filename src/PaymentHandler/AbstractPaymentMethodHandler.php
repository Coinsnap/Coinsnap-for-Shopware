<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\PaymentHandler;

use Coinsnap\Shopware\Client\ClientInterface;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Coinsnap\Shopware\Order\InvoiceData;
use Coinsnap\Shopware\Order\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractPaymentMethodHandler extends AbstractPaymentHandler
{
    protected ClientInterface $client;
    protected ConfigurationService $configurationService;
    protected OrderTransactionStateHandler $transactionStateHandler;
    protected EntityRepository $orderTransactionRepository;
    protected OrderService $orderService;
    protected LoggerInterface $logger;

    public function __construct(
        ClientInterface $client,
        ConfigurationService $configurationService,
        OrderTransactionStateHandler $transactionStateHandler,
        EntityRepository $orderTransactionRepository,
        OrderService $orderService,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->configurationService = $configurationService;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    /**
     * This handler only supports the synchronous redirect flow. Refunds and
     * recurring payments are handled out of band by Coinsnap, so neither
     * capability is advertised here.
     */
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        try {
            $redirectUrl = $this->sendReturnUrlToCheckout($transaction, $context);
        } catch (\Throwable $e) {
            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        return $redirectUrl !== null ? new RedirectResponse($redirectUrl) : null;
    }

    // Webhook handles this part
    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
    }

    /**
     * Loads the order transaction together with the order associations the
     * gateway call needs (amount, currency, order number, buyer, items).
     */
    protected function loadOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociations(InvoiceData::ASSOCIATIONS);

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw PaymentException::invalidTransaction($orderTransactionId);
        }

        return $orderTransaction;
    }

    /**
     * Persists Shopware's (long, tokenized) return URL on the transaction and
     * hands the gateway a short proxy URL instead. Coinsnap caps redirectUrl at
     * 255 chars, which the finalize URL with its payment-token JWT exceeds.
     */
    protected function buildGatewayRedirectUrl(OrderTransactionEntity $orderTransaction, string $returnUrl, Context $context): string
    {
        $parts = parse_url($returnUrl);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return $returnUrl;
        }

        $this->orderTransactionRepository->update([[
            'id' => $orderTransaction->getId(),
            'customFields' => ['coinsnapReturnUrl' => $returnUrl],
        ]], $context);

        $base = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $base . '/coinsnap/payment-return/' . $orderTransaction->getId();
    }

    /**
     * orderId, buyerEmail and metadata for the create-invoice request.
     *
     * @return array{orderId: string, buyerEmail?: string, metadata: array<string, mixed>}
     */
    protected function invoiceData(OrderTransactionEntity $orderTransaction): array
    {
        $order = $orderTransaction->getOrder();
        if ($order === null) {
            throw PaymentException::invalidTransaction($orderTransaction->getId());
        }

        return InvoiceData::fromOrder($order, $orderTransaction, self::adminUrl(), self::pluginVersion());
    }

    // Same base as Shopware's own admin links: APP_URL plus SHOPWARE_ADMINISTRATION_PATH_NAME.
    private static function adminUrl(): string
    {
        $appUrl = rtrim((string) EnvironmentHelper::getVariable('APP_URL', ''), '/');
        if ($appUrl === '') {
            return '';
        }

        return $appUrl . '/' . trim((string) EnvironmentHelper::getVariable('SHOPWARE_ADMINISTRATION_PATH_NAME', 'admin'), '/');
    }

    private static function pluginVersion(): string
    {
        $composer = json_decode((string) @file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        return is_array($composer) && is_string($composer['version'] ?? null) ? $composer['version'] : '';
    }

    // Returns the checkout link, after recording the invoice so only its webhooks can pay this transaction.
    protected function checkoutLinkFrom(array $response, OrderTransactionEntity $orderTransaction, Context $context): string
    {
        if (empty($response['checkoutLink']) || empty($response['id']) || !is_string($response['id'])) {
            $this->logger->error('The payment gateway did not return an invoice for order ' . $orderTransaction->getOrder()?->getOrderNumber());
            throw new \RuntimeException('The payment gateway did not return a checkout link.');
        }

        $this->orderService->rememberInvoiceId($orderTransaction, $response['id'], $context);

        return $response['checkoutLink'];
    }

    abstract public function sendReturnUrlToCheckout(PaymentTransactionStruct $transaction, Context $context): ?string;
}
