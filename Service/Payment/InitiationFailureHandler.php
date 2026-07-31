<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use FlizPay\Payment\Model\PaymentAttempt;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class InitiationFailureHandler
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly QuoteRestorer $quoteRestorer,
    ) {}

    public function handleDefinite(
        PaymentAttempt $attempt,
        Order $order,
        string $safeErrorCode,
    ): void {
        if ($order->canCancel()) {
            $order->cancel();
            $order->addCommentToStatusHistory(
                (string) __("FLIZpay payment could not be started."),
            );
            $this->orderRepository->save($order);
        }

        $this->quoteRestorer->restore(
            $attempt->getData("quote_id") !== null
                ? (int) $attempt->getData("quote_id")
                : null,
        );
        $attempt->setData("creation_state", "failed");
        $attempt->setData("safe_error_code", $safeErrorCode);
        $this->attemptRepository->save($attempt);
    }

    public function handleAmbiguous(
        PaymentAttempt $attempt,
        string $safeErrorCode,
    ): void {
        $attempt->setData("creation_state", "ambiguous");
        $attempt->setData("safe_error_code", $safeErrorCode);
        $this->attemptRepository->save($attempt);
    }
}
