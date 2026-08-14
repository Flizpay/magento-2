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
        private readonly ?string $currency,
        private readonly ?string $externalOrderId,
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
        $currency = null;
        $externalOrderId = null;

        if ($status === ProviderPaymentState::COMPLETED) {
            $amountMinor = self::toMinorUnits($payload["amount"] ?? null);
            $originalAmountMinor = self::toMinorUnits(
                $payload["originalAmount"] ?? null,
            );
            $currency = $payload["currency"] ?? null;
            $externalOrderId = $payload["metadata"]["orderId"] ?? null;

            if (
                !is_string($currency) ||
                strtoupper(trim($currency)) !== "EUR" ||
                (!is_string($externalOrderId) && !is_int($externalOrderId)) ||
                trim((string) $externalOrderId) === ""
            ) {
                throw new \InvalidArgumentException("Invalid webhook binding.");
            }

            $currency = "EUR";
            $externalOrderId = trim((string) $externalOrderId);
        }

        return new self(
            trim($transactionId),
            $status,
            $amountMinor,
            $originalAmountMinor,
            $currency,
            $externalOrderId,
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

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getExternalOrderId(): ?string
    {
        return $this->externalOrderId;
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
