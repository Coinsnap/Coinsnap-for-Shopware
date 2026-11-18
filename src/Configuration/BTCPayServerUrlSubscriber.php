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

use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// A BTCPay key belongs to one server. When the saved server URL changes without a new key, the
// stored key (and the webhook created with it) is dropped, so it is never sent to the new address.
class BTCPayServerUrlSubscriber implements EventSubscriberInterface
{
    private const URL = ConfigurationService::DOMAIN . 'btcpayServerUrl';
    private const API_KEY = ConfigurationService::DOMAIN . 'btcpayApiKey';

    private ConfigurationService $configurationService;

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    public static function getSubscribedEvents(): array
    {
        return [BeforeSystemConfigMultipleChangedEvent::class => 'onBeforeSave'];
    }

    public function onBeforeSave(BeforeSystemConfigMultipleChangedEvent $event): void
    {
        // The plugin reads only the global settings.
        if ($event->getSalesChannelId() !== null) {
            return;
        }

        $changes = self::changesFor(
            $event->getConfig(),
            (string) $this->configurationService->getSetting('btcpayServerUrl'),
            (string) $this->configurationService->getSetting('btcpayApiKey')
        );
        foreach ($changes as $key => $value) {
            $event->setValue($key, $value);
        }
    }

    /**
     * The extra values to save with $config, keyed by full system config key.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function changesFor(array $config, string $storedUrl, string $storedKey): array
    {
        if (!array_key_exists(self::URL, $config)) {
            return [];
        }

        $oldUrl = self::normalize($storedUrl);
        if ($oldUrl === '' || self::normalize((string) $config[self::URL]) === $oldUrl) {
            return [];
        }

        // A different, non-empty key saved together with the new URL is the merchant's new key;
        // "Test connection" checks it against the new server.
        $submittedKey = array_key_exists(self::API_KEY, $config) ? trim((string) $config[self::API_KEY]) : $storedKey;
        if ($submittedKey !== '' && $submittedKey !== $storedKey) {
            return [];
        }

        return [
            self::API_KEY => null,
            ConfigurationService::DOMAIN . 'btcpayWebhookId' => null,
            ConfigurationService::INTERNAL_DOMAIN . 'btcpayWebhookSecret' => null,
            ConfigurationService::DOMAIN . 'btcpayIntegrationStatus' => false,
        ];
    }

    private static function normalize(string $url): string
    {
        return rtrim(trim($url), "/");
    }
}
