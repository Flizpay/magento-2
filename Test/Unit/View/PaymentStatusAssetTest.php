<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class PaymentStatusAssetTest extends TestCase
{
    public function testCompletedPollingBypassesCachedSuccessPage(): void
    {
        $script = file_get_contents(
            __DIR__ . "/../../../view/frontend/web/js/payment-status.js",
        );

        self::assertIsString($script);
        self::assertStringContainsString("Date.now()", $script);
        self::assertStringContainsString("window.location.assign", $script);
    }
}
