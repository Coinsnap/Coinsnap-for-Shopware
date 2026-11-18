<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Webhook;

// Transactions created before 1.2.0 have no list of their invoice IDs. They are accepted only for a
// short time after the upgrade, while invoices opened before it can still be paid; after that only
// invoices this plugin recorded can pay a transaction.
class LegacyInvoiceWindow
{
    // Unix time the invoice records started, set once by Migration1763400000InternalSettings.
    public const SETTING = 'invoiceRecordsSince';
    public const WINDOW_SECONDS = 86400;

    public static function accepts(?\DateTimeInterface $transactionCreatedAt, int $recordsSince, int $now): bool
    {
        if ($transactionCreatedAt === null || $recordsSince <= 0) {
            return false;
        }

        return $transactionCreatedAt->getTimestamp() < $recordsSince
            && $now <= $recordsSince + self::WINDOW_SECONDS;
    }
}
