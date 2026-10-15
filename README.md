# Coinsnap for Shopware

![Coinsnap for Shopware](https://coinsnap.io/wp-content/uploads/2023/11/Coinsnap-for-Shopware.png)

**Bitcoin payment module for Shopware — accept Bitcoin and Lightning with Coinsnap or your own BTCPay Server.**

Coinsnap for Shopware is an open-source Bitcoin payment module that adds Bitcoin payments directly to the Shopware checkout and order flow.

Customers can pay with **Bitcoin via Lightning or on-chain**, while merchants keep their existing Shopware products, taxes, shipping rules, coupons and order workflow.

When using Coinsnap, merchants do not need to operate their own Bitcoin or Lightning payment infrastructure. Coinsnap follows a **non-custodial / self-custody model** and does not hold merchant funds. Payments can be settled to the merchant's configured Bitcoin or Lightning wallet or, through supported off-ramp partners, to a bank account.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments** in Shopware
- Support for **Lightning and Bitcoin on-chain**
- Native integration into the Shopware checkout and order workflow
- Automatic Shopware order updates after payment
- No own Lightning node or Bitcoin payment server required when using Coinsnap
- **Non-custodial / self-custody** payment model
- Settlement to a configured Bitcoin or Lightning wallet
- Optional fiat settlement through supported off-ramp partners
- Support for **BTCPay Server** for merchants who prefer a self-hosted Bitcoin payment infrastructure
- Separate BTCPay Bitcoin and Lightning payment methods
- Secure webhook handling for Coinsnap and BTCPay Server
- Open source under the MIT License

## Requirements

The current plugin release is **1.1.1** and requires:

- **Shopware 6.7**
- A **Coinsnap merchant account**, or
- Your own **BTCPay Server**

For Coinsnap settlement, use a compatible Bitcoin or Lightning wallet. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/), which combines self-custodial wallet functionality with merchant and point-of-sale features.

## How it works

1. Create a [Coinsnap merchant account](https://app.coinsnap.io/register).
2. Install and activate the Coinsnap for Shopware extension.
3. Connect Shopware to Coinsnap using your Store ID and API key.
4. Enable the Bitcoin payment method in the relevant Shopware sales channel.
5. Customers select Bitcoin at checkout and pay via Lightning or Bitcoin on-chain.
6. Coinsnap detects the payment and updates the Shopware order automatically.

## Coinsnap or BTCPay Server

The module supports two payment-provider modes:

- **Coinsnap** — for merchants who want to accept Bitcoin without operating their own payment infrastructure.
- **BTCPay Server** — for merchants who run their own BTCPay Server and want to connect it to Shopware.

BTCPay Server support was reintroduced in version 1.1.0 and includes API-key authorization, connection testing, Bitcoin and Lightning payment methods, and webhook routing.

## Installation

### Install from Shopware Extensions

The recommended installation method is through Shopware Extensions. After installation, activate the extension and open its configuration in the Shopware administration.

Follow the step-by-step [Shopware Bitcoin User Guide](https://www.coinsnap.io/user-guides/shopware-bitcoin-user-guide/) for installation, connection and checkout configuration.

### Install from GitHub

Developers can also download the source code from this repository and install the extension in their Shopware environment.

After installation:

1. Open the Coinsnap extension configuration.
2. Enter your **Coinsnap Store ID** and **API key**.
3. Test the connection.
4. Enable the Coinsnap payment method.
5. Add the payment method to the relevant Shopware sales channel.
6. Perform a test transaction before going live.

## BTCPay Server configuration

If you prefer to use your own BTCPay Server:

1. Enter your BTCPay Server URL in the extension configuration.
2. Authorize the required API key on your BTCPay Server.
3. Test the connection.
4. Enable the available BTCPay Bitcoin and/or Lightning payment methods.
5. Add the desired methods to your Shopware sales channel.

## Resources

- [Coinsnap for Shopware product page](https://coinsnap.io/shop-systems/shopware/)
- [Shopware Bitcoin User Guide](https://www.coinsnap.io/user-guides/shopware-bitcoin-user-guide/)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Coinsnap website](https://coinsnap.io/)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### What are the requirements for accepting Bitcoin in my Shopware store?

For the current plugin release you need a Shopware 6.7 store and either a Coinsnap merchant account or your own BTCPay Server. When using Coinsnap, you do not need to operate your own Lightning node or Bitcoin payment server.

### Can customers pay with both Lightning and Bitcoin on-chain?

Yes. Coinsnap supports Bitcoin payments via both the Lightning Network and Bitcoin on-chain.

### Which Bitcoin or Lightning wallets can I use?

You can use a compatible Bitcoin or Lightning wallet as your settlement wallet. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/), which combines a self-custodial Bitcoin wallet with merchant and point-of-sale functionality.

Other compatible wallets may include solutions such as Wallet of Satoshi, Blink and Zeus, depending on the settlement setup you choose.

### Does Coinsnap hold my Bitcoin?

No. Coinsnap is non-custodial and does not hold or manage merchant funds. Payments are sent to the merchant's configured wallet or, if selected, converted and settled through a supported off-ramp.

### Can I use my own BTCPay Server instead of Coinsnap?

Yes. The Shopware module supports BTCPay Server for merchants who prefer a self-hosted Bitcoin payment infrastructure.

### Do I need technical knowledge to install the module?

No advanced technical knowledge is required for the standard Coinsnap setup. The module can be installed through Shopware Extensions, connected with your Coinsnap credentials and enabled as a Shopware payment method.

### Can I receive Bitcoin sales in my bank account?

Coinsnap supports integrations with off-ramp partners that can convert Bitcoin payments and settle the proceeds to a bank account, subject to the selected provider's availability and conditions.

### Is the module available worldwide?

Coinsnap is designed for merchants worldwide. Bitcoin and Lightning payments can be accepted from customers globally, subject to any legal or service restrictions that may apply in the merchant's jurisdiction.

### Where can I get support?

Use the support area in your Coinsnap merchant account, consult the [Shopware Bitcoin User Guide](https://www.coinsnap.io/user-guides/shopware-bitcoin-user-guide/), or join the official [Coinsnap Telegram channel](https://t.me/coinsnap).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.
