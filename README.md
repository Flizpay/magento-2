# FLIZpay for Magento 2

Accept FLIZpay payments in Magento Open Source and Adobe Commerce. The extension
redirects customers to the hosted FLIZpay checkout and displays available
cashback directly in the Magento checkout.

## Features

- Hosted FLIZpay checkout for guest and registered customers
- Customer cashback shown in checkout and recorded in Magento totals and tax
- Automatic webhook registration and connection verification
- Signed webhooks as the source of truth for payment settlement
- Encrypted API credentials and a dedicated, privacy-safe log
- Idempotent transaction creation to prevent duplicate payment attempts

## Requirements

- Magento Open Source or Adobe Commerce 2.4.7, 2.4.8, or 2.4.9
- PHP 8.2, 8.3, 8.4, or 8.5 where supported by the selected Magento release
- An HTTPS storefront
- EUR as the quote currency
- Magento Luma or Blank checkout
- A FLIZpay API key

## Installation

Run the following commands from the Magento root directory:

```bash
composer require flizpay/magento2
bin/magento module:enable FlizPay_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

1. Open **Stores > Configuration > Sales > Payment Methods > FLIZpay**.
2. Enable the payment method and enter your FLIZpay API key.
3. Save the configuration.
4. Wait for the connection status to confirm the signed webhook test.

Saving the API key registers the store's secure `/flizpay/webhook` endpoint with
FLIZpay. The payment method becomes available after the connection is verified.
Its logo, subtitle, and cashback title can be configured per store view.

## Payment Behavior

After an order is placed, the customer is redirected to the hosted FLIZpay
checkout. Magento keeps the order in `pending_payment` until a signed webhook is
received. A completed payment creates a paid invoice and moves the order to
`processing`; a failed or canceled payment cancels the order and restores the
cart. Browser redirects never settle a payment.

The current release supports:

- One full invoice per order
- One full offline credit memo after payment completion
- No partial or multiple invoices or credit memos
- No online refunds; refunds must be issued separately through FLIZpay

## Troubleshooting

The extension writes warnings and errors to `var/log/flizpay.log`. Enable debug
logging in the FLIZpay payment configuration to include API and webhook
processing details. Logs exclude credentials, signatures, customer data, and
request or response bodies.

## Development

Run `make` to list all available commands. Common checks are:

```bash
make check              # Validation, XML checks, linting, and unit tests
make test-integration   # Magento integration tests
make analyse            # PHPStan
make phpcs              # Magento coding standards
```

Test and quality commands require a Magento installation. Set `MAGENTO_ROOT` if
it is not available at the default `../magento-store` path.

## License

This extension is dual-licensed under the
[Open Software License 3.0](LICENSE.txt) and the
[Academic Free License 3.0](LICENSE_AFL.txt).
