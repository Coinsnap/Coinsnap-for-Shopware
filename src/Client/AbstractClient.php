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

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Exception\RequestException;

class AbstractClient
{
    protected ClientInterface $client;

    protected LoggerInterface $logger;

    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {

        $this->client = $client;
        $this->logger = $logger;
    }
    protected function get(string $uri, array $options): array
    {
        return $this->request(Request::METHOD_GET, $uri, $options);
    }
    protected function post(string $uri, array $options): array
    {
        return $this->request(Request::METHOD_POST, $uri, $options);
    }

    private function request(string $method, string $uri, array $options = []): array
    {

        try {
            $response = $this->client->request($method, $uri, $options);
            $body = $response->getBody()->getContents();
            $this->logger->debug(
                '{method} {uri} with following response: {response}',
                [
                    'method' => \mb_strtoupper($method),
                    'uri' => $uri,
                    'response' => $body,
                ]
            );
            $decodedBody = \json_decode($body, true);
            if ($decodedBody === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to decode JSON response: ' . json_last_error_msg());

                throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
            }

            // Return the decoded response or an empty array
            return $decodedBody;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                $method = $e->getRequest()->getMethod();
                $message = $e->getMessage();

                $this->logger->error(
                    'Guzzle request failed: {message} {status} {reason} - {method} {uri} with options: {options} and response: {body}',
                    [
                        'status' => $statusCode,
                        'reason' => $reasonPhrase,
                        'method' => $method,
                        'uri' => $uri,
                        'options' => $options,
                        'message' => $message,
                        'body' => $body
                    ]
                );

                throw new \Exception($reasonPhrase, $statusCode);
            }

            $this->logger->error('Guzzle request failed: Unknown error');
            throw new \Exception('Unknown error');
        }
    }
}
