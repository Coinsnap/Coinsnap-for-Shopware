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

interface ClientInterface
{
    public function sendPostRequest(string $resourceUri, array $data, array $headers = []): array;

    public function sendGetRequest(string $resourceUri, array $headers = []): array;

    public function createAuthHeader(): ?string;
}
