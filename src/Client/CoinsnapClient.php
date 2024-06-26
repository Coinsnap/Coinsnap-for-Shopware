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

class CoinsnapClient extends AbstractClient implements ClientInterface
{
    protected ConfigurationService $configurationService;
    protected LoggerInterface $logger;

    public function __construct(ConfigurationService $configurationService, LoggerInterface $logger)
    {
        $this->configurationService = $configurationService;

        $authorizationHeader = $this->createAuthHeader();

        $client = new Client(
            [
                'base_uri' => 'https://app.coinsnap.io',
                'headers' => [
                    'X-Api-Key' => $authorizationHeader
                ]
            ]
        );
        parent::__construct($client, $logger);
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
        return $this->configurationService->getSetting('coinsnapApiKey');
    }
}
