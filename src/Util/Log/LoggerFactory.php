<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Util\Log;

use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class LoggerFactory
{
    public function createLogger(): Logger
    {
        $logger = new Logger('coinsnap_shopware');
        // $logger->pushHandler(new LogHandler()); // Potential issue here
        $logger->pushProcessor(new PsrLogMessageProcessor());

        return $logger;
    }
}
