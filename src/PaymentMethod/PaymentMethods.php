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

use Coinsnap\Shopware\PaymentMethod\CoinsnapBitcoinLightningPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\BTCPayBitcoinPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\BTCPayLightningPaymentMethod;

class PaymentMethods
{
    public const PAYMENT_METHODS = [
        CoinsnapBitcoinLightningPaymentMethod::class,
        BTCPayBitcoinPaymentMethod::class,
        BTCPayLightningPaymentMethod::class,
    ];
}
