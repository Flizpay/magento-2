<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Model;

use FlizPay\Payment\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
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

        $config = new Config($this->scopeConfig);

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

        self::assertFalse((new Config($this->scopeConfig))->hasApiKey());
    }

    public function testWebhookSecretIsReadFromProcessedConfig(): void
    {
        $this->scopeConfig
            ->method("getValue")
            ->willReturn("webhook-secret");

        self::assertSame(
            "webhook-secret",
            (new Config($this->scopeConfig))->getWebhookSecret(),
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
            (new Config($this->scopeConfig))->isConnected(),
        );
    }
}
