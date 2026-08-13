<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Webhook;

use FlizPay\Payment\Service\Payment\PaymentStateMapper;
use FlizPay\Payment\Service\Webhook\WebhookPayload;
use FlizPay\Payment\Service\Webhook\WebhookProcessor;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class WebhookProcessorTest extends TestCase
{
    public function testCompletedPaymentIsSettled(): void
    {
        $mapper = $this->createMock(PaymentStateMapper::class);
        $mapper
            ->expects(self::once())
            ->method("apply")
            ->with(
                "provider-123",
                "completed",
                9000,
                10000,
                "EUR",
                "100000001",
            );

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())->method("lock")->willReturn(true);
        $lockManager->expects(self::once())->method("unlock");

        (new WebhookProcessor($mapper, $lockManager))->process(
            WebhookPayload::fromArray([
                "transactionId" => "provider-123",
                "status" => "completed",
                "amount" => "90.00",
                "originalAmount" => "100.00",
                "currency" => "EUR",
                "metadata" => ["orderId" => "100000001"],
            ]),
        );
    }

    public function testFailedPaymentIsMapped(): void
    {
        $mapper = $this->createMock(PaymentStateMapper::class);
        $mapper
            ->expects(self::once())
            ->method("apply")
            ->with("provider-123", "failed", null, null, null, null);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())->method("lock")->willReturn(true);
        $lockManager->expects(self::once())->method("unlock");

        (new WebhookProcessor($mapper, $lockManager))->process(
            WebhookPayload::fromArray([
                "transactionId" => "provider-123",
                "status" => "failed",
            ]),
        );
    }
}
