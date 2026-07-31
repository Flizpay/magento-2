<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Service\Payment\ProviderPaymentState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProviderPaymentStateTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function supportedStates(): array
    {
        return [
            "pending" => ["pending"],
            "processing" => ["processing"],
            "completed" => ["completed"],
            "failed" => ["failed"],
            "canceled" => ["canceled"],
        ];
    }

    #[DataProvider("supportedStates")]
    public function testNormalizesSupportedStates(string $state): void
    {
        self::assertSame($state, ProviderPaymentState::normalize(" $state "));
    }

    public function testRejectsUnknownState(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProviderPaymentState::normalize("expired");
    }
}
