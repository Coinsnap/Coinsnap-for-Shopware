<?php

declare(strict_types=1);

/**
 * Captures the create-invoice request of every payment handler and checks orderId and metadata.
 *
 * Needs shopware/core classes (no database, no kernel). Run with:
 *   SHOPWARE_AUTOLOAD=/path/to/vendor/autoload.php php tests/InvoiceRequestTest.php
 * SHOPWARE_AUTOLOAD defaults to this plugin's vendor/autoload.php (`composer install`).
 */

use Coinsnap\Shopware\Client\ClientInterface;
use Coinsnap\Shopware\Configuration\ConfigurationService;
use Coinsnap\Shopware\Order\OrderService;
use Coinsnap\Shopware\PaymentHandler\AbstractPaymentMethodHandler;
use Coinsnap\Shopware\PaymentHandler\BTCPayBitcoinPaymentMethodHandler;
use Coinsnap\Shopware\PaymentHandler\BTCPayLightningPaymentMethodHandler;
use Coinsnap\Shopware\PaymentHandler\CoinsnapBitcoinLightningPaymentMethodHandler;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

$autoload = getenv('SHOPWARE_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('Coinsnap\\Shopware\\', __DIR__ . '/../src/');

$_SERVER['APP_URL'] = 'https://shop.example';
$_ENV['APP_URL'] = 'https://shop.example';

final class RecordingClient implements ClientInterface
{
    public array $posts = [];

    public function sendPostRequest(string $resourceUri, array $data, array $headers = []): array
    {
        // Round-trip through JSON, as Guzzle's `json` option sends it.
        $this->posts[] = ['uri' => $resourceUri, 'body' => json_decode(json_encode($data, JSON_THROW_ON_ERROR), true)];

        return ['id' => 'inv_1', 'checkoutLink' => 'https://pay.example/i/inv_1'];
    }

    public function sendGetRequest(string $resourceUri, array $headers = []): array
    {
        return [];
    }

    public function createAuthHeader(): ?string
    {
        return null;
    }
}

final class FixedConfig extends ConfigurationService
{
    public function __construct()
    {
    }

    public function getSetting(string $setting, ?string $salesChannelId = null)
    {
        return ['coinsnapStoreId' => 'cs-store', 'btcpayServerStoreId' => 'bp-store'][$setting] ?? '';
    }
}

final class TransactionRepository extends EntityRepository
{
    public function __construct(private OrderTransactionEntity $transaction)
    {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult('order_transaction', 1, new OrderTransactionCollection([$this->transaction]), null, $criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return (new ReflectionClass(EntityWrittenContainerEvent::class))->newInstanceWithoutConstructor();
    }
}

function price(float $unit, int $quantity = 1): CalculatedPrice
{
    return new CalculatedPrice($unit, $unit * $quantity, new CalculatedTaxCollection(), new TaxRuleCollection(), $quantity);
}

function lineItem(string $id, string $type, string $label, int $quantity, float $unit, array $payload, ?string $parentId = null): OrderLineItemEntity
{
    $item = new OrderLineItemEntity();
    $item->assign([
        'id' => $id, 'type' => $type, 'label' => $label, 'quantity' => $quantity,
        'unitPrice' => $unit, 'totalPrice' => $unit * $quantity, 'payload' => $payload,
        'parentId' => $parentId, 'productId' => $type === 'product' ? 'prod-' . $id : null,
    ]);

    return $item;
}

function transaction(bool $withCustomerData = true): OrderTransactionEntity
{
    $currency = new CurrencyEntity();
    $currency->assign(['id' => 'cur', 'isoCode' => 'EUR']);

    $order = new OrderEntity();
    $order->assign([
        'id' => '0190aaaabbbbccccddddeeeeffff0001',
        'orderNumber' => '10042',
        'currency' => $currency,
        'itemRounding' => new CashRoundingConfig(2, 0.01, true),
        'shippingTotal' => 4.9,
        'price' => new CartPrice(40.0, 47.4, 42.5, new CalculatedTaxCollection([new CalculatedTax(7.4, 19.0, 47.4)]), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
        'customerComment' => $withCustomerData ? 'Please ring twice' : null,
        'lineItems' => new OrderLineItemCollection([
            lineItem('li1', 'product', 'Coffee mug', 2, 12.5, ['productNumber' => 'MUG-1']),
            lineItem('li2', 'product', 'T-shirt', 1, 25.0, ['productNumber' => 'TS-9']),
            lineItem('li3', 'product', 'Bundle part', 1, 0.0, ['productNumber' => 'PART'], 'li2'),
            lineItem('li4', 'promotion', 'Autumn sale', 1, -7.5, ['code' => 'AUTUMN10']),
        ]),
    ]);

    if ($withCustomerData) {
        $customer = new OrderCustomerEntity();
        $customer->assign(['firstName' => 'Ada', 'lastName' => 'Lovelace', 'email' => 'ada@example.com']);

        $country = new CountryEntity();
        $country->assign(['iso' => 'DE']);
        $state = new CountryStateEntity();
        $state->assign(['shortCode' => 'DE-BE']);
        $billing = new OrderAddressEntity();
        $billing->assign([
            'firstName' => 'Ada', 'lastName' => 'Lovelace', 'street' => 'Main St 1', 'additionalAddressLine1' => 'Floor 2',
            'zipcode' => '10115', 'city' => 'Berlin', 'phoneNumber' => '+49 30 1234', 'country' => $country, 'countryState' => $state,
        ]);

        $shipping = new ShippingMethodEntity();
        $shipping->assign(['name' => 'Standard']);
        $delivery = new OrderDeliveryEntity();
        $delivery->assign(['id' => 'del1', 'shippingMethod' => $shipping, 'shippingCosts' => price(4.9)]);

        $locale = new LocaleEntity();
        $locale->assign(['code' => 'de-DE']);
        $language = new LanguageEntity();
        $language->assign(['locale' => $locale]);

        $order->assign([
            'orderCustomer' => $customer,
            'billingAddress' => $billing,
            'deliveries' => new OrderDeliveryCollection([$delivery]),
            'language' => $language,
        ]);
    }

    $transaction = new OrderTransactionEntity();
    $transaction->assign([
        'id' => '0190aaaabbbbccccddddeeeeffff0002',
        'orderId' => $order->getId(),
        'order' => $order,
        'amount' => price(47.4),
        'customFields' => [],
    ]);

    return $transaction;
}

function run(string $handlerClass, OrderTransactionEntity $transaction): array
{
    $client = new RecordingClient();
    $repository = new TransactionRepository($transaction);
    /** @var AbstractPaymentMethodHandler $handler */
    $handler = new $handlerClass(
        $client,
        new FixedConfig(),
        (new ReflectionClass(OrderTransactionStateHandler::class))->newInstanceWithoutConstructor(),
        $repository,
        new OrderService($repository, $repository),
        new NullLogger()
    );

    $link = $handler->sendReturnUrlToCheckout(
        new PaymentTransactionStruct($transaction->getId(), 'https://shop.example/payment/finalize-transaction?_sw_payment_token=x'),
        Context::createDefaultContext()
    );
    if ($link !== 'https://pay.example/i/inv_1' || count($client->posts) !== 1) {
        throw new RuntimeException('handler did not create exactly one invoice');
    }

    return $client->posts[0];
}

$failures = 0;
$checks = 0;
function check(string $label, $expected, $actual): void
{
    global $failures, $checks;
    $checks++;
    if ($expected !== $actual) {
        $failures++;
        echo "FAIL {$label}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

$handlers = [
    'coinsnap' => CoinsnapBitcoinLightningPaymentMethodHandler::class,
    'btcpay-btc' => BTCPayBitcoinPaymentMethodHandler::class,
    'btcpay-ln' => BTCPayLightningPaymentMethodHandler::class,
];

foreach ($handlers as $name => $class) {
    $post = run($class, transaction());
    $body = $post['body'];
    $meta = $body['metadata'] ?? [];

    check("$name uri", $name === 'coinsnap' ? '/api/v1/stores/cs-store/invoices' : '/api/v1/stores/bp-store/invoices', $post['uri']);
    check("$name amount", 47.4, $body['amount'] ?? null);
    check("$name currency", 'EUR', $body['currency'] ?? null);
    check("$name top-level orderId", '10042', $body['orderId'] ?? null);
    check("$name top-level buyerEmail", 'ada@example.com', $body['buyerEmail'] ?? null);

    // The webhook matches on these two; their values must not change.
    check("$name metadata.transactionId", '0190aaaabbbbccccddddeeeeffff0002', $meta['transactionId'] ?? null);
    check("$name metadata.orderNumber", '10042', $meta['orderNumber'] ?? null);

    check("$name metadata.orderId", '10042', $meta['orderId'] ?? null);
    check("$name metadata.shopwareOrderId", '0190aaaabbbbccccddddeeeeffff0001', $meta['shopwareOrderId'] ?? null);
    check("$name metadata.orderUrl", 'https://shop.example/admin#/sw/order/detail/0190aaaabbbbccccddddeeeeffff0001', $meta['orderUrl'] ?? null);
    check("$name metadata.platform", 'shopware', $meta['platform'] ?? null);
    check("$name metadata.pluginVersion", true, is_string($meta['pluginVersion'] ?? null) && $meta['pluginVersion'] !== '');
    check("$name metadata.customerName", 'Ada Lovelace', $meta['customerName'] ?? null);
    check("$name metadata.buyerName", 'Ada Lovelace', $meta['buyerName'] ?? null);
    check("$name metadata.buyerEmail", 'ada@example.com', $meta['buyerEmail'] ?? null);
    check("$name metadata.buyerPhone", '+49 30 1234', $meta['buyerPhone'] ?? null);
    check("$name metadata.buyerAddress1", 'Main St 1', $meta['buyerAddress1'] ?? null);
    check("$name metadata.buyerAddress2", 'Floor 2', $meta['buyerAddress2'] ?? null);
    check("$name metadata.buyerCity", 'Berlin', $meta['buyerCity'] ?? null);
    check("$name metadata.buyerZip", '10115', $meta['buyerZip'] ?? null);
    check("$name metadata.buyerState", 'DE-BE', $meta['buyerState'] ?? null);
    check("$name metadata.buyerCountry", 'DE', $meta['buyerCountry'] ?? null);
    check("$name metadata.buyerMessage", 'Please ring twice', $meta['buyerMessage'] ?? null);
    check("$name metadata.itemDesc", '2 x Coffee mug, 1 x T-shirt', $meta['itemDesc'] ?? null);
    check("$name metadata.itemCode", 'MUG-1, TS-9', $meta['itemCode'] ?? null);
    check("$name metadata.lineItems", [
        ['name' => 'Coffee mug', 'sku' => 'MUG-1', 'productId' => 'prod-li1', 'type' => 'product', 'quantity' => 2, 'price' => 12.5, 'total' => 25],
        ['name' => 'T-shirt', 'sku' => 'TS-9', 'productId' => 'prod-li2', 'type' => 'product', 'quantity' => 1, 'price' => 25, 'total' => 25],
    ], $meta['lineItems'] ?? null);
    check("$name metadata.couponCodes", 'AUTUMN10', $meta['couponCodes'] ?? null);
    check("$name metadata.discountTotal", 7.5, $meta['discountTotal'] ?? null);
    check("$name metadata.shippingMethod", 'Standard', $meta['shippingMethod'] ?? null);
    check("$name metadata.shippingTotal", 4.9, $meta['shippingTotal'] ?? null);
    check("$name metadata.taxTotal", 7.4, $meta['taxTotal'] ?? null);
    check("$name metadata.language", 'de-DE', $meta['language'] ?? null);

    $empty = array_keys(array_filter($meta, static fn ($v) => $v === null || $v === '' || $v === []));
    check("$name no empty metadata values", [], $empty);
}

// Guest data missing: no empty keys, orderId still the order number.
$post = run(CoinsnapBitcoinLightningPaymentMethodHandler::class, transaction(false));
$meta = $post['body']['metadata'];
check('minimal orderId', '10042', $post['body']['orderId'] ?? null);
check('minimal no buyerEmail', false, array_key_exists('buyerEmail', $post['body']));
check('minimal no buyer keys', [], array_values(array_filter(array_keys($meta), static fn ($k) => str_starts_with($k, 'buyer') || $k === 'customerName')));
check('minimal no empty values', [], array_keys(array_filter($meta, static fn ($v) => $v === null || $v === '' || $v === [])));
check('minimal matching keys', ['0190aaaabbbbccccddddeeeeffff0002', '10042'], [$meta['transactionId'] ?? null, $meta['orderNumber'] ?? null]);

echo ($checks - $failures) . "/{$checks} checks passed\n";
exit($failures === 0 ? 0 : 1);
