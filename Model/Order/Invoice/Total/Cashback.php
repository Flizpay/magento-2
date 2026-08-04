<?php

declare(strict_types=1);

namespace FlizPay\Payment\Model\Order\Invoice\Total;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Cashback extends AbstractTotal
{
    public function collect(Invoice $invoice): self
    {
        $order = $invoice->getOrder();
        $cashback = (float) $order->getData("flizpay_cashback_amount");
        $baseCashback = (float) $order->getData("base_flizpay_cashback_amount");

        $invoice->setData("flizpay_cashback_amount", $cashback);
        $invoice->setData("base_flizpay_cashback_amount", $baseCashback);

        if ($cashback <= 0 && $baseCashback <= 0) {
            return $this;
        }

        $invoice->setDiscountAmount(
            (float) $invoice->getDiscountAmount() - $cashback,
        );
        $invoice->setBaseDiscountAmount(
            (float) $invoice->getBaseDiscountAmount() - $baseCashback,
        );
        $invoice->setGrandTotal((float) $invoice->getGrandTotal() - $cashback);
        $invoice->setBaseGrandTotal(
            (float) $invoice->getBaseGrandTotal() - $baseCashback,
        );

        return $this;
    }
}
