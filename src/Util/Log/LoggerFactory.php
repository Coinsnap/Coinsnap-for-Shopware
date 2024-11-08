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
use Monolog\Handler\RotatingFileHandler;

class LoggerFactory {
    private $logPath;
    private $rotationCount;

    public function __construct(string $logPath, int $rotationCount) {
        $this->logPath = $logPath;
        $this->rotationCount = $rotationCount;
    }

    public function createLogger(): Logger {
        $logger = new Logger('coinsnap_logger');
        $handler = new RotatingFileHandler($this->logPath, $this->rotationCount);
        $logger->pushHandler($handler);

        return $logger;
    }
}
