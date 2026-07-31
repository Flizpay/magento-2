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

        return new self(
            trim($transactionId),
            ProviderPaymentState::normalize($status),
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
}
