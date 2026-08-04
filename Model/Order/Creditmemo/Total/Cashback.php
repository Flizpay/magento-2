<?php

declare(strict_types=1);

namespace FlizPay\Payment\Model\Order\Creditmemo\Total;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

class Cashback extends AbstractTotal
{
    public function collect(Creditmemo $creditmemo): self
    {
        $order = $creditmemo->getOrder();
        $cashback = (float) $order->getData("flizpay_cashback_amount");
        $baseCashback = (float) $order->getData("base_flizpay_cashback_amount");

        $refunded = (float) $order->getData("flizpay_cashback_refunded");
        $baseRefunded = (float) $order->getData(
            "base_flizpay_cashback_refunded",
        );

        $cashback = max(0.0, $cashback - $refunded);
        $baseCashback = max(0.0, $baseCashback - $baseRefunded);

        $creditmemo->setData("flizpay_cashback_amount", $cashback);
        $creditmemo->setData("base_flizpay_cashback_amount", $baseCashback);

        if ($cashback <= 0 && $baseCashback <= 0) {
            return $this;
        }

        $creditmemo->setDiscountAmount(
            (float) $creditmemo->getDiscountAmount() - $cashback,
        );
        $creditmemo->setBaseDiscountAmount(
            (float) $creditmemo->getBaseDiscountAmount() - $baseCashback,
        );
        $creditmemo->setGrandTotal(
            (float) $creditmemo->getGrandTotal() - $cashback,
        );
        $creditmemo->setBaseGrandTotal(
            (float) $creditmemo->getBaseGrandTotal() - $baseCashback,
        );

        return $this;
    }
}
