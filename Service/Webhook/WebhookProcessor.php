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
