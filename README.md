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

The payment method is active after installation but is never offered in
checkout until merchant onboarding is complete. Configure it under
**Stores > Configuration > Sales > Payment Methods > FlizPay**. The method
appears in checkout only when all of the following hold:

- It is enabled for the current website.
- The global API key is configured.
- The merchant connection has passed a signed webhook test.
- The store's secure base URL uses HTTPS.
- The quote currency is EUR.

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

Transaction creation is idempotent. Every payment attempt sends a unique
`Idempotency-Key` header; the provider answers a conflicting reuse with HTTP
409, which the module reports as a distinct creation failure. Repeated start
requests for the same order replay the stored hosted-checkout redirect URL
instead of creating a new transaction, and concurrent webhook deliveries are
serialized with a named lock before any order state is applied. Automatic
retry with backoff after ambiguous creation failures remains post-MVP work.

## Logs

The module writes to a dedicated log file at `var/log/flizpay.log`. Warnings
and errors — failed payment starts, rejected webhooks, connection failures —
are always recorded. Enable **Stores > Configuration > Sales > Payment
Methods > FlizPay > Enable Debug Logging** to also record verbose debug
entries such as webhook processing traces and API request outcomes.

Log entries contain only safe identifiers: order increment IDs, attempt IDs,
exception classes, safe error codes, HTTP statuses, and API paths. They never
contain API credentials, webhook signatures, request or response bodies,
return URLs, or customer data.

## Development Checks

Run `make` from this repository to list the available development commands and
their descriptions.

Package checks run against this repository alone and need only PHP and
Composer:

```bash
make validate       # Validate composer.json
make validate-xml   # Validate every tracked XML file
make lint           # Check PHP syntax
```

Test and quality targets need a Magento installation that contains this module.
Point `MAGENTO_ROOT` at it (default: `../magento-store`) and `MODULE_PATH` at
the module location inside it (default: `app/code/FlizPay/Payment`):

```bash
make test-unit           # Run the unit test suite
make test-integration    # Run the Magento integration test suite
make test                # Both suites
make analyse             # PHPStan
make phpcs               # Magento coding standards
make format              # PHPCBF
make compile             # setup:di:compile
make check               # validate + validate-xml + lint + test-unit
```

When `MAGENTO_ROOT` contains the `bin/clinotty` wrapper (the sibling
`magento-store` Docker environment), commands automatically run inside its PHP
container; that environment must be running, and the Magento integration-test
infrastructure must be initialized before `make test-integration`. Against any
other Magento root, commands run directly on the host:

```bash
make test-unit MAGENTO_ROOT=/path/to/magento
```

## Continuous Integration

GitHub Actions runs on every push to `main` and every pull request targeting
`main` (`.github/workflows/ci.yml`):

- **Package checks** — `make validate`, `make validate-xml`, `make lint`, and a
  standalone package smoke test on PHP 8.2–8.5. Secret-free, so it runs for
  every pull request, including forks.
- **Unit tests** — installs the module with its Magento dependencies from
  `repo.magento.com` into a Composer sandbox and runs the full `Test/Unit`
  suite on PHP 8.3 via `make test-unit`.
- **Integration tests** — provisions Magento Open Source with MySQL,
  OpenSearch, and RabbitMQ service containers, links this module under
  `app/code/FlizPay/Payment`, and runs `Test/Integration` via
  `make test-integration`.

The unit and integration jobs authenticate against `repo.magento.com` with the
`COMPOSER_AUTH` repository secret. Create a dedicated, revocable Adobe
Marketplace access key for CI and store it as:

```json
{
  "http-basic": {
    "repo.magento.com": {
      "username": "<public-key>",
      "password": "<private-key>"
    }
  }
}
```

Pull requests from forks receive no secrets, so the unit and integration jobs
are skipped for them; the package checks still run. For branch protection,
require the package, unit, and integration jobs as status checks on `main`.

## Lifecycle Smoke Test

```bash
../magento-store/bin/magento module:disable FlizPay_Payment
../magento-store/bin/composer remove flizpay/magento2
../magento-store/bin/composer require flizpay/magento2:@dev
../magento-store/bin/magento module:enable FlizPay_Payment
../magento-store/bin/magento setup:upgrade
```

The module owns the `flizpay_payment_attempt` declarative-schema table. Full
uninstall and data retention verification is scheduled for the release
lifecycle phase.

## License

This extension is dual-licensed under the
[Open Software License 3.0 (OSL-3.0)](https://opensource.org/license/osl-3-0-php)
and the
[Academic Free License 3.0 (AFL-3.0)](https://opensource.org/license/afl-3-0-php),
matching the licensing of Magento Open Source. See [LICENSE.txt](LICENSE.txt)
and [LICENSE_AFL.txt](LICENSE_AFL.txt) for the full license texts.
