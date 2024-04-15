<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Webhook\Factory;

use Coinsnap\Shopware\Client\ClientInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Psr\Log\LoggerInterface;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Coinsnap\Shopware\Webhook\WebhookServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

use Coinsnap\Shopware\Webhook\CoinsnapWebhookService;
use Coinsnap\Shopware\Webhook\BTCPayWebhookService;

class WebhookFactory
{
  private ClientInterface $coinsnapClient;
  private ConfigurationService $configurationService;
  private OrderTransactionStateHandler $transactionStateHandler;
  private $orderService;
  private EntityRepository $orderRepository;
  private LoggerInterface $logger;

  public function __construct(ClientInterface $coinsnapClient, ConfigurationService $configurationService, OrderTransactionStateHandler $transactionStateHandler, $orderService, EntityRepository $orderRepository, LoggerInterface $logger)
  {
    $this->coinsnapClient = $coinsnapClient;
    $this->configurationService = $configurationService;
    $this->transactionStateHandler = $transactionStateHandler;
    $this->orderService = $orderService;
    $this->orderRepository = $orderRepository;
    $this->logger = $logger;
  }

  public function create(string $provider): WebhookServiceInterface
  {
    if ($provider === 'coinsnap') {
      return new CoinsnapWebhookService($this->coinsnapClient, $this->configurationService, $this->transactionStateHandler, $this->orderService, $this->orderRepository, $this->logger);
    }
    throw new \RuntimeException('Unsupported webhook provider.');
  }
}
