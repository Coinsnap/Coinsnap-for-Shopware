# 2.0.0
- Add compatibility with Shopware 6.7 (requires Shopware ~6.7.0)
- Rewrite the payment handler to the new AbstractPaymentHandler API
- Replace removed EntityRepositoryInterface with EntityRepository
- Switch route attributes to the Symfony 7 Attribute\Route namespace
- Re-link the payment method on upgrade from earlier versions
- Verify webhook signatures against the raw request body using a timing-safe comparison
- Harden the webhook against missing order metadata and unknown events
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
