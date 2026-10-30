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
            ["startUrl", "formKey", "checkout"],
            array_keys($paymentConfig),
        );
        self::assertStringContainsString(
            "flizpay/payment/start",
            $paymentConfig["startUrl"],
        );
        self::assertNotSame("", $paymentConfig["formKey"]);
        self::assertSame(
            [
                "publicId",
                "locale",
                "showHeadline",
                "showDescription",
                "showLogo",
            ],
            array_keys($paymentConfig["checkout"]),
        );
    }

    /**
     * The browser must receive no credentials and no locally stored discount
     * rules; <fliz-checkout> owns the offer text.
     */
    public function testCheckoutPayloadCarriesNoSecretsOrOfferText(): void
    {
        $checkout = Bootstrap::getObjectManager()
            ->get(ConfigProvider::class)
            ->getConfig()["payment"]["flizpay"]["checkout"];

        self::assertIsString($checkout["publicId"]);
        self::assertIsString($checkout["locale"]);
        self::assertIsBool($checkout["showHeadline"]);
        self::assertIsBool($checkout["showDescription"]);
        self::assertIsBool($checkout["showLogo"]);

        // No amount: at checkout it changes with the cart and is read from the
        // quote in the browser instead.
        self::assertArrayNotHasKey("amount", $checkout);
        self::assertArrayNotHasKey("title", $checkout);
        self::assertArrayNotHasKey("description", $checkout);
    }
}
