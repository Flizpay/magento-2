<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Model;

use FlizPay\Payment\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /** @var ScopeConfigInterface&Stub */
    private ScopeConfigInterface $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
    }

    public function testApiKeyIsConfigured(): void
    {
        $this->scopeConfig
            ->method("getValue")
            ->willReturnMap([
                [
                    "payment/flizpay/api_key",
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    null,
                    "api-key",
                ],
            ]);

        $config = $this->createConfig();

        self::assertTrue($config->hasApiKey());
        self::assertSame("api-key", $config->getApiKey());
    }

    public function testMissingApiKeyIsNotConfigured(): void
    {
        $this->scopeConfig
            ->method("getValue")
            ->willReturnMap([
                [
                    "payment/flizpay/api_key",
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    null,
                    "",
                ],
            ]);

        self::assertFalse($this->createConfig()->hasApiKey());
    }

    public function testWebhookSecretIsReadFromProcessedConfig(): void
    {
        $this->scopeConfig
            ->method("getValue")
            ->willReturn("webhook-secret");

        self::assertSame(
            "webhook-secret",
            $this->createConfig()->getWebhookSecret(),
        );
    }

    public function testConnectedRequiresStatusAndWebhookSecret(): void
    {
        $this->scopeConfig
            ->method("getValue")
            ->willReturnMap([
                [
                    "payment/flizpay/connection_status",
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    null,
                    "connected",
                ],
                [
                    "payment/flizpay/webhook_secret",
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    null,
                    "secret",
                ],
            ]);

        self::assertTrue(
            $this->createConfig()->isConnected(),
        );
    }

    public function testReadsNormalizedCashbackData(): void
    {
        $this->scopeConfig->method("getValue")->willReturn(
            '{"first_purchase_amount":5,"standard_amount":"2.5"}',
        );

        self::assertSame(
            ["first_purchase_amount" => 5.0, "standard_amount" => 2.5],
            $this->createConfig()->getCashbackData(),
        );
    }

    public function testRejectsInvalidCashbackData(): void
    {
        $this->scopeConfig->method("getValue")->willReturn(
            '{"first_purchase_amount":-1,"standard_amount":2}',
        );

        self::assertNull($this->createConfig()->getCashbackData());
    }

    private function createConfig(): Config
    {
        $json = $this->createStub(Json::class);
        $json
            ->method("unserialize")
            ->willReturnCallback(
                static fn(string $value): mixed => json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );

        return new Config($this->scopeConfig, $json);
    }
}
