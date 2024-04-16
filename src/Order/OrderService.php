<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Order;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Class OrderService
 * @package Coinsnap\Shopware
 */
class OrderService
{
  private EntityRepository $orderRepository;

  public function __construct(EntityRepository $orderRepository)
  {
    $this->orderRepository = $orderRepository;
  }

  public function getId(string $orderNumber, Context $context): string
  {
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
    $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();
    return $orderId;
  }
}
