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
use Shopware\Core\Framework\Context;

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

    public function getId(string $orderNumber, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        return $this->orderRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * Returns the technical name of the order transaction's current state, or
     * null if the order/transaction or its state cannot be resolved.
     */
    public function getTransactionState(string $orderId, string $transactionId, Context $context): ?string
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $order = $this->orderRepository->search($criteria, $context)->first();
        $transaction = $order?->getTransactions()?->get($transactionId);

        return $transaction?->getStateMachineState()?->getTechnicalName();
    }
}
