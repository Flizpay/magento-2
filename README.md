# FlizPay Payment for Magento

Native FlizPay payment integration for Magento Open Source and Adobe Commerce.

The module is under active development and is not ready for production use.

## Compatibility

- Magento Open Source and Adobe Commerce 2.4.7, 2.4.8, and 2.4.9
- PHP 8.2, 8.3, 8.4, and 8.5 where supported by the selected Magento release
- Core Luma and Blank checkout

## Local Installation

The sibling `magento-store` environment mounts this repository at
`/var/www/flizpay-magento` and declares it as a Composer path repository.

Start the shop and install the module from `magento-store`:

```bash
bin/start
bin/composer require flizpay/magento2:@dev
bin/magento module:enable FlizPay_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Composer installs the package with a symlink, so module changes are available in
the Magento container without reinstalling the package.

## Current Payment Behavior

FlizPay is disabled by default. Configure it under **Stores > Configuration >
Sales > Payment Methods > FlizPay**. The method is available only when:

- It is enabled for the current website.
- The global API key is configured.
- The merchant connection has passed a signed webhook test.
- The store's secure base URL uses HTTPS.

The API key and webhook secret use Magento's encrypted configuration backend.
Neither credential is included in checkout configuration.

Saving an API key automatically registers the secure `/flizpay/webhook` URL with
FlizPay and stores the generated webhook secret encrypted. FlizPay then sends a
signed test callback. Magento answers with `data.alive: true`, records the
verification time, and updates the connection status in Admin.

Order placement persists guest and authenticated orders in `pending_payment`.
The initializer does not contact FlizPay, authorize or capture payment, or create
an invoice. Checkout then submits a form POST to `/flizpay/payment/start`.

The module creates one provider transaction from the persisted order and redirects
the customer to the hosted checkout with an HTTP 303 response. Valid signed
`pending` and `processing` webhooks keep the order awaiting payment. A
`completed` webhook registers the capture, creates a paid invoice, and moves the
order to `processing`. Signed `failed` and `canceled` webhooks cancel the order
and reactivate its quote for a fresh checkout order. Browser returns never
decide provider state or settle payment; a terminal failure return only restores
the already-reactivated quote to the checkout session.

The current provider API does not implement transaction-creation idempotency.
The module therefore makes one creation call without automatic retries. Safe
retry, repeated-start, and concurrent-webhook behavior remain post-MVP work.

## Development Checks

Run `make` from this repository to list the available development commands and
their descriptions. Invoke a command by its target name:

```bash
make validate
make lint
make analyse
make test-unit
make test-integration
make phpcs
make format
make compile
```

The unit, integration, coding-standard, and compilation targets require the
sibling Magento Docker environment to be running. Magento integration-test
infrastructure must be initialized before running `make test-integration`.

## Lifecycle Smoke Test

```bash
../magento-store/bin/magento module:disable FlizPay_Payment
../magento-store/bin/composer remove flizpay/magento2
../magento-store/bin/composer require flizpay/magento2:@dev
../magento-store/bin/magento module:enable FlizPay_Payment
../magento-store/bin/magento setup:upgrade
```

The module currently owns the `flizpay_payment_attempt` and
`flizpay_webhook_event` declarative-schema tables. Full uninstall and data
retention verification is scheduled for the release lifecycle phase.
