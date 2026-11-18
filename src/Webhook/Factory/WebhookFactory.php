<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Webhook\Factory;

use Coinsnap\Shopware\Webhook\BTCPayWebhookService;
use Coinsnap\Shopware\Webhook\CoinsnapWebhookService;
use Coinsnap\Shopware\Webhook\WebhookServiceInterface;

class WebhookFactory
{
    private CoinsnapWebhookService $coinsnapWebhookService;
    private BTCPayWebhookService $btcpayWebhookService;

    public function __construct(CoinsnapWebhookService $coinsnapWebhookService, BTCPayWebhookService $btcpayWebhookService)
    {
        $this->coinsnapWebhookService = $coinsnapWebhookService;
        $this->btcpayWebhookService = $btcpayWebhookService;
    }

    public function create(string $provider): WebhookServiceInterface
    {
        return match ($provider) {
            'coinsnap' => $this->coinsnapWebhookService,
            'btcpay' => $this->btcpayWebhookService,
            default => throw new \RuntimeException('Unsupported webhook provider.'),
        };
    }
}
