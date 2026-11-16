# 1.2.0
Security release.

- Decide payment from the invoice the Coinsnap or BTCPay API returns, never from the webhook body. A signed webhook can no longer mark an order paid on its own.
- Mark an order paid only when the invoice is settled for the order's amount and currency, and was created by this plugin for that order. BTCPay invoices settled by hand ("Marked") are not accepted.
- Mark each invoice paid only once, using a new `coinsnap_processed_invoice` table.
- Never move a paid, refunded, cancelled or charged-back order back with a webhook. Expired and Invalid only affect unpaid orders.
- Verify Coinsnap webhooks with the timestamped `X-Coinsnap-Signature` header and reject ones older than 5 minutes.
- Reply 200 to test webhooks and unknown invoices, and 503 when the payment API is unreachable, so the provider retries.
- Keep the current webhook and secret when the API can't be reached during "Test connection", instead of creating a new one.
- BTCPay connect: read the key's permissions from BTCPay and accept only a key limited to one store with the permissions the plugin needs.
- Log only the method, path and status of API calls, at INFO level and above. API responses, invoices and customer data are no longer written to the log.
- Log every rejected webhook with a fixed reason (bad signature, stale timestamp, forged status, amount mismatch), at most 10 lines a minute.
- Hide the webhook secrets from the settings page and return generic connection errors.

# 1.1.1
- Fix plugin update failing on Shopware 6.7 with "SVG files with active content are not allowed" by cleaning the payment icons to Shopware's passive-SVG allowlist (remove DOCTYPE declarations, editor metadata and namespaces, and unused filter data). Icon rendering is unchanged.

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
