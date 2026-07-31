<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Webhook;

use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;

/**
 * Dispatches supported authenticated payment notifications.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly CompletedPaymentHandler $completedPaymentHandler,
    ) {}

    public function process(WebhookPayload $payload): void
    {
        if ($payload->getStatus() !== "completed") {
            throw new \InvalidArgumentException("Unsupported webhook status.");
        }

        $this->completedPaymentHandler->execute($payload->getTransactionId());
    }
}
