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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Coinsnap\Shopware\Webhook\WebhookServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route(defaults: ['_routeScope' => ['api']])]
class WebhookController extends AbstractController
{
    private $webhookRouter;

    public function __construct($webhookRouter)
    {
        $this->webhookRouter = $webhookRouter;
    }
    #[Route(path: '/api/_action/coinsnap/webhook-endpoint', name: 'api.action.coinsnap.webhook.endpoint', methods: ['POST'], defaults: ['XmlHttpRequest' => true, 'auth_required' => false, 'csrf_protected' => false])]
    public function endpoint(Request $request, Context $context): Response
    {
        return $this->webhookRouter->route($request, $context);
    }
}
