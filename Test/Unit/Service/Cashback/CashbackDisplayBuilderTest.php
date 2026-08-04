<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Cashback;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Cashback\CashbackDisplayBuilder;
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
        self::assertSame("FLIZpay - Up to 5% Cashback", $display["title"]);
        self::assertStringContainsString("2.5% cashback", $display["description"]);
        self::assertTrue($display["showLogo"]);
    }

    public function testUnavailableCashbackUsesDefaultPresentation(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isConnected")->willReturn(false);
        $config->method("getCashbackData")->willReturn([
            "first_purchase_amount" => 5.0,
            "standard_amount" => 2.0,
        ]);

        $display = $this->createBuilder($config)->build()->toArray();

        self::assertFalse($display["available"]);
        self::assertSame("FLIZpay", $display["title"]);
        self::assertNull($display["formattedValue"]);
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
            $resolver,
            $storeManager,
        );
    }
}
