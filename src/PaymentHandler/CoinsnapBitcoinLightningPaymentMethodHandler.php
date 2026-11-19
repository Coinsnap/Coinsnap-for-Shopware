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
        $orderTransaction = $this->loadOrderTransaction($transaction->getOrderTransactionId(), $context);
        $order = $orderTransaction->getOrder();
        $returnUrl = $transaction->getReturnUrl();

        if ($orderTransaction->getAmount()->getTotalPrice() === 0.0) {
            $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
            return $returnUrl;
        }

        $redirectUrl = $this->buildGatewayRedirectUrl($orderTransaction, $returnUrl, $context);

        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId') . '/invoices';
        // orderId is the order number; the webhook matches on metadata transactionId and orderNumber.
        $response = $this->client->sendPostRequest(
            $uri,
            $this->invoiceData($orderTransaction) + [
                'amount' => $orderTransaction->getAmount()->getTotalPrice(),
                'currency' => $order->getCurrency()->getIsoCode(),
                'referralCode' => 'DEV17612c35cd8c54d3fad381615',
                'redirectUrl' => $redirectUrl,
            ]
        );

        // A null link reads as "paid, no redirect" in Shopware, so a missing one throws.
        return $this->checkoutLinkFrom($response, $orderTransaction, $context);
    }
}
