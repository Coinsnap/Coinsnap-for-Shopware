<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1763300000ProcessedInvoice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763300000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `coinsnap_processed_invoice` (
                `invoice_id` VARCHAR(64) NOT NULL,
                `transaction_id` VARCHAR(32) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
