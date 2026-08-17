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

namespace FlizPay\Payment\Service\Refund;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item;

/**
 * Enforces one full offline credit memo for completed FLIZpay payments.
 */
class FullOfflineCreditmemoValidator
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
    ) {}

    /**
     * Validate factory input before Magento collects credit memo totals.
     *
     * @param Order $order
     * @param array<string, mixed> $data
     * @return void
     * @throws LocalizedException
     */
    public function validatePreparation(Order $order, array $data = []): void
    {
        if (!$this->isFlizpay($order)) {
            return;
        }

        $this->validateEligibility($order);
        $this->validateQuantities($order, $data["qtys"] ?? []);
        $this->validateAdjustments($data);

        if (array_key_exists("shipping_amount", $data)) {
            $remainingShipping = round(
                (float) $order->getShippingInvoiced() -
                    (float) $order->getShippingRefunded(),
                2,
            );
            if (
                abs((float) $data["shipping_amount"] - $remainingShipping) >
                0.01
            ) {
                $this->rejectFullOnly();
            }
        }
    }

    /**
     * Validate a collected memo at the final service boundary.
     *
     * @param Creditmemo $creditmemo
     * @param bool $offlineRequested
     * @return void
     * @throws LocalizedException
     */
    public function validateRefund(
        Creditmemo $creditmemo,
        bool $offlineRequested,
    ): void {
        $order = $creditmemo->getOrder();
        if (!$this->isFlizpay($order)) {
            return;
        }

        if (!$offlineRequested) {
            throw new LocalizedException(
                __(
                    "FLIZpay online refunds are not supported. Choose Refund Offline.",
                ),
            );
        }

        $this->validateEligibility($order);
        $this->validateCreditmemoQuantities($creditmemo);

        if (
            abs((float) $creditmemo->getAdjustmentPositive()) > 0.0001 ||
            abs((float) $creditmemo->getAdjustmentNegative()) > 0.0001
        ) {
            throw new LocalizedException(
                __("FLIZpay credit memo adjustments are not supported."),
            );
        }

        $target = round(
            (float) $order->getTotalPaid() - (float) $order->getTotalRefunded(),
            2,
        );
        $baseTarget = round(
            (float) $order->getBaseTotalPaid() -
                (float) $order->getBaseTotalRefunded(),
            2,
        );
        if (
            abs((float) $creditmemo->getGrandTotal() - $target) > 0.01 ||
            abs((float) $creditmemo->getBaseGrandTotal() - $baseTarget) >
                0.01 ||
            (int) $creditmemo->getData("flizpay_full_refund") !== 1
        ) {
            $this->rejectFullOnly();
        }
    }

    private function validateEligibility(Order $order): void
    {
        try {
            $attempt = $this->attemptRepository->getByOrderId(
                (int) $order->getId(),
            );
        } catch (\Throwable) {
            throw new LocalizedException(
                __(
                    "A FLIZpay credit memo can be created only after payment completion.",
                ),
            );
        }

        if ((string) $attempt->getData("provider_status") !== "completed") {
            throw new LocalizedException(
                __(
                    "A FLIZpay credit memo can be created only after payment completion.",
                ),
            );
        }

        $invoices = $order->getInvoiceCollection();
        $invoices->clear();
        if ($invoices->getSize() !== 1) {
            $this->rejectFullOnly();
        }

        $invoice = $invoices->getFirstItem();
        if (
            !$invoice instanceof Invoice ||
            (int) $invoice->getState() !== Invoice::STATE_PAID ||
            abs(
                (float) $invoice->getGrandTotal() -
                    (float) $order->getTotalPaid(),
            ) > 0.01 ||
            (string) $invoice->getTransactionId() !==
                (string) $attempt->getData("provider_transaction_id")
        ) {
            $this->rejectFullOnly();
        }

        $creditmemos = $order->getCreditmemosCollection();
        $creditmemos->clear();
        if (
            $creditmemos->getSize() !== 0 ||
            (float) $order->getTotalRefunded() > 0 ||
            (float) $order->getPayment()->getAmountRefunded() > 0
        ) {
            throw new LocalizedException(
                __("This FLIZpay order has already been refunded."),
            );
        }
    }

    /**
     * @param Order $order
     * @param array<int|string, int|float|string> $quantities
     * @return void
     */
    private function validateQuantities(Order $order, array $quantities): void
    {
        if ($quantities === []) {
            return;
        }

        foreach ($order->getAllItems() as $item) {
            if ($item->isDummy()) {
                continue;
            }

            $remaining =
                (float) $item->getQtyInvoiced() -
                (float) $item->getQtyRefunded();
            if ($remaining <= 0) {
                continue;
            }

            if (
                abs($this->requestedQuantity($item, $quantities) - $remaining) >
                0.0001
            ) {
                $this->rejectFullOnly();
            }
        }
    }

    private function validateCreditmemoQuantities(Creditmemo $creditmemo): void
    {
        $refunded = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $refunded[(int) $item->getOrderItemId()] = (float) $item->getQty();
        }

        foreach ($creditmemo->getOrder()->getAllItems() as $item) {
            if ($item->isDummy()) {
                continue;
            }

            $remaining =
                (float) $item->getQtyInvoiced() -
                (float) $item->getQtyRefunded();
            if (
                $remaining > 0 &&
                abs(($refunded[(int) $item->getId()] ?? 0.0) - $remaining) >
                    0.0001
            ) {
                $this->rejectFullOnly();
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function validateAdjustments(array $data): void
    {
        foreach (["adjustment_positive", "adjustment_negative"] as $field) {
            if (abs((float) ($data[$field] ?? 0)) > 0.0001) {
                throw new LocalizedException(
                    __("FLIZpay credit memo adjustments are not supported."),
                );
            }
        }
    }

    /**
     * @param Item $item
     * @param array<int|string, int|float|string> $quantities
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

    private function isFlizpay(Order $order): bool
    {
        return $order->getPayment()->getMethod() ===
            ConfigInterface::METHOD_CODE;
    }

    private function rejectFullOnly(): never
    {
        throw new LocalizedException(
            __("FLIZpay supports one full offline credit memo only."),
        );
    }
}
