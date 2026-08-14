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

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * Enforces the single full-invoice policy before Magento collects totals.
 */
class FullInvoiceValidator
{
    /**
     * @param Order $order
     * @param array<int|string, int|float|string> $quantities
     * @return void
     * @throws LocalizedException
     */
    public function validate(Order $order, array $quantities = []): void
    {
        if ($order->getPayment()->getMethod() !== ConfigInterface::METHOD_CODE) {
            return;
        }

        $invoices = $order->getInvoiceCollection();
        $invoices->clear();
        if ($invoices->getSize() !== 0) {
            $this->reject();
        }

        if ($quantities === []) {
            return;
        }

        foreach ($order->getAllItems() as $item) {
            if ($item->isDummy() || (float) $item->getQtyToInvoice() <= 0) {
                continue;
            }

            $requested = $this->requestedQuantity($item, $quantities);
            if (abs($requested - (float) $item->getQtyToInvoice()) > 0.0001) {
                $this->reject();
            }
        }
    }

    /**
     * @param Item $item
     * @param array<int|string, int|float|string> $quantities
     * @return float
     */
    private function requestedQuantity(Item $item, array $quantities): float
    {
        $itemId = $item->getId();
        if ($itemId !== null && array_key_exists($itemId, $quantities)) {
            return (float) $quantities[$itemId];
        }

        $parentId = $item->getParentItemId();
        if ($parentId !== null && array_key_exists($parentId, $quantities)) {
            return (float) $quantities[$parentId];
        }

        return 0.0;
    }

    /**
     * @return never
     * @throws LocalizedException
     */
    private function reject(): never
    {
        throw new LocalizedException(
            __("FLIZpay supports one full invoice only."),
        );
    }
}
