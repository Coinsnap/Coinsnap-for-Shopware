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

use Coinsnap\Shopware\Webhook\Factory\WebhookFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

class WebhookRouter
{
    private $webhookFactory;

    public function __construct(WebhookFactory $webhookFactory)
    {
        $this->webhookFactory = $webhookFactory;
    }
    public function route(Request $request, Context $context)
    {
        $provider = $this->getProviderFromRequest($request);
        if (isset($provider)) {
            $webhook = $this->webhookFactory->create($provider);
            return $webhook->process($request, $context);
        }
        throw new \RuntimeException('No webhook defined for the payment provider.');
    }
    public function getProviderFromRequest(Request $request): string
    {
        return 'coinsnap';
    }
}
