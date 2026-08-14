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

/**
 * Covers capture notifications that use Order::prepareInvoice().
 */
class OrderInvoicePreparationPlugin
{
    public function __construct(
        private readonly FullInvoiceValidator $validator,
    ) {}

    /**
     * @param Order $subject
     * @param array<int|string, int|float|string> $quantities
     * @return array{array<int|string, int|float|string>}
     */
    public function beforePrepareInvoice(
        Order $subject,
        array $quantities = [],
    ): array {
        $this->validator->validate($subject, $quantities);

        return [$quantities];
    }
}
