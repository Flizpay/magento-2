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

use Magento\Framework\Exception\LocalizedException;

class PaymentStateMapper
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly CompletedPaymentHandler $completedPaymentHandler,
        private readonly TerminalFailureHandler $terminalFailureHandler,
    ) {}

    public function apply(
        string $providerTransactionId,
        string $status,
        ?int $amountMinor = null,
        ?int $originalAmountMinor = null,
        ?string $currency = null,
        ?string $externalOrderId = null,
    ): void {
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
            if (
                $amountMinor === null ||
                $originalAmountMinor === null ||
                $currency === null ||
                $externalOrderId === null
            ) {
                throw new LocalizedException(
                    __("FLIZpay completion amounts are missing."),
                );
            }
            $this->completedPaymentHandler->execute(
                $providerTransactionId,
                $originalAmountMinor,
                $amountMinor,
                $currency,
                $externalOrderId,
            );
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
