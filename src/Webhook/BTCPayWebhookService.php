<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Webhook;

use Symfony\Component\HttpFoundation\Request;

class BTCPayWebhookService extends AbstractWebhookService
{
    public const REQUIRED_HEADER = 'btcpay-sig';

    private const SETTINGS = [
        'webhookSecret' => 'btcpayWebhookSecret',
        'webhookId' => 'btcpayWebhookId',
        'storeId' => 'btcpayServerStoreId',
    ];

    protected function provider(): string
    {
        return 'btcpay';
    }

    protected function setting(string $name): string
    {
        return self::SETTINGS[$name];
    }

    protected function signatureProblem(Request $request, string $secret): ?string
    {
        return WebhookSignature::btcpayProblem(
            (string) $request->headers->get(self::REQUIRED_HEADER),
            $request->getContent(),
            $secret
        );
    }

    protected function decide(array $invoice, float $price, string $currency): string
    {
        return InvoiceDecision::btcpay($invoice, $price, $currency);
    }

    protected function paidEventTypes(): array
    {
        return ['InvoiceSettled'];
    }
}
