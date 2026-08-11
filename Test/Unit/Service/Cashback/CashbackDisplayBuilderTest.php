<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Cashback;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Cashback\CashbackDisplayBuilder;
use FlizPay\Payment\Service\Cashback\PercentageFormatter;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CashbackDisplayBuilderTest extends TestCase
{
    public function testBuildsBothRatesDisplay(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isConnected")->willReturn(true);
        $config->method("getCashbackData")->willReturn([
            "first_purchase_amount" => 5.0,
            "standard_amount" => 2.5,
        ]);
        $config->method("isCashbackInTitleEnabled")->willReturn(true);
        $config->method("isCheckoutLogoEnabled")->willReturn(true);
        $config->method("isCheckoutSubtitleEnabled")->willReturn(true);

        $display = $this->createBuilder($config)->build()->toArray();

        self::assertTrue($display["available"]);
        self::assertSame("both", $display["type"]);
        self::assertSame("5", $display["formattedValue"]);
        self::assertSame("FLIZpay - Save up to 5%", $display["title"]);
        self::assertSame(
            "Get 5% discount on your first payment, then 2.5% on every payment after that at Demo Store.",
            $display["description"],
        );
        self::assertTrue($display["showLogo"]);
    }

    public function testBuildsFirstPurchaseDisplay(): void
    {
        $config = $this->createConnectedConfig(5.0, 0.0);

        $display = $this->createBuilder($config)->build()->toArray();

        self::assertSame("first", $display["type"]);
        self::assertSame(
            "FLIZpay - Save 5% on your first payment",
            $display["title"],
        );
        self::assertSame(
            "Get 5% discount on your first payment at Demo Store.",
            $display["description"],
        );
    }

    public function testBuildsStandardDisplay(): void
    {
        $config = $this->createConnectedConfig(0.0, 2.5);

        $display = $this->createBuilder($config)->build()->toArray();

        self::assertSame("standard", $display["type"]);
        self::assertSame("FLIZpay - Up to 2.5% discount", $display["title"]);
        self::assertSame(
            "Get 2.5% discount on every payment at Demo Store.",
            $display["description"],
        );
    }

    public function testTitleCanBeDisabled(): void
    {
        $config = $this->createConnectedConfig(5.0, 2.5, false);

        $display = $this->createBuilder($config)->build()->toArray();

        self::assertSame("FLIZpay", $display["title"]);
    }

    public function testUnavailableCashbackUsesDefaultPresentation(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isConnected")->willReturn(false);
        $config->method("getCashbackData")->willReturn([
            "first_purchase_amount" => 5.0,
            "standard_amount" => 2.0,
        ]);
        $config->method("isCheckoutSubtitleEnabled")->willReturn(true);

        $display = $this->createBuilder($config)->build()->toArray();

        self::assertFalse($display["available"]);
        self::assertSame("FLIZpay", $display["title"]);
        self::assertNull($display["formattedValue"]);
        self::assertSame(
            "Pay with FLIZ. Stop carrying the hidden cost of payments. The European solution.",
            $display["description"],
        );
    }

    private function createConnectedConfig(
        float $firstPurchase,
        float $standard,
        bool $showTitle = true,
    ): ConfigInterface {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isConnected")->willReturn(true);
        $config->method("getCashbackData")->willReturn([
            "first_purchase_amount" => $firstPurchase,
            "standard_amount" => $standard,
        ]);
        $config->method("isCashbackInTitleEnabled")->willReturn($showTitle);
        $config->method("isCheckoutLogoEnabled")->willReturn(true);
        $config->method("isCheckoutSubtitleEnabled")->willReturn(true);

        return $config;
    }

    private function createBuilder(ConfigInterface $config): CashbackDisplayBuilder
    {
        $resolver = $this->createStub(ResolverInterface::class);
        $resolver->method("getLocale")->willReturn("en_US");
        $store = $this->createStub(StoreInterface::class);
        $store->method("getName")->willReturn("Demo Store");
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getStore")->willReturn($store);

        return new CashbackDisplayBuilder(
            $config,
            new PercentageFormatter($resolver),
            $storeManager,
        );
    }
}
