<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class CashbackAssetTest extends TestCase
{
    public function testCheckoutTemplateRendersPreparedCashbackData(): void
    {
        $template = file_get_contents(
            __DIR__ . "/../../../view/frontend/web/template/payment/flizpay.html",
        );
        $script = file_get_contents(
            __DIR__ .
                "/../../../view/frontend/web/js/view/payment/method-renderer/flizpay-method.js",
        );

        self::assertIsString($template);
        self::assertIsString($script);
        self::assertStringContainsString("getDisplayTitle()", $template);
        self::assertStringContainsString("shouldShowDescription()", $template);
        self::assertStringContainsString("shouldShowLogo()", $template);
        self::assertStringContainsString("getLogoUrl()", $template);
        self::assertStringContainsString("cashback || {}", $script);
    }

    public function testCheckoutAssetsExist(): void
    {
        self::assertFileExists(
            __DIR__ .
                "/../../../view/frontend/web/images/fliz-checkout-logo.svg",
        );
        self::assertFileExists(
            __DIR__ . "/../../../view/frontend/web/css/source/_checkout.css",
        );
    }
}
