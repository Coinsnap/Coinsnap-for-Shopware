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

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;

class CoinsnapBitcoinLightningPaymentMethodHandler extends AbstractPaymentMethodHandler
{
    public function sendReturnUrlToCheckout(PaymentTransactionStruct $transaction, Context $context): ?string
    {
        try {
            $orderTransaction = $this->loadOrderTransaction($transaction->getOrderTransactionId(), $context);
            $order = $orderTransaction->getOrder();
            $returnUrl = $transaction->getReturnUrl();

            if ($orderTransaction->getAmount()->getTotalPrice() == 0) {
                $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
                return $returnUrl;
            }

            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId') . '/invoices';
            $response = $this->client->sendPostRequest(
                $uri,
                [
                    'amount' => $orderTransaction->getAmount()->getTotalPrice(),
                    'currency' => $order->getCurrency()->getIsoCode(),
                    'referralCode' => 'DEV17612c35cd8c54d3fad381615',
                    'metadata' =>
                    [
                        'orderNumber' => $order->getOrderNumber(),
                        'orderId' => $orderTransaction->getOrderId(),
                        'transactionId' => $orderTransaction->getId()
                    ],
                    'orderId' => $order->getOrderNumber(),
                    'redirectUrl' => $returnUrl,
                ]
            );

            return $response['checkoutLink'];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}
