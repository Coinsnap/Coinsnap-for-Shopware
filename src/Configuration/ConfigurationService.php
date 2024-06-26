<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Configuration;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigurationService
{
    protected const DOMAIN = 'CoinsnapShopware.config.';
    /**
     * @var SystemConfigService
     */
    private SystemConfigService $systemConfigService;

    /**
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getSetting(string $setting, ?string $salesChannelId = null)
    {
        return $this->systemConfigService->get(self::DOMAIN . $setting, $salesChannelId);
    }
    public function setSetting(string $setting, $value, ?string $salesChannelId = null)
    {
        return $this->systemConfigService->set(self::DOMAIN . $setting, $value, $salesChannelId);
    }
    public function getShopName(?string $salesChannelId)
    {
        return $this->systemConfigService->get("core.basicInformation.shopName", $salesChannelId);
    }
}
