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

use Coinsnap\Shopware\Configuration\ConfigurationService;
use Coinsnap\Shopware\Webhook\LegacyInvoiceWindow;
use Coinsnap\Shopware\Webhook\ProcessedInvoices;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

// Moves the webhook secrets out of the settings domain (the settings page loads every key of it
// into the admin browser) and records when per-transaction invoice records started.
class Migration1763400000InternalSettings extends MigrationStep
{
    private const MOVED_KEYS = ['coinsnapWebhookSecret', 'btcpayWebhookSecret'];

    public function getCreationTimestamp(): int
    {
        return 1763400000;
    }

    public function update(Connection $connection): void
    {
        foreach (self::MOVED_KEYS as $name) {
            $this->moveKey($connection, ConfigurationService::DOMAIN . $name, ConfigurationService::INTERNAL_DOMAIN . $name);
        }

        $sinceKey = ConfigurationService::INTERNAL_DOMAIN . LegacyInvoiceWindow::SETTING;
        if (!$this->globalKeyExists($connection, $sinceKey)) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => $sinceKey,
                'configuration_value' => json_encode(['_value' => time()]),
                'sales_channel_id' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $hasIndex = $connection->fetchOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index',
            ['table' => ProcessedInvoices::TABLE, 'index' => 'idx.coinsnap_processed_invoice.transaction_id']
        );
        if ($hasIndex === false) {
            $connection->executeStatement(
                'ALTER TABLE `' . ProcessedInvoices::TABLE . '` ADD INDEX `idx.coinsnap_processed_invoice.transaction_id` (`transaction_id`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function moveKey(Connection $connection, string $from, string $to): void
    {
        if (!$this->globalKeyExists($connection, $to)) {
            $connection->executeStatement(
                'UPDATE `system_config` SET `configuration_key` = :to WHERE `configuration_key` = :from AND `sales_channel_id` IS NULL',
                ['from' => $from, 'to' => $to]
            );
        }
        // The plugin only reads the global value; per-sales-channel copies are never used.
        $connection->executeStatement('DELETE FROM `system_config` WHERE `configuration_key` = :from', ['from' => $from]);
    }

    private function globalKeyExists(Connection $connection, string $key): bool
    {
        return $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => $key]
        ) !== false;
    }
}
