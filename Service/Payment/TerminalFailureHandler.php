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
use FlizPay\Payment\Model\PaymentAttempt;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class TerminalFailureHandler
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly QuoteRestorer $quoteRestorer,
        private readonly ResourceConnection $resourceConnection,
    ) {}

    public function execute(PaymentAttempt $attempt, string $status): void
    {
        $status = ProviderPaymentState::normalize($status);
        if (!ProviderPaymentState::isFailure($status)) {
            throw new \InvalidArgumentException(
                "Payment state is not a failure.",
            );
        }

        $currentStatus = (string) $attempt->getData("provider_status");
        if ($currentStatus === $status) {
            return;
        }
        if (ProviderPaymentState::isTerminal($currentStatus)) {
            throw new LocalizedException(
                __("FLIZpay payment transition is invalid."),
            );
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
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
            if ($order->getInvoiceCollection()->getSize() !== 0) {
                throw new LocalizedException(
                    __("FLIZpay paid order cannot be canceled."),
                );
            }

            if ($order->canCancel()) {
                $order->cancel();
                $order->addCommentToStatusHistory(
                    $status === ProviderPaymentState::FAILED
                        ? (string) __("FLIZpay reported a failed payment.")
                        : (string) __("FLIZpay reported a canceled payment."),
                );
                $this->orderRepository->save($order);
            } elseif ($order->getState() !== Order::STATE_CANCELED) {
                throw new LocalizedException(
                    __("FLIZpay order cannot be canceled."),
                );
            }

            $this->quoteRestorer->restore(
                $attempt->getData("quote_id") !== null
                    ? (int) $attempt->getData("quote_id")
                    : null,
            );
            $attempt->setData("provider_status", $status);
            $this->attemptRepository->save($attempt);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
