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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * Applies a trusted completed-payment notification to Magento.
 */
class CompletedPaymentHandler
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly CashbackAdjustmentService $cashbackAdjustmentService,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function execute(
        string $providerTransactionId,
        int $originalAmountMinor,
        int $finalAmountMinor,
        string $currency,
        string $externalOrderId,
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $attempt = $this->attemptRepository->getByProviderTransactionId(
                $providerTransactionId,
            );
            $order = $this->orderRepository->get(
                (int) $attempt->getData("order_id"),
            );
            $payment = $order instanceof Order ? $order->getPayment() : null;

            if (
                !$order instanceof Order ||
                !$payment instanceof Payment ||
                $payment->getMethod() !== ConfigInterface::METHOD_CODE
            ) {
                throw new LocalizedException(
                    __("FLIZpay payment binding is invalid."),
                );
            }

            if (
                strtoupper($currency) !== "EUR" ||
                strtoupper((string) $attempt->getData("currency")) !== "EUR" ||
                strtoupper((string) $order->getOrderCurrencyCode()) !== "EUR" ||
                (int) $attempt->getData("expected_amount_minor") !==
                    $originalAmountMinor ||
                (string) $attempt->getData("order_increment_id") !==
                    $externalOrderId ||
                $finalAmountMinor > $originalAmountMinor
            ) {
                throw new LocalizedException(
                    __("FLIZpay payment amount binding is invalid."),
                );
            }

            if (
                ProviderPaymentState::isFailure(
                    (string) $attempt->getData("provider_status"),
                )
            ) {
                throw new LocalizedException(
                    __("FLIZpay payment transition is invalid."),
                );
            }

            $invoices = $order->getInvoiceCollection();
            $invoices->clear();
            if ($invoices->getSize() !== 0) {
                throw new LocalizedException(
                    __("FLIZpay payment has an unexpected existing invoice."),
                );
            }

            if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                throw new LocalizedException(
                    __("FLIZpay order is not awaiting payment."),
                );
            }

            $payment->setTransactionId($providerTransactionId);
            $payment->setData(
                "currency_code",
                (string) $order->getBaseCurrencyCode(),
            );
            $payment->setIsTransactionClosed(true);
            $this->cashbackAdjustmentService->apply(
                $order,
                $originalAmountMinor,
                $finalAmountMinor,
            );
            $order->setState(Order::STATE_PROCESSING);
            $payment->registerCaptureNotification(
                (float) $order->getBaseGrandTotal(),
                true,
            );
            $order->addCommentToStatusHistory(
                (string) __("FLIZpay payment completed."),
            );
            $this->orderRepository->save($order);
            $this->updateTaxBreakdown($order);

            $attempt->setData("provider_status", "completed");
            $this->attemptRepository->save($attempt);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function updateTaxBreakdown(Order $order): void
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTaxTable = $this->resourceConnection->getTableName(
            "sales_order_tax",
        );
        $orderTaxItemTable = $this->resourceConnection->getTableName(
            "sales_order_tax_item",
        );
        $orderId = (int) $order->getId();

        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $connection->update(
                $orderTaxItemTable,
                [
                    "amount" => (float) $item->getTaxAmount(),
                    "base_amount" => (float) $item->getBaseTaxAmount(),
                    "real_amount" => (float) $item->getTaxAmount(),
                    "real_base_amount" => (float) $item->getBaseTaxAmount(),
                ],
                ["item_id = ?" => (int) $item->getId()],
            );
        }

        $connection->update(
            $orderTaxItemTable,
            [
                "amount" => (float) $order->getShippingTaxAmount(),
                "base_amount" => (float) $order->getBaseShippingTaxAmount(),
                "real_amount" => (float) $order->getShippingTaxAmount(),
                "real_base_amount" => (float) $order->getBaseShippingTaxAmount(),
            ],
            [
                "tax_id IN (?)" => $connection
                    ->select()
                    ->from($orderTaxTable, "tax_id")
                    ->where("order_id = ?", $orderId),
                "taxable_item_type = ?" => "shipping",
            ],
        );

        $taxIds = $connection->fetchCol(
            $connection
                ->select()
                ->from($orderTaxTable, "tax_id")
                ->where("order_id = ?", $orderId),
        );
        foreach ($taxIds as $taxId) {
            $totals = $connection->fetchRow(
                $connection
                    ->select()
                    ->from($orderTaxItemTable, [
                        "row_count" => "COUNT(*)",
                        "amount" => "SUM(amount)",
                        "base_amount" => "SUM(base_amount)",
                    ])
                    ->where("tax_id = ?", (int) $taxId),
            );
            if ((int) ($totals["row_count"] ?? 0) === 0) {
                if (count($taxIds) === 1) {
                    $connection->update(
                        $orderTaxTable,
                        [
                            "amount" => (float) $order->getTaxAmount(),
                            "base_amount" => (float) $order->getBaseTaxAmount(),
                            "base_real_amount" => (float) $order->getBaseTaxAmount(),
                        ],
                        ["tax_id = ?" => (int) $taxId],
                    );
                }
                continue;
            }
            $connection->update(
                $orderTaxTable,
                [
                    "amount" => (float) ($totals["amount"] ?? 0.0),
                    "base_amount" => (float) ($totals["base_amount"] ?? 0.0),
                    "base_real_amount" =>
                        (float) ($totals["base_amount"] ?? 0.0),
                ],
                ["tax_id = ?" => (int) $taxId],
            );
        }
    }
}
