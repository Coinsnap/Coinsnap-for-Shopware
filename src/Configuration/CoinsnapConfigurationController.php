<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Configuration;

use Coinsnap\Shopware\Client\ClientInterface;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Coinsnap\Shopware\Webhook\WebhookServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;
use Coinsnap\Shopware\PaymentMethod\{CoinsnapLightningPaymentMethod, CoinsnapBitcoinPaymentMethod, CoinsnapBitcoinLightningPaymentMethod};

#[Route(defaults: ['_routeScope' => ['api']])]
class CoinsnapConfigurationController extends ConfigurationController
{
    private ClientInterface $client;
    private ConfigurationService $configurationService;
    private WebhookServiceInterface $webhookService;
    private $paymentRepository;

    public function __construct(ClientInterface $client, ConfigurationService $configurationService, WebhookServiceInterface $webhookService, $paymentRepository)
    {
        $this->client = $client;
        $this->configurationService = $configurationService;
        $this->webhookService = $webhookService;
        $this->paymentRepository = $paymentRepository;
    }

    #[Route(path: '/api/_action/coinsnap/coinsnap_verify', name: 'api.action.coinsnap.coinsnap_verify', methods: ['GET'])]
    public function verifyApiKey(Request $request, Context $context)
    {
        try {
            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('coinsnapStoreId');
            $response = $this->client->sendGetRequest($uri);
            if (!is_array($response)) {
                $this->configurationService->setSetting('coinsnapIntegrationStatus', false);
                return new JsonResponse(['success' => false, 'message' => 'Check server url and API key.']);
            }
            if (!$this->webhookService->register($request, null)) {
                $this->configurationService->setSetting('coinsnapIntegrationStatus', false);
                return new JsonResponse(['success' => false, 'message' => "There is a temporary problem with Coinsnap Server. A webhook can't be created at the moment. Please try later."]);
            }
            $this->updatePaymentMethodStatus($context, CoinsnapBitcoinLightningPaymentMethod::class, true, $this->paymentRepository);
            $this->configurationService->setSetting('coinsnapIntegrationStatus', true);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->configurationService->setSetting('coinsnapIntegrationStatus', false);
            return new JsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}
