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

namespace FlizPay\Payment\Model\Order\Creditmemo\Total;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Reconciles a full offline credit memo to the amount FLIZpay captured.
 */
class Cashback extends AbstractTotal
{
    public function collect(Creditmemo $creditmemo): self
    {
        $order = $creditmemo->getOrder();
        if (
            $order->getPayment()->getMethod() !==
                ConfigInterface::METHOD_CODE ||
            (int) $creditmemo->getData("flizpay_full_refund") === 1
        ) {
            return $this;
        }

        $creditmemo->setData(
            "flizpay_cashback_amount",
            (float) $order->getData("flizpay_cashback_amount"),
        );
        $creditmemo->setData(
            "base_flizpay_cashback_amount",
            (float) $order->getData("base_flizpay_cashback_amount"),
        );
        $creditmemo->setData("flizpay_full_refund", 1);

        $target = round(
            (float) $order->getTotalPaid() - (float) $order->getTotalRefunded(),
            2,
        );
        $baseTarget = round(
            (float) $order->getBaseTotalPaid() -
                (float) $order->getBaseTotalRefunded(),
            2,
        );
        $adjustment = max(
            0.0,
            round((float) $creditmemo->getGrandTotal() - $target, 2),
        );
        $baseAdjustment = max(
            0.0,
            round((float) $creditmemo->getBaseGrandTotal() - $baseTarget, 2),
        );

        if ($adjustment > 0 || $baseAdjustment > 0) {
            $creditmemo->setDiscountAmount(
                round(
                    (float) $creditmemo->getDiscountAmount() - $adjustment,
                    2,
                ),
            );
            $creditmemo->setBaseDiscountAmount(
                round(
                    (float) $creditmemo->getBaseDiscountAmount() -
                        $baseAdjustment,
                    2,
                ),
            );
            $creditmemo->setGrandTotal(
                round((float) $creditmemo->getGrandTotal() - $adjustment, 2),
            );
            $creditmemo->setBaseGrandTotal(
                round(
                    (float) $creditmemo->getBaseGrandTotal() - $baseAdjustment,
                    2,
                ),
            );
        }

        return $this;
    }
}
