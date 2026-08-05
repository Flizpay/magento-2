<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;

class CashbackAdjustmentService
{
    public function __construct(
        private readonly CashbackCalculator $calculator,
    ) {}

    public function apply(
        Order $order,
        int $originalAmountMinor,
        int $finalAmountMinor,
    ): void {
        if ($originalAmountMinor === $finalAmountMinor) {
            return;
        }

        $adjustment = $this->calculator->calculate(
            $order,
            $originalAmountMinor,
            $finalAmountMinor,
        );
        $baseRate = (float) $order->getBaseToOrderRate();
        $toBase = static fn(float $amount): float => round(
            $baseRate > 0 ? $amount / $baseRate : $amount,
            4,
        );

        foreach ($order->getAllItems() as $item) {
            if (!$item instanceof Item || $item->getParentItemId()) {
                continue;
            }

            $allocation = $adjustment->items[(int) $item->getId()] ?? null;
            if ($allocation === null) {
                continue;
            }

            $item->setData("flizpay_cashback_amount", $allocation["cashback"]);
            $item->setData(
                "base_flizpay_cashback_amount",
                $toBase($allocation["cashback"]),
            );
            $item->setTaxAmount(
                max(
                    0.0,
                    (float) $item->getTaxAmount() -
                        $allocation["tax_reduction"],
                ),
            );
            $item->setBaseTaxAmount(
                max(
                    0.0,
                    (float) $item->getBaseTaxAmount() -
                        $toBase($allocation["tax_reduction"]),
                ),
            );
        }

        $order->setData(
            "flizpay_shipping_cashback_amount",
            $adjustment->shippingCashback,
        );
        $order->setData(
            "base_flizpay_shipping_cashback_amount",
            $toBase($adjustment->shippingCashback),
        );
        $order->setShippingDiscountAmount(
            (float) $order->getShippingDiscountAmount() +
                $adjustment->shippingCashback,
        );
        $order->setBaseShippingDiscountAmount(
            (float) $order->getBaseShippingDiscountAmount() +
                $toBase($adjustment->shippingCashback),
        );
        $order->setShippingTaxAmount(
            max(
                0.0,
                (float) $order->getShippingTaxAmount() -
                    $adjustment->shippingTaxReduction,
            ),
        );
        $order->setBaseShippingTaxAmount(
            max(
                0.0,
                (float) $order->getBaseShippingTaxAmount() -
                    $toBase($adjustment->shippingTaxReduction),
            ),
        );
        $order->setTaxAmount(
            max(
                0.0,
                (float) $order->getTaxAmount() - $adjustment->taxReduction,
            ),
        );
        $order->setBaseTaxAmount(
            max(
                0.0,
                (float) $order->getBaseTaxAmount() -
                    $toBase($adjustment->taxReduction),
            ),
        );

        $order->setData(
            "flizpay_original_grand_total",
            $adjustment->originalAmount,
        );
        $order->setData(
            "base_flizpay_original_grand_total",
            $toBase($adjustment->originalAmount),
        );
        $order->setData("flizpay_cashback_amount", $adjustment->cashbackAmount);
        $order->setData(
            "base_flizpay_cashback_amount",
            $toBase($adjustment->cashbackAmount),
        );
        $order->setDiscountAmount(
            (float) $order->getDiscountAmount() - $adjustment->cashbackAmount,
        );
        $order->setBaseDiscountAmount(
            (float) $order->getBaseDiscountAmount() -
                $toBase($adjustment->cashbackAmount),
        );
        $order->setGrandTotal($adjustment->finalAmount);
        $order->setBaseGrandTotal($toBase($adjustment->finalAmount));

        $payment = $order->getPayment();
        if ($payment instanceof Payment) {
            $payment->setAmountOrdered($adjustment->finalAmount);
            $payment->setBaseAmountOrdered($toBase($adjustment->finalAmount));
        }
    }
}
