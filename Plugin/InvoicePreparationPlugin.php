<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay.
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 * @link    https://flizpay.de
 */

declare(strict_types=1);

namespace FlizPay\Payment\Plugin;

use FlizPay\Payment\Service\Payment\FullInvoiceValidator;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * Validates FLIZpay invoices before Magento constructs and collects them.
 */
class InvoicePreparationPlugin
{
    public function __construct(
        private readonly FullInvoiceValidator $validator,
    ) {}

    /**
     * @param InvoiceService $subject
     * @param Order $order
     * @param array<int|string, int|float|string> $quantities
     * @return array{Order, array<int|string, int|float|string>}
     */
    public function beforePrepareInvoice(
        InvoiceService $subject,
        Order $order,
        array $quantities = [],
    ): array {
        $this->validator->validate($order, $quantities);

        return [$order, $quantities];
    }
}
