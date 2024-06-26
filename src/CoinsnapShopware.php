<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Coinsnap\Shopware\PaymentMethod\PaymentMethods;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Coinsnap\Shopware\PaymentMethod\CoinsnapBitcoinLightningPaymentMethod;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CoinsnapShopware extends Plugin
{
    public function install(InstallContext $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', ['coinsnap']));

        $customFieldIds = $customFieldSetRepository->search($criteria, $context->getContext())->first();
        if (!$customFieldIds) {
            $customFieldSetRepository->upsert(
                [
                    [
                        'name' => 'coinsnap',
                        'config' => [
                            'label' => [
                                'de-DE' => 'Coinsnap Information',
                                'en-GB' => 'Coinsnap Information',
                                '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'Coinsnap Information' //Fallback language
                            ]
                        ],
                        'customFields' => [
                            [
                                'name' => 'coinsnapInvoiceId',
                                'type' => CustomFieldTypes::TEXT,
                                'config' => [
                                    'label' => [
                                        'de-DE' => 'Rechnungs-ID',
                                        'en-GB' => 'Invoice ID',
                                        '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'Invoice ID'
                                    ]
                                ]
                            ],
                            [
                                'name' => 'coinsnapOrderStatus',
                                'type' => CustomFieldTypes::TEXT,
                                'config' => [
                                    'label' => [
                                        'de-DE' => 'Auftragsstatus',
                                        'en-GB' => 'Order Status',
                                        '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'Order Status'
                                    ]
                                ]
                            ],
                        ],
                        'relations' => [[
                            'entityName' => 'order'
                        ]],
                    ]
                ],
                $context->getContext()
            );
        }
        $this->addPaymentMethod(new CoinsnapBitcoinLightningPaymentMethod(), $context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
            $this->setPaymentMethodIsActive(new $paymentMethod(), false, $context->getContext());
        }
        if (!$context->keepUserData()) {

            $systemConfigRepository = $this->container->get('system_config.repository');
            $criteria = (new Criteria())
                ->addFilter(
                    new ContainsFilter('configurationKey', 'CoinsnapShopware.config')
                );
            $idSearchResult = $systemConfigRepository->searchIds($criteria, Context::createDefaultContext());

            $ids = \array_map(static function ($id) {
                return ['id' => $id];
            }, $idSearchResult->getIds());
            $systemConfigRepository->delete($ids, Context::createDefaultContext());
        }
    }

    public function activate(ActivateContext $context): void
    {

        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
            $this->setPaymentMethodIsActive(new $paymentMethod(), false, $context->getContext());
        }
        parent::deactivate($context);
    }
    public function update(UpdateContext $updateContext): void
    {
        $currentVersion = $updateContext->getCurrentPluginVersion();

        if (version_compare($currentVersion, '1.0.2', '=') && version_compare($currentVersion, '1.0.3', '<')) {

            foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
                $this->setPaymentMethodIsActive(new $paymentMethod(), false, $updateContext->getContext());
            }

            $configService = $this->container->get(SystemConfigService::class);
            $configKeysToDelete = [
                'CoinsnapShopware.config.btcpayServerUrl',
                'CoinsnapShopware.config.btcpayApiKey',
                'CoinsnapShopware.config.btcpayServerStoreId',
                'CoinsnapShopware.config.btcpayWebhookId',
                'CoinsnapShopware.config.btcpayWebhookSecret',
                'CoinsnapShopware.config.integrationStatus',
                'CoinsnapShopware.config.btcpayStorePaymentMethodBTC',
                'CoinsnapShopware.config.btcpayStorePaymentMethodLightning',
                'CoinsnapShopware.config.btcpayStorePaymentMethodMonero',
                'CoinsnapShopware.configbtcpayStorePaymentMethodLitecoin',
            ];
            foreach ($configKeysToDelete as $configKey) {
                $configService->delete($configKey);
            }
        }

        parent::update($updateContext);
    }

    private function addPaymentMethod($paymentMethod, Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId($paymentMethod);

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }

        /**
         * @var PluginIdProvider $pluginIdProvider
         */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $examplePaymentData = [
            'handlerIdentifier' => $paymentMethod->getPaymentHandler(),
            'pluginId' => $pluginId,
            'position' => $paymentMethod->getPosition(),
            'media' => [
                'id' => $this->ensureMedia($context, $paymentMethod->getName()),
                'mediaFolderId' => $this->getMediaDefaultFolderId($context),
            ],
            'translations' => $paymentMethod->getTranslations()
        ];

        /**
         * @var EntityRepositoryInterface $paymentRepository
         */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$examplePaymentData], $context);
    }

    private function setPaymentMethodIsActive($paymentMethod, bool $active, Context $context): void
    {
        /**
         * @var EntityRepositoryInterface $paymentRepository
         */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId($paymentMethod);

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId($paymentMethod): ?string
    {
        /**
         * @var EntityRepositoryInterface $paymentRepository
         */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', $paymentMethod->getPaymentHandler()));
        return $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext())->firstId();
    }

    private function getMediaEntity(string $fileName, Context $context): ?MediaEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));
        $mediaRepository = $this->container->get('media.repository');

        return $mediaRepository->search($criteria, $context)->first();
    }
    private function ensureMedia(Context $context, string $logoName): string
    {
        $filePath = realpath(__DIR__ . '/Resources/icons/' . strtolower($logoName) . '.svg');
        $fileName = hash_file('md5', $filePath);
        $media = $this->getMediaEntity($fileName, $context);
        $mediaRepository = $this->container->get('media.repository');

        if ($media) {
            return $media->getId();
        }

        $mediaFile = new MediaFile(
            $filePath,
            mime_content_type($filePath),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath)
        );
        $mediaId = Uuid::randomHex();
        $mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );
        $fileSaver = $this->container->get(FileSaver::class);
        $savedFileName = \sprintf("coinsnap_%s", strtolower($logoName));
        $fileSaver->persistFileToMedia(
            $mediaFile,
            $savedFileName,
            $mediaId,
            $context
        );

        return $mediaId;
    }
    private function getMediaDefaultFolderId(Context $context): ?string
    {
        $mediaFolderRepository = $this->container->get('media_folder.repository');
        $paymentMethodRepository = $this->container->get('payment_method.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $paymentMethodRepository->getDefinition()->getEntityName()));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);

        return $mediaFolderRepository->searchIds($criteria, $context)->firstId();
    }
}
