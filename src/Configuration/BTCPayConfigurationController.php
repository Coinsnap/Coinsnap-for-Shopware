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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Coinsnap\Shopware\PaymentMethod\BTCPayBitcoinPaymentMethod;
use Coinsnap\Shopware\PaymentMethod\BTCPayLightningPaymentMethod;

#[Route(defaults: ['_routeScope' => ['api']])]
class BTCPayConfigurationController extends ConfigurationController
{
    private ClientInterface $client;
    private ConfigurationService $configurationService;
    private WebhookServiceInterface $webhookService;
    private EntityRepository $paymentRepository;

    public function __construct(ClientInterface $client, ConfigurationService $configurationService, WebhookServiceInterface $webhookService, EntityRepository $paymentRepository)
    {
        $this->client = $client;
        $this->configurationService = $configurationService;
        $this->webhookService = $webhookService;
        $this->paymentRepository = $paymentRepository;
    }

    #[Route(path: '/api/_action/coinsnap/btcpay_verify', name: 'api.action.coinsnap.btcpay_verify', methods: ['GET'])]
    public function verifyApiKey(Request $request, Context $context)
    {
        try {
            $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId');
            $response = $this->client->sendGetRequest($uri);
            if (!is_array($response)) {
                $this->configurationService->setSetting('btcpayIntegrationStatus', false);
                return new JsonResponse(['success' => false, 'message' => 'Check server url and API key.']);
            }
            if (!$this->webhookService->register($request, null)) {
                $this->configurationService->setSetting('btcpayIntegrationStatus', false);
                return new JsonResponse(['success' => false, 'message' => "There is a temporary problem with BTCPay Server. A webhook can't be created at the moment. Please try later."]);
            }
            $this->configurationService->setSetting('btcpayIntegrationStatus', true);
            $this->checkEnabledPaymentMethodsBTCPayStore($context);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->configurationService->setSetting('btcpayIntegrationStatus', false);
            return new JsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Receives the API key and store permissions BTCPay redirects back with
     * after the merchant authorizes the plugin's API key request.
     *
     * BTCPay posts the generated key back through a top-level browser
     * navigation, so the Shopware admin bearer token cannot be attached and the
     * route has to stay unauthenticated. A one-time, unguessable state nonce
     * (created before the merchant is redirected to BTCPay) guards the callback
     * against forged or replayed credential overwrites, and the payload shape is
     * validated before anything is persisted.
     */
    #[Route(path: '/api/_action/coinsnap/btcpay_credentials', name: 'api.action.coinsnap.btcpay_credentials', defaults: ['auth_required' => false, 'XmlHttpRequest' => true], methods: ['POST'])]
    public function updateCredentials(Request $request): RedirectResponse
    {
        $redirectUrl = $request->server->get('APP_URL') . '/admin#/sw/extension/config/CoinsnapShopware';

        $expectedState = $this->configurationService->getSetting('btcpayAuthState');
        // Consume the nonce regardless of outcome so it can't be replayed.
        $this->configurationService->setSetting('btcpayAuthState', null);

        $state = $request->query->get('state');
        if (empty($state) || empty($expectedState) || !hash_equals((string) $expectedState, (string) $state)) {
            return new RedirectResponse($redirectUrl);
        }

        $body = $request->request->all();
        $apiKey = $body['apiKey'] ?? '';
        $permission = $body['permissions'][0] ?? '';

        // Reject anything that doesn't look like a BTCPay key or a
        // "scope:storeId" permission before persisting it.
        if (!is_string($apiKey) || !preg_match('/^[A-Za-z0-9]+$/', $apiKey)
            || !is_string($permission) || !preg_match('/^[a-z0-9.]+:[A-Za-z0-9]+$/', $permission)) {
            return new RedirectResponse($redirectUrl);
        }

        $this->configurationService->setSetting('btcpayApiKey', $apiKey);
        $this->configurationService->setSetting('btcpayServerStoreId', explode(':', $permission)[1]);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * Reads which payment methods the BTCPay store actually has enabled and
     * mirrors that onto the Shopware payment methods, so a method is only
     * offered at checkout when BTCPay can settle it.
     */
    private function checkEnabledPaymentMethodsBTCPayStore(Context $context): void
    {
        // BTCPay v2 ids are "BTC-CHAIN"/"BTC-LN"; v1 used "BTC"/"BTC-LightningNetwork". Accept both.
        $onChainIds = ['BTC-CHAIN', 'BTC'];
        $lightningIds = ['BTC-LN', 'BTC-LightningNetwork'];
        $paymentHandlers = ['BTC' => BTCPayBitcoinPaymentMethod::class, 'Lightning' => BTCPayLightningPaymentMethod::class];
        $this->disableBTCPaymentMethodsBeforeTest($context, $paymentHandlers);

        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/payment-methods';
        $response = $this->client->sendGetRequest($uri);
        foreach ($response as $method) {
            $key = $method['paymentMethodId'] ?? null;
            if ($key === null) {
                continue;
            }
            if (in_array($key, $onChainIds, true)) {
                $label = 'BTC';
            } elseif (in_array($key, $lightningIds, true)) {
                $label = 'Lightning';
            } else {
                continue;
            }
            $this->configurationService->setSetting('btcpayStorePaymentMethod' . $label, $method['enabled']);
            $this->updatePaymentMethodStatus($context, $paymentHandlers[$label], $method['enabled'], $this->paymentRepository);
        }
    }

    private function disableBTCPaymentMethodsBeforeTest(Context $context, array $paymentHandlers): void
    {
        $this->configurationService->setSetting('btcpayStorePaymentMethodBTC', false);
        $this->configurationService->setSetting('btcpayStorePaymentMethodLightning', false);
        foreach ($paymentHandlers as $handler) {
            $this->updatePaymentMethodStatus($context, $handler, false, $this->paymentRepository);
        }
    }
}
