<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Webhook;

use FlizPay\Payment\Service\Webhook\WebhookPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebhookPayloadTest extends TestCase
{
    public function testParsesRequiredCompletedFields(): void
    {
        $payload = WebhookPayload::fromArray([
            "transactionId" => "provider-123",
            "status" => "COMPLETED",
            "amount" => "90.00",
            "originalAmount" => "100.00",
            "currency" => "EUR",
            "metadata" => ["orderId" => "100000001"],
        ]);

        self::assertSame("provider-123", $payload->getTransactionId());
        self::assertSame("completed", $payload->getStatus());
        self::assertSame(9000, $payload->getAmountMinor());
        self::assertSame(10000, $payload->getOriginalAmountMinor());
        self::assertSame("EUR", $payload->getCurrency());
        self::assertSame("100000001", $payload->getExternalOrderId());
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider("invalidPayloadProvider")]
    public function testRejectsMissingRequiredFields(array $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WebhookPayload::fromArray($payload);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidPayloadProvider(): array
    {
        return [
            "missing transaction" => [["status" => "completed"]],
            "missing status" => [["transactionId" => "provider-123"]],
            "non-string transaction" => [[
                "transactionId" => 123,
                "status" => "completed",
            ]],
            "completed without currency" => [[
                "transactionId" => "provider-123",
                "status" => "completed",
                "amount" => "90.00",
                "originalAmount" => "100.00",
                "metadata" => ["orderId" => "100000001"],
            ]],
            "completed without order binding" => [[
                "transactionId" => "provider-123",
                "status" => "completed",
                "amount" => "90.00",
                "originalAmount" => "100.00",
                "currency" => "EUR",
            ]],
        ];
    }
}
