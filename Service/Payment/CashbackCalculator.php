<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

class CashbackCalculator
{
    public function calculate(
        Order $order,
        int $originalAmountMinor,
        int $finalAmountMinor,
    ): CashbackAdjustment {
        $cashbackMinor = $originalAmountMinor - $finalAmountMinor;

        if ($cashbackMinor < 0) {
            throw new \InvalidArgumentException(
                "FLIZpay final amount exceeds the original amount.",
            );
        }

        $items = array_values(
            array_filter(
                $order->getAllItems(),
                fn(Item $item): bool => !$item->getParentItemId() &&
                    $this->itemGrossMinor($item) > 0,
            ),
        );
        $shippingGrossMinor = $this->minor(
            max(0.0, (float) $order->getShippingInclTax()),
        );
        $eligibleMinor =
            $shippingGrossMinor +
            array_sum(
                array_map(
                    fn(Item $item): int => $this->itemGrossMinor($item),
                    $items,
                ),
            );

        if ($eligibleMinor <= 0 || $cashbackMinor > $eligibleMinor) {
            throw new \InvalidArgumentException(
                "FLIZpay cashback cannot be allocated to the order.",
            );
        }

        $remainingCashback = $cashbackMinor;
        $remainingGross = $eligibleMinor;
        $allocations = [];
        $taxReduction = 0.0;

        foreach ($items as $item) {
            $grossMinor = $this->itemGrossMinor($item);
            $allocatedMinor =
                $shippingGrossMinor === 0 && $item === end($items)
                    ? $remainingCashback
                    : intdiv($remainingCashback * $grossMinor, $remainingGross);
            $cashback = $allocatedMinor / 100;
            $itemTaxReduction = $this->taxPart(
                $cashback,
                max(0.0, (float) $item->getTaxPercent()),
            );
            $allocations[(int) $item->getId()] = [
                "cashback" => $cashback,
                "tax_reduction" => $itemTaxReduction,
            ];
            $taxReduction += $itemTaxReduction;
            $remainingCashback -= $allocatedMinor;
            $remainingGross -= $grossMinor;
        }

        $shippingCashback = $remainingCashback / 100;
        $shippingTaxReduction = $this->taxPart(
            $shippingCashback,
            $this->shippingTaxRate($order),
        );

        return new CashbackAdjustment(
            $originalAmountMinor / 100,
            $finalAmountMinor / 100,
            $cashbackMinor / 100,
            $allocations,
            $shippingCashback,
            $shippingTaxReduction,
            round($taxReduction + $shippingTaxReduction, 4),
        );
    }

    private function shippingTaxRate(Order $order): float
    {
        $shippingNet = max(0.0, (float) $order->getShippingAmount());
        $shippingTax = max(0.0, (float) $order->getShippingTaxAmount());

        return $shippingNet > 0
            ? round(($shippingTax / $shippingNet) * 100, 4)
            : 0.0;
    }

    private function taxPart(float $gross, float $rate): float
    {
        return round($gross - $gross / (1 + $rate / 100), 2);
    }

    private function itemGrossMinor(Item $item): int
    {
        $gross = (float) $item->getRowTotalInclTax();

        return $this->minor(
            $gross > 0
                ? $gross
                : (float) $item->getRowTotal() + (float) $item->getTaxAmount(),
        );
    }

    private function minor(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
