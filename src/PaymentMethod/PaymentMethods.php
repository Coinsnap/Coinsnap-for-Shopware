<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\PaymentMethod;

use Coinsnap\Shopware\PaymentMethod\BitcoinPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\LightningPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\BitcoinLightningPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\CoinsnapBitcoinPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\CoinsnapLightningPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\CoinsnapBitcoinLightningPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\MoneroPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\LitecoinPaymentMethod;

class PaymentMethods
{
    public const PAYMENT_METHODS = [
        // CoinsnapBitcoinPaymentMethod::class,
        // CoinsnapLightningPaymentMethod::class,
        CoinsnapBitcoinLightningPaymentMethod::class,
        // BitcoinPaymentMethod::class,
        // LightningPaymentMethod::class,
        // BitcoinLightningPaymentMethod::class,
        // MoneroPaymentMethod::class,
        // LitecoinPaymentMethod::class,
    ];
}
