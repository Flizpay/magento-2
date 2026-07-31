<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use FlizPay\Payment\Model\PaymentAttempt;
use Magento\Sales\Model\Order;

/**
 * Validated local payment state for a browser return.
 */
class ReturnContext
{
    public function __construct(
        private readonly PaymentAttempt $attempt,
        private readonly Order $order,
    ) {}

    public function getAttempt(): PaymentAttempt
    {
        return $this->attempt;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Whether trusted webhook processing has settled the Magento order.
     *
     * The attempt's provider status becomes "completed" only inside the
     * webhook settlement transaction that registers the capture and creates
     * the paid invoice, so it is the Magento-owned settlement marker.
     */
    public function isComplete(): bool
    {
        return $this->attempt->getData("provider_status") === "completed";
    }

    public function isTerminalFailure(): bool
    {
        return ProviderPaymentState::isFailure(
            (string) $this->attempt->getData("provider_status"),
        );
    }

    public function getPublicStatus(): string
    {
        if ($this->isComplete()) {
            return "complete";
        }

        return $this->isTerminalFailure() ? "failed" : "pending";
    }
}
