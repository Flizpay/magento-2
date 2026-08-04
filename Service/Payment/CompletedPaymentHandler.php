<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
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
                ProviderPaymentState::isFailure(
                    (string) $attempt->getData("provider_status"),
                )
            ) {
                throw new LocalizedException(
                    __("FLIZpay payment transition is invalid."),
                );
            }

            if ($order->getInvoiceCollection()->getSize() === 0) {
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
            }

            $attempt->setData("provider_status", "completed");
            $this->attemptRepository->save($attempt);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
