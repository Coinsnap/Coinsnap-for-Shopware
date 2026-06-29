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
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
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
    protected LoggerInterface $logger;

    public function __construct(
        ClientInterface $client,
        ConfigurationService $configurationService,
        OrderTransactionStateHandler $transactionStateHandler,
        EntityRepository $orderTransactionRepository,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->configurationService = $configurationService;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderTransactionRepository = $orderTransactionRepository;
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
     * Loads the order transaction together with the order and currency
     * associations the gateway call needs (amount, currency, order number).
     */
    protected function loadOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.currency');

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

    abstract public function sendReturnUrlToCheckout(PaymentTransactionStruct $transaction, Context $context): ?string;
}
