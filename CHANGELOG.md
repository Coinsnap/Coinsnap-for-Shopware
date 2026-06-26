# 1.1.0
- Add compatibility with Shopware 6.7 (requires Shopware ~6.7.0)
- Rewrite the payment handler to the new AbstractPaymentHandler API
- Reintroduce BTCPay Server support alongside Coinsnap (both providers can be configured at once)
- Add BTCPay Bitcoin and Lightning payment methods
- Add a BTCPay configuration card with API-key authorization and Test Connection flow
- Route incoming webhooks to Coinsnap or BTCPay based on the signature header
- Register the BTCPay payment methods on upgrade from earlier versions
- Fix invoice creation failing when the gateway redirect URL exceeded 255 characters, by storing the Shopware return URL and handing the gateway a short proxy URL
- Fix connection-page status switches not refreshing after a successful Test Connection
- Verify webhook signatures against the raw request body using a timing-safe comparison, and harden webhook routing and replay handling
- Replace removed EntityRepositoryInterface with EntityRepository and switch route attributes to the Symfony 7 Attribute\Route namespace
- Clean up dead imports and tighten type declarations

# 1.0.4
- Updated logger
- Added necessary checks for Webhook endpoint

# 1.0.3

- Replace deprecated methods for registering routes
- Fix problem with handling payment exceptions
- Drop BTCPay support

# 1.0.2

- Fix problem with gateway connection

# 1.0.1

- Announcement of Dropping Support for BTCPay Server

# 1.0.0

- First version
