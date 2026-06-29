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

    // Only enforce http/https; private/self-hosted BTCPay hosts are legitimate.
    private function resolveBaseUri(): string
    {
        $url = (string) $this->configurationService->getSetting('btcpayServerUrl');

        // Empty on Coinsnap-only installs; leave base_uri unset.
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
