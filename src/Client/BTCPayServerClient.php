<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Client;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Coinsnap\Shopware\Configuration\ConfigurationService;

class BTCPayServerClient extends AbstractClient implements ClientInterface
{
    protected ConfigurationService $configurationService;
    protected LoggerInterface $logger;

    public function __construct(ConfigurationService $configurationService, LoggerInterface $logger)
    {
        $this->configurationService = $configurationService;

        $authorizationHeader = $this->createAuthHeader();

        $client = new Client(
            [
                'base_uri' => $this->resolveBaseUri(),
                'headers' => [
                    'Authorization' => $authorizationHeader
                ]
            ]
        );
        parent::__construct($client, $logger);
    }

    /**
     * Validates the configured BTCPay Server URL before it is used as the
     * Guzzle base_uri. We only enforce an http/https scheme: BTCPay is
     * frequently self-hosted on a private network or a custom domain, so
     * blocking private/loopback addresses would break legitimate setups.
     * Rejecting non-http(s) schemes still removes the file://, gopher:// and
     * similar request-forgery vectors Guzzle would otherwise accept.
     */
    private function resolveBaseUri(): string
    {
        $url = (string) $this->configurationService->getSetting('btcpayServerUrl');

        // This service is instantiated by the container even on Coinsnap-only
        // installs where BTCPay is never configured, so an empty URL must stay
        // permissible (Guzzle simply has no base_uri and BTCPay is unused).
        if ($url === '') {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('BTCPay Server URL must use the http or https scheme.');
        }

        return $url;
    }
    public function sendPostRequest(string $resourceUri, array $data, array $headers = []): array
    {
        $headers['content-type'] = 'application/json';
        $options = [
            'headers' => $headers,
            'json'  => $data
        ];
        return $this->post($resourceUri, $options);
    }
    public function sendGetRequest(string $resourceUri, array $headers = []): array
    {
        $options = [
            'headers' => $headers
        ];
        return $this->get($resourceUri, $options);
    }
    public function createAuthHeader(): ?string
    {
        return 'token ' . $this->configurationService->getSetting('btcpayApiKey');
    }
}
