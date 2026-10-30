<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Connection;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Checkout\PublicIdResolver;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use FlizPay\Payment\Service\Connection\ConnectionManager;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ConnectionManagerTest extends TestCase
{
    public function testRegistersWebhookAndStoresGeneratedSecret(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");
        $config->method("getConnectionStatus")->willReturn("not_connected");

        $writer = $this->createMock(ConnectionConfigWriter::class);
        $writer
            ->expects(self::once())
            ->method("markConnecting")
            ->with(
                "https://shop.test/flizpay/webhook",
                hash("sha256", "api-key"),
            );
        $writer
            ->expects(self::once())
            ->method("saveWebhookSecret")
            ->with("generated-secret");

        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("registerWebhook")
            ->with("https://shop.test/flizpay/webhook");
        $apiClient
            ->expects(self::once())
            ->method("generateWebhookSecret")
            ->willReturn("generated-secret");

        $store = $this->createStub(Store::class);
        $store->method("getBaseUrl")->willReturn("https://shop.test/");
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getDefaultStoreView")->willReturn($store);
        $publicIdResolver = $this->createStub(PublicIdResolver::class);

        self::assertTrue(
            (new ConnectionManager(
                $config,
                $writer,
                $apiClient,
                $publicIdResolver,
                $storeManager,
                $this->createStub(PaymentLogger::class),
            ))
                ->connectIfNeeded(),
        );
    }

    public function testConnectedConfigurationDoesNotRotateWebhookSecret(): void
    {
        $apiKeyHash = hash("sha256", "api-key");
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");
        $config->method("getConnectionStatus")->willReturn("connected");
        $config->method("hasWebhookSecret")->willReturn(true);
        $config->method("getConnectionApiKeyHash")->willReturn($apiKeyHash);
        $config
            ->method("getWebhookUrl")
            ->willReturn("https://shop.test/flizpay/webhook");

        $writer = $this->createMock(ConnectionConfigWriter::class);
        $writer->expects(self::never())->method("markConnecting");
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("registerWebhook");

        $store = $this->createStub(Store::class);
        $store->method("getBaseUrl")->willReturn("https://shop.test/");
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getDefaultStoreView")->willReturn($store);
        $publicIdResolver = $this->createStub(PublicIdResolver::class);

        self::assertFalse(
            (new ConnectionManager(
                $config,
                $writer,
                $apiClient,
                $publicIdResolver,
                $storeManager,
                $this->createStub(PaymentLogger::class),
            ))
                ->connectIfNeeded(),
        );
    }

    public function testConnectingConfigurationRetriesWithSameApiKey(): void
    {
        $apiKeyHash = hash("sha256", "api-key");
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");
        $config->method("getConnectionStatus")->willReturn("connecting");
        $config->method("hasWebhookSecret")->willReturn(true);
        $config->method("getConnectionApiKeyHash")->willReturn($apiKeyHash);
        $config
            ->method("getWebhookUrl")
            ->willReturn("https://shop.test/flizpay/webhook");

        $writer = $this->createMock(ConnectionConfigWriter::class);
        $writer
            ->expects(self::once())
            ->method("markConnecting")
            ->with(
                "https://shop.test/flizpay/webhook",
                $apiKeyHash,
            );
        $writer
            ->expects(self::once())
            ->method("saveWebhookSecret")
            ->with("new-secret");

        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("registerWebhook")
            ->with("https://shop.test/flizpay/webhook");
        $apiClient
            ->expects(self::once())
            ->method("generateWebhookSecret")
            ->willReturn("new-secret");

        $store = $this->createStub(Store::class);
        $store->method("getBaseUrl")->willReturn("https://shop.test/");
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getDefaultStoreView")->willReturn($store);
        $publicIdResolver = $this->createStub(PublicIdResolver::class);

        self::assertTrue(
            (new ConnectionManager(
                $config,
                $writer,
                $apiClient,
                $publicIdResolver,
                $storeManager,
                $this->createStub(PaymentLogger::class),
            ))
                ->connectIfNeeded(),
        );
    }

    /** markConnecting() blanks the stored id, so a reconnect must refetch it. */
    public function testConnectingAcquiresThePublicIdUnthrottled(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");
        $config->method("getConnectionStatus")->willReturn("not_connected");

        $apiClient = $this->createStub(FlizPayApiClient::class);
        $apiClient->method("generateWebhookSecret")->willReturn("secret");

        $store = $this->createStub(Store::class);
        $store->method("getBaseUrl")->willReturn("https://shop.test/");
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getDefaultStoreView")->willReturn($store);

        $publicIdResolver = $this->createMock(PublicIdResolver::class);
        $publicIdResolver->expects(self::once())->method("ensure")->with();

        self::assertTrue(
            (new ConnectionManager(
                $config,
                $this->createStub(ConnectionConfigWriter::class),
                $apiClient,
                $publicIdResolver,
                $storeManager,
                $this->createStub(PaymentLogger::class),
            ))->connectIfNeeded(),
        );
    }

    /**
     * Nothing to reconnect, but an earlier acquisition may have failed, and
     * saving settings is merchant-initiated.
     */
    public function testUnchangedConfigurationStillRetriesThePublicId(): void
    {
        $apiKeyHash = hash("sha256", "api-key");
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");
        $config->method("getConnectionStatus")->willReturn("connected");
        $config->method("hasWebhookSecret")->willReturn(true);
        $config->method("getConnectionApiKeyHash")->willReturn($apiKeyHash);
        $config
            ->method("getWebhookUrl")
            ->willReturn("https://shop.test/flizpay/webhook");

        $store = $this->createStub(Store::class);
        $store->method("getBaseUrl")->willReturn("https://shop.test/");
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getDefaultStoreView")->willReturn($store);

        $publicIdResolver = $this->createMock(PublicIdResolver::class);
        $publicIdResolver->expects(self::once())->method("ensure")->with();

        self::assertFalse(
            (new ConnectionManager(
                $config,
                $this->createStub(ConnectionConfigWriter::class),
                $this->createStub(FlizPayApiClient::class),
                $publicIdResolver,
                $storeManager,
                $this->createStub(PaymentLogger::class),
            ))->connectIfNeeded(),
        );
    }
}
