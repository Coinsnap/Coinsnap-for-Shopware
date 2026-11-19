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

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * The order data sent with a new Coinsnap or BTCPay invoice: the order number as orderId,
 * the buyer, the items and the totals, so the merchant can find the order from the invoice.
 *
 * The webhook matches invoices by metadata `transactionId` and `orderNumber`; both keep their values.
 */
final class InvoiceData
{
    // Associations of the order transaction that fromOrder() reads.
    public const ASSOCIATIONS = [
        'order.currency',
        'order.orderCustomer',
        'order.billingAddress.country',
        'order.billingAddress.countryState',
        'order.lineItems',
        'order.deliveries.shippingMethod',
        'order.language.locale',
    ];

    private const MAX_ITEM_DESC = 255;

    /**
     * @return array{orderId: string, buyerEmail?: string, metadata: array<string, mixed>}
     */
    public static function fromOrder(OrderEntity $order, OrderTransactionEntity $transaction, string $adminUrl, string $pluginVersion): array
    {
        $orderNumber = (string) $order->getOrderNumber();
        $decimals = $order->getItemRounding()?->getDecimals() ?? 2;

        $metadata = [
            'orderId' => $orderNumber,
            'orderNumber' => $orderNumber,
            'transactionId' => $transaction->getId(),
            'shopwareOrderId' => $order->getId(),
            'orderUrl' => $adminUrl !== '' ? rtrim($adminUrl, '/') . '#/sw/order/detail/' . $order->getId() : '',
            'platform' => 'shopware',
            'pluginVersion' => $pluginVersion,
        ];

        $customer = $order->getOrderCustomer();
        $billing = $order->getBillingAddress();
        $name = $customer !== null
            ? trim($customer->getFirstName() . ' ' . $customer->getLastName())
            : ($billing !== null ? trim($billing->getFirstName() . ' ' . $billing->getLastName()) : '');
        $email = $customer !== null ? trim($customer->getEmail()) : '';

        $metadata += [
            'customerName' => $name,
            'buyerName' => $name,
            'buyerEmail' => $email,
        ];

        if ($billing !== null) {
            $metadata += [
                'buyerCompany' => (string) $billing->getCompany(),
                'buyerPhone' => (string) $billing->getPhoneNumber(),
                'buyerAddress1' => $billing->getStreet(),
                'buyerAddress2' => trim($billing->getAdditionalAddressLine1() . ' ' . $billing->getAdditionalAddressLine2()),
                'buyerCity' => $billing->getCity(),
                'buyerZip' => (string) $billing->getZipcode(),
                'buyerState' => (string) ($billing->getCountryState()?->getShortCode() ?? ''),
                'buyerCountry' => (string) ($billing->getCountry()?->getIso() ?? ''),
            ];
        }

        $metadata['buyerMessage'] = (string) $order->getCustomerComment();

        [$lineItems, $couponCodes, $discountTotal] = self::lineItems($order, $decimals);
        $names = array_map(static fn (array $item): string => $item['quantity'] . ' x ' . $item['name'], $lineItems);
        $skus = array_values(array_filter(array_map(static fn (array $item): string => $item['sku'] ?? '', $lineItems)));

        $metadata['itemDesc'] = self::truncate($names !== [] ? implode(', ', $names) : 'Order ' . $orderNumber);
        $metadata['itemCode'] = implode(', ', array_unique($skus));
        $metadata['lineItems'] = $lineItems;
        $metadata['couponCodes'] = implode(', ', $couponCodes);
        $metadata['discountTotal'] = $discountTotal;
        $metadata['shippingMethod'] = (string) ($order->getDeliveries()?->first()?->getShippingMethod()?->getName() ?? '');
        $metadata['shippingTotal'] = round($order->getShippingTotal(), $decimals);
        $metadata['taxTotal'] = round($order->getPrice()->getCalculatedTaxes()->getAmount(), $decimals);
        $metadata['language'] = (string) ($order->getLanguage()?->getLocale()?->getCode() ?? '');

        $request = [
            'orderId' => $orderNumber,
            'metadata' => self::withoutEmpty($metadata),
        ];
        if ($email !== '') {
            $request['buyerEmail'] = $email;
        }

        return $request;
    }

    /**
     * Top-level items (bundles' children are left out). Promotions become couponCodes and discountTotal.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: float}
     */
    private static function lineItems(OrderEntity $order, int $decimals): array
    {
        $items = [];
        $codes = [];
        $discount = 0.0;

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if (!$lineItem instanceof OrderLineItemEntity || $lineItem->getParentId() !== null) {
                continue;
            }

            if ($lineItem->getType() === 'promotion') {
                $discount += abs($lineItem->getTotalPrice());
                $code = $lineItem->getPayload()['code'] ?? '';
                if (is_string($code) && $code !== '') {
                    $codes[] = $code;
                }
                continue;
            }

            $sku = $lineItem->getPayload()['productNumber'] ?? '';
            $items[] = self::withoutEmpty([
                'name' => $lineItem->getLabel(),
                'sku' => is_string($sku) ? $sku : '',
                'productId' => (string) $lineItem->getProductId(),
                'type' => (string) $lineItem->getType(),
                'quantity' => $lineItem->getQuantity(),
                'price' => round($lineItem->getUnitPrice(), $decimals),
                'total' => round($lineItem->getTotalPrice(), $decimals),
            ]);
        }

        return [$items, array_values(array_unique($codes)), round($discount, $decimals)];
    }

    // Empty strings, nulls and empty lists are left out; zero amounts are kept.
    private static function withoutEmpty(array $values): array
    {
        return array_filter($values, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private static function truncate(string $text): string
    {
        return mb_strlen($text) > self::MAX_ITEM_DESC ? mb_substr($text, 0, self::MAX_ITEM_DESC - 3) . '...' : $text;
    }
}
