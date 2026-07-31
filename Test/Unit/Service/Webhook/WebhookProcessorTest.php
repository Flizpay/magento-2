<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Webhook;

use FlizPay\Payment\Service\Payment\PaymentStateMapper;
use FlizPay\Payment\Service\Webhook\WebhookPayload;
use FlizPay\Payment\Service\Webhook\WebhookProcessor;
use PHPUnit\Framework\TestCase;

class WebhookProcessorTest extends TestCase
{
    public function testCompletedPaymentIsSettled(): void
    {
        $mapper = $this->createMock(PaymentStateMapper::class);
        $mapper
            ->expects(self::once())
            ->method("apply")
            ->with("provider-123", "completed");

        (new WebhookProcessor($mapper))->process(
            WebhookPayload::fromArray([
                "transactionId" => "provider-123",
                "status" => "completed",
            ]),
        );
    }

    public function testFailedPaymentIsMapped(): void
    {
        $mapper = $this->createMock(PaymentStateMapper::class);
        $mapper
            ->expects(self::once())
            ->method("apply")
            ->with("provider-123", "failed");

        (new WebhookProcessor($mapper))->process(
            WebhookPayload::fromArray([
                "transactionId" => "provider-123",
                "status" => "failed",
            ]),
        );
    }
}
