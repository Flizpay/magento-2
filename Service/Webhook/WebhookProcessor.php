<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Webhook;

use FlizPay\Payment\Service\Payment\PaymentStateMapper;
use Magento\Framework\Lock\LockManagerInterface;

/**
 * Dispatches supported authenticated payment notifications.
 * Uses row locking to ensure concurrent identical webhooks converge safely.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly PaymentStateMapper $paymentStateMapper,
        private readonly LockManagerInterface $lockManager,
    ) {}

    public function process(WebhookPayload $payload): void
    {
        $lockName = "flizpay_webhook_" . $payload->getTransactionId();

        if (!$this->lockManager->lock($lockName, 10)) {
            throw new \RuntimeException(
                "Could not acquire lock for webhook processing.",
            );
        }

        try {
            $this->paymentStateMapper->apply(
                $payload->getTransactionId(),
                $payload->getStatus(),
                $payload->getAmountMinor(),
                $payload->getOriginalAmountMinor(),
                $payload->getCurrency(),
                $payload->getExternalOrderId(),
            );
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
