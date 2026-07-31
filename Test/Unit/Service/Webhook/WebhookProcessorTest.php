<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Webhook;

use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;
use FlizPay\Payment\Service\Webhook\WebhookPayload;
use FlizPay\Payment\Service\Webhook\WebhookProcessor;
use PHPUnit\Framework\TestCase;

class WebhookProcessorTest extends TestCase
{
    public function testCompletedPaymentIsSettled(): void
    {
        $handler = $this->createMock(CompletedPaymentHandler::class);
        $handler
            ->expects(self::once())
            ->method("execute")
            ->with("provider-123");

        (new WebhookProcessor($handler))->process(
            WebhookPayload::fromArray([
                "transactionId" => "provider-123",
                "status" => "completed",
            ]),
        );
    }

    public function testUnsupportedStatusDoesNotSettle(): void
    {
        $handler = $this->createMock(CompletedPaymentHandler::class);
        $handler->expects(self::never())->method("execute");
        $this->expectException(\InvalidArgumentException::class);

        (new WebhookProcessor($handler))->process(
            WebhookPayload::fromArray([
                "transactionId" => "provider-123",
                "status" => "failed",
            ]),
        );
    }
}
