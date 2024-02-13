![Image of shopwarebtcpay](https://coinsnap.io/wp-content/uploads/2023/11/Coinsnap-for-Shopware.png)

# Coinsnap for Shopware Payment Plugin

**Accept Bitcoin and Lightning payment in Shopware with Coinsnap**

Coinsnap is a Lightning Payment Provider and offers Bitcoin and Lightning payment processing for retail stores and online stores.
As a merchant, you only need a Lightning wallet with a Lightning address to receive Bitcoin and Lightning payments from your customers.

== Description ==

If you run a Shopware-based online store, integrating Bitcoin and Lightning payment options is easy with the Coinsnap Shopware plugin.

Simply install the Coinsnap Shopware plugin on Shopware version 6 or higher, link it to your Coinsnap account, and your customers will have the option to pay with Bitcoin and Lightning.

All incoming Bitcoin payments are immediately forwarded and added to your Lightning Wallet. The Coinsnap Shopware plugin, developed by Coincharge, is compatible with both BTCPay Server and Coinsnap for connectivity.

== Support ==

- Installation instructions in English and German: https://coinsnap.io/en/coinsnap-for-shopware-payment-plugin/
- Demo Store: https://shopware.coincharge.io/en/
- Telegram Support: https://t.me/coinsnap_io

## Installation of the Shopware plugin via Github ##

Here at the Github page you will find all the payment modules provided by Coinsnap. On Coinsnap for Shopware you will find the green button labeled Code and if you click on it, the menu opens and Download ZIP appears. Here you can download the latest version of the Coinsnap plugin to your computer.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/github-coinsnap.jpg)

### Connect Coinsnap account with Shopware plugin ###

As soon as a Coinsnap account has been set up, we can start connecting Shopware to Coinsnap. The BTCPayShopware extension is available in the “My extensions” area.

#### (1) Determination of the configuration process ####
Click on the three dots on the right-hand side to start the configuration process.

#### (2) Initialization of the configuration ####
Click on Configure to start the configuration process.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/Photo2-12.35.49.png)

The Coinsnap API Key and the Coinsnap Store ID must be stored in the configuration area.

Go to the Settings menu item in the Coinsnap backend. There you will find the Coinsnap Store ID and the Coinsnap API Key in the Store Settings section.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/coinsnap-store.png)

Then enter the Coinsnap API key and the Coinsnap store ID that you copied from the Coinsnap backend into Shopware.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/coinsnap-for-shopware-1.jpg)

### Shopware payment methods settings ###

In the Settings section, you can navigate to the payment methods to change the notes for the various payment methods. Access is via the Settings menu, which also contains the Payment methods section.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/payment-methods.png)

In addition to the standard payment options, Bitcoin and Lightning payment methods are also listed here, which are highlighted in blue when activated. You have the option of displaying the Bitcoin and Lightning payment methods separately or as a joint payment method. We recommend using it as a common payment method.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/pay-with-bitcoin.png)
![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/pay-with-lightning.png)

The end customer is then shown a QR code containing both Bitcoin and Lightning.
Regardless of whether the user uses a Lightning or a Bitcoin wallet, payment is possible and unwanted terminations can be avoided.

![](https://github.com/Coinsnap/coinsnap-for-Shopware/blob/master/assets/editing.png)

Individual configurations can be made using the “Edit details” option. This can be used to change the display for the payer, e.g. to “Bitcoin” in our example.

The order of the payment methods displayed, the descriptive text and the associated logo can also be customized. It is recommended to activate the function “Allow change of payment method after completion of the order”.

If someone chooses Bitcoin as a payment method but later decides against it, they can easily switch to another option.
