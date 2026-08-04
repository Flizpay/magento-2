<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Model\Ui\ConfigProvider;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CheckoutConfigProviderTest extends TestCase
{
    public function testCheckoutReceivesOnlyPostHandoffData(): void
    {
        $config = Bootstrap::getObjectManager()
            ->get(ConfigProvider::class)
            ->getConfig();
        $paymentConfig = $config["payment"]["flizpay"];

        self::assertSame(
            ["startUrl", "formKey", "cashback"],
            array_keys($paymentConfig),
        );
        self::assertStringContainsString(
            "flizpay/payment/start",
            $paymentConfig["startUrl"],
        );
        self::assertNotSame("", $paymentConfig["formKey"]);
        self::assertSame(
            [
                "available",
                "type",
                "formattedValue",
                "title",
                "description",
                "showLogo",
            ],
            array_keys($paymentConfig["cashback"]),
        );
    }
}
