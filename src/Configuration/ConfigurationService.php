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
    public const DOMAIN = 'CoinsnapShopware.config.';
    // Values only the server uses (webhook secrets, upgrade markers). The settings page loads
    // every key under DOMAIN into the admin browser, so these live under their own prefix.
    public const INTERNAL_DOMAIN = 'CoinsnapShopware.internal.';
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
    public function getInternal(string $setting)
    {
        return $this->systemConfigService->get(self::INTERNAL_DOMAIN . $setting);
    }
    public function setInternal(string $setting, $value)
    {
        return $this->systemConfigService->set(self::INTERNAL_DOMAIN . $setting, $value);
    }
    public function getShopName(?string $salesChannelId)
    {
        return $this->systemConfigService->get("core.basicInformation.shopName", $salesChannelId);
    }
}
