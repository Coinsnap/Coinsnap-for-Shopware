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

class BTCPayLightningPaymentMethodHandler extends AbstractPaymentMethodHandler
{
    public function sendReturnUrlToCheckout(PaymentTransactionStruct $transaction, Context $context): ?string
    {
        $orderTransaction = $this->loadOrderTransaction($transaction->getOrderTransactionId(), $context);
        $order = $orderTransaction->getOrder();
        $returnUrl = $transaction->getReturnUrl();

        if ($orderTransaction->getAmount()->getTotalPrice() === 0.0) {
            $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
            return $returnUrl;
        }

        $redirectUrl = $this->buildGatewayRedirectUrl($orderTransaction, $returnUrl, $context);

        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/invoices';
        $response = $this->client->sendPostRequest(
            $uri,
            [
                'amount' => $orderTransaction->getAmount()->getTotalPrice(),
                'currency' => $order->getCurrency()->getIsoCode(),
                'metadata' =>
                [
                    'orderNumber' => $order->getOrderNumber(),
                    'orderId' => $orderTransaction->getOrderId(),
                    'transactionId' => $orderTransaction->getId()
                ],
                'checkout' => [
                    'redirectURL' => $redirectUrl,
                    'redirectAutomatically' => true,
                    'paymentMethods' => ['BTC-LightningNetwork']
                ]
            ]
        );

        // A null link reads as "paid, no redirect" in Shopware; fail instead.
        if (empty($response['checkoutLink'])) {
            $this->logger->error('BTCPay did not return a checkout link for order ' . $order->getOrderNumber());
            throw new \RuntimeException('The payment gateway did not return a checkout link.');
        }

        return $response['checkoutLink'];
    }
}
