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
bin/magento module:enable Flizpay_Payment
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
- The store's secure base URL uses HTTPS.

The API key uses Magento's encrypted configuration backend. It is never included
in checkout configuration.

Order placement persists guest and authenticated orders in `pending_payment`.
The initializer does not contact FlizPay, authorize or capture payment, or create
an invoice. Checkout then submits a form POST to `/flizpay/payment/start`.

Provider transaction creation is added in Phase 3. Until then, the start action
continues to Magento's order-success page without changing payment state.

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
../magento-store/bin/magento module:disable Flizpay_Payment
../magento-store/bin/composer remove flizpay/magento2
../magento-store/bin/composer require flizpay/magento2:@dev
../magento-store/bin/magento module:enable Flizpay_Payment
../magento-store/bin/magento setup:upgrade
```

The module currently owns the `flizpay_payment_attempt` and
`flizpay_webhook_event` declarative-schema tables. Full uninstall and data
retention verification is scheduled for the release lifecycle phase.
