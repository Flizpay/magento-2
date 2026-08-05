<?php

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
    ) {
    }
}
