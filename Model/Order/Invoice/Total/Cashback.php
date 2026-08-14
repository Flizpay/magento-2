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

namespace FlizPay\Payment\Model\Order\Invoice\Total;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Cashback extends AbstractTotal
{
    public function collect(Invoice $invoice): self
    {
        $order = $invoice->getOrder();
        $itemsToInvoice = $invoice->getAllItems();

        if (!$itemsToInvoice) {
            return $this;
        }

        $cashback = (float) $order->getData("flizpay_cashback_amount");
        $baseCashback = (float) $order->getData("base_flizpay_cashback_amount");

        if ($cashback <= 0 && $baseCashback <= 0) {
            return $this;
        }

        if (
            (float) $invoice->getData("flizpay_cashback_amount") > 0 ||
            (float) $invoice->getData("base_flizpay_cashback_amount") > 0
        ) {
            return $this;
        }

        $shippingCashback = (float) $order->getData(
            "flizpay_shipping_cashback_amount",
        );
        $baseShippingCashback = (float) $order->getData(
            "base_flizpay_shipping_cashback_amount",
        );
        $taxReduction = max(
            0.0,
            $this->originalTax($order, false) - (float) $order->getTaxAmount(),
        );
        $baseTaxReduction = max(
            0.0,
            $this->originalTax($order, true) -
                (float) $order->getBaseTaxAmount(),
        );
        $invoiceCashback = max(
            0.0,
            $cashback - $shippingCashback - $taxReduction,
        );
        $baseInvoiceCashback = max(
            0.0,
            $baseCashback - $baseShippingCashback - $baseTaxReduction,
        );

        $invoice->setData("flizpay_cashback_amount", $cashback);
        $invoice->setData("base_flizpay_cashback_amount", $baseCashback);

        if ($invoiceCashback <= 0 && $baseInvoiceCashback <= 0) {
            return $this;
        }

        $invoice->setDiscountAmount(
            round((float) $invoice->getDiscountAmount() - $invoiceCashback, 2),
        );
        $invoice->setBaseDiscountAmount(
            round(
                (float) $invoice->getBaseDiscountAmount() -
                    $baseInvoiceCashback,
                2,
            ),
        );
        $invoice->setGrandTotal(
            round((float) $invoice->getGrandTotal() - $invoiceCashback, 2),
        );
        $invoice->setBaseGrandTotal(
            round(
                (float) $invoice->getBaseGrandTotal() - $baseInvoiceCashback,
                2,
            ),
        );

        return $this;
    }

    private function originalTax(Order $order, bool $base): float
    {
        $itemTax = array_sum(
            array_map(
                fn(Item $item): float => (float) ($base
                    ? $item->getBaseTaxAmount()
                    : $item->getTaxAmount()) +
                    $this->itemTaxReduction($item, $base),
                $order->getAllVisibleItems(),
            ),
        );
        $adjustedShippingTax = (float) ($base
            ? $order->getBaseShippingTaxAmount()
            : $order->getShippingTaxAmount());
        $shippingCashback = (float) $order->getData(
            $base
                ? "base_flizpay_shipping_cashback_amount"
                : "flizpay_shipping_cashback_amount",
        );
        $shippingTaxReduction = round(
            $shippingCashback -
                $shippingCashback /
                    (1 + $this->shippingTaxRate($order, $base) / 100),
            2,
        );

        return $itemTax + $adjustedShippingTax + $shippingTaxReduction;
    }

    private function shippingTaxRate(Order $order, bool $base): float
    {
        $shippingNet = (float) ($base
            ? $order->getBaseShippingAmount()
            : $order->getShippingAmount());
        $originalShippingTax =
            (float) ($base
                ? $order->getBaseShippingInclTax()
                : $order->getShippingInclTax()) - $shippingNet;

        return $shippingNet > 0
            ? max(0.0, ($originalShippingTax / $shippingNet) * 100)
            : 0.0;
    }

    private function itemTaxReduction(Item $item, bool $base): float
    {
        $cashback = (float) $item->getData(
            $base ? "base_flizpay_cashback_amount" : "flizpay_cashback_amount",
        );
        $rate = max(0.0, (float) $item->getTaxPercent());

        return round($cashback - $cashback / (1 + $rate / 100), 2);
    }
}
