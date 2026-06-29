<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\PaymentMethod;

use Coinsnap\Shopware\PaymentHandler\BTCPayBitcoinPaymentMethodHandler;

class BTCPayBitcoinPaymentMethod
{
    public function getName(): string
    {
        return 'Bitcoin';
    }

    public function getTechnicalName(): string
    {
        return 'btcpay_bitcoin';
    }

    public function getPosition(): int
    {
        return -2;
    }

    public function getTranslations(): array
    {
        return [
            'de-DE' => [
                'description' => 'Zahle mit Bitcoin - BTCPay Server',
                'name' => 'Bitcoin - BTCPay Server',
            ],
            'en-GB' => [
                'description' => 'Pay with Bitcoin - BTCPay Server',
                'name' => 'Bitcoin - BTCPay Server',
            ],
            '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                'description' => 'Pay with Bitcoin - BTCPay Server',
                'name' => 'Bitcoin - BTCPay Server',
            ], //Fallback language
        ];
    }

    public function getPaymentHandler(): string
    {
        return BTCPayBitcoinPaymentMethodHandler::class;
    }
}
