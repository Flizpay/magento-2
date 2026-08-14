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

namespace FlizPay\Payment\Service\Payment;

class CashbackAdjustment
{
    /**
     * @param array<int, array{cashback: float, tax_reduction: float}> $items
     */
    public function __construct(
        public readonly float $originalAmount,
        public readonly float $finalAmount,
        public readonly float $cashbackAmount,
        public readonly array $items,
        public readonly float $shippingCashback,
        public readonly float $shippingTaxReduction,
        public readonly float $taxReduction,
    ) {}
}
