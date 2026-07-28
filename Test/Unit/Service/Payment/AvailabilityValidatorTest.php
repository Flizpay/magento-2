<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Payment\AvailabilityValidator;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AvailabilityValidatorTest extends TestCase
{
    /** @var ConfigInterface&Stub */
    private ConfigInterface $config;

    protected function setUp(): void
    {
        $this->config = $this->createConfig();
    }

    public function testValidQuoteIsAvailable(): void
    {
        self::assertTrue($this->validator()->isAvailable($this->createQuote()));
    }

    public function testDisabledMethodIsUnavailable(): void
    {
        $this->config = $this->createConfig(active: false);

        self::assertFalse(
            $this->validator()->isAvailable($this->createQuote()),
        );
    }

    public function testMissingApiKeyMakesMethodUnavailable(): void
    {
        $this->config = $this->createConfig(hasApiKey: false);

        self::assertFalse(
            $this->validator()->isAvailable($this->createQuote()),
        );
    }

    public function testNonHttpsStoreIsUnavailable(): void
    {
        self::assertFalse(
            $this->validator()->isAvailable(
                $this->createQuote(secureBaseUrl: "http://magento.test/"),
            ),
        );
    }

    private function validator(): AvailabilityValidator
    {
        return new AvailabilityValidator($this->config);
    }

    /**
     * @return ConfigInterface&Stub
     */
    private function createConfig(
        bool $active = true,
        bool $hasApiKey = true,
    ): ConfigInterface {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isActive")->willReturn($active);
        $config->method("hasApiKey")->willReturn($hasApiKey);

        return $config;
    }

    private function createQuote(
        string $secureBaseUrl = "https://magento.test/",
    ): Quote {
        $store = $this->createStub(Store::class);
        $store->method("getId")->willReturn(1);
        $store->method("getBaseUrl")->willReturn($secureBaseUrl);

        $quote = $this->createStub(Quote::class);
        $quote->method("getStore")->willReturn($store);

        return $quote;
    }
}
