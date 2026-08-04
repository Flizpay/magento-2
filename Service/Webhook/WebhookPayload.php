<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Webhook;

use FlizPay\Payment\Service\Payment\ProviderPaymentState;

/**
 * Validated fields required from a payment webhook.
 */
class WebhookPayload
{
    private function __construct(
        private readonly string $transactionId,
        private readonly string $status,
        private readonly ?int $amountMinor,
        private readonly ?int $originalAmountMinor,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $transactionId = $payload["transactionId"] ?? null;
        $status = $payload["status"] ?? null;

        if (
            !is_string($transactionId) ||
            trim($transactionId) === "" ||
            !is_string($status) ||
            trim($status) === ""
        ) {
            throw new \InvalidArgumentException("Invalid webhook payload.");
        }

        $status = ProviderPaymentState::normalize($status);
        $amountMinor = null;
        $originalAmountMinor = null;

        if ($status === ProviderPaymentState::COMPLETED) {
            $amountMinor = self::toMinorUnits($payload["amount"] ?? null);
            $originalAmountMinor = self::toMinorUnits(
                $payload["originalAmount"] ?? null,
            );
        }

        return new self(
            trim($transactionId),
            $status,
            $amountMinor,
            $originalAmountMinor,
        );
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmountMinor(): ?int
    {
        return $this->amountMinor;
    }

    public function getOriginalAmountMinor(): ?int
    {
        return $this->originalAmountMinor;
    }

    private static function toMinorUnits(mixed $amount): int
    {
        if (!is_string($amount) || !preg_match('/^\d+\.\d{2}$/', $amount)) {
            throw new \InvalidArgumentException("Invalid webhook amount.");
        }

        [$units, $cents] = explode(".", $amount);
        return (int) $units * 100 + (int) $cents;
    }
}
