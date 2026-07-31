<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Service\Payment\ReturnContext;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReturnContextTest extends TestCase
{
    /**
     * @return array<string, array{?string, bool}>
     */
    public static function settlementStates(): array
    {
        return [
            "webhook settled" => ["completed", true],
            "awaiting webhook" => ["pending", false],
            "provider processing" => ["processing", false],
            "no provider status" => [null, false],
        ];
    }

    #[DataProvider("settlementStates")]
    public function testOnlyWebhookSettlementCompletesTheReturn(
        ?string $providerStatus,
        bool $expected,
    ): void {
        $attempt = $this->createMock(PaymentAttempt::class);
        $attempt
            ->method("getData")
            ->with("provider_status")
            ->willReturn($providerStatus);

        self::assertSame(
            $expected,
            (new ReturnContext(
                $attempt,
                $this->createMock(Order::class),
            ))->isComplete(),
        );
    }

    /** @return array<string, array{?string, string}> */
    public static function publicStates(): array
    {
        return [
            "pending" => ["pending", "pending"],
            "processing" => ["processing", "pending"],
            "completed" => ["completed", "complete"],
            "failed" => ["failed", "failed"],
            "canceled" => ["canceled", "failed"],
        ];
    }

    #[DataProvider("publicStates")]
    public function testMapsPublicReturnState(
        ?string $providerStatus,
        string $expected,
    ): void {
        $attempt = $this->createStub(PaymentAttempt::class);
        $attempt->method("getData")->willReturn($providerStatus);

        self::assertSame(
            $expected,
            (new ReturnContext(
                $attempt,
                $this->createStub(Order::class),
            ))->getPublicStatus(),
        );
    }
}
