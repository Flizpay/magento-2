<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class CashbackAdjustmentService
{
    public function apply(
        Order $order,
        int $originalAmountMinor,
        int $finalAmountMinor,
    ): void {
        $cashbackMinor = $originalAmountMinor - $finalAmountMinor;

        if ($cashbackMinor < 0) {
            throw new \InvalidArgumentException(
                "FLIZpay final amount exceeds the original amount.",
            );
        }

        if ($cashbackMinor === 0) {
            return;
        }

        $originalAmount = $originalAmountMinor / 100;
        $finalAmount = $finalAmountMinor / 100;
        $cashbackAmount = $cashbackMinor / 100;

        $order->setData("flizpay_original_grand_total", $originalAmount);
        $order->setData("base_flizpay_original_grand_total", $originalAmount);
        $order->setData("flizpay_cashback_amount", $cashbackAmount);
        $order->setData("base_flizpay_cashback_amount", $cashbackAmount);
        $order->setDiscountAmount(
            (float) $order->getDiscountAmount() - $cashbackAmount,
        );
        $order->setBaseDiscountAmount(
            (float) $order->getBaseDiscountAmount() - $cashbackAmount,
        );
        $order->setGrandTotal($finalAmount);
        $order->setBaseGrandTotal($finalAmount);

        $payment = $order->getPayment();

        if ($payment instanceof Payment) {
            $payment->setAmountOrdered($finalAmount);
            $payment->setBaseAmountOrdered($finalAmount);
        }
    }
}
