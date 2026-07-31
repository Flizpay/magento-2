<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use Magento\Framework\Exception\LocalizedException;

class PaymentStateMapper
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly CompletedPaymentHandler $completedPaymentHandler,
        private readonly TerminalFailureHandler $terminalFailureHandler,
    ) {}

    public function apply(string $providerTransactionId, string $status): void
    {
        $status = ProviderPaymentState::normalize($status);

        $attempt = $this->attemptRepository->getByProviderTransactionId(
            $providerTransactionId,
        );
        $currentStatus = (string) $attempt->getData("provider_status");

        if ($currentStatus === $status) {
            return;
        }

        if (ProviderPaymentState::isTerminal($currentStatus)) {
            throw new LocalizedException(
                __("FLIZpay payment transition is invalid."),
            );
        }

        if ($status === ProviderPaymentState::COMPLETED) {
            $this->completedPaymentHandler->execute($providerTransactionId);
            return;
        }

        if (ProviderPaymentState::isFailure($status)) {
            $this->terminalFailureHandler->execute($attempt, $status);
            return;
        }

        $attempt->setData("provider_status", $status);
        $this->attemptRepository->save($attempt);
    }
}
