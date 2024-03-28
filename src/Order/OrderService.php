<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Order;

use Coinsnap\Shopware\Client\ClientInterface;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Class OrderService
 * @package Coinsnap\Shopware
 */
class OrderService
{
    private ClientInterface $client;
    private ConfigurationService $configurationService;
    private EntityRepository $orderRepository;

    public function __construct(ClientInterface $client, ConfigurationService $configurationService, EntityRepository $orderRepository)
    {
        $this->client = $client;
        $this->configurationService = $configurationService;
        $this->orderRepository = $orderRepository;
    }

    public function invoiceIsFullyPaid(string $invoiceId): bool
    {

        $uri = '/api/v1/stores/' . $this->configurationService->getSetting('btcpayServerStoreId') . '/invoices/' . $invoiceId;
        $response = $this->client->sendGetRequest($uri);
        return ($response['status'] === 'Settled');
    }
    private function getId(string $orderNumber, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();
        return $orderId;
    }
}
