<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Checkout;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Checkout\PublicIdResolver;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class PublicIdResolverTest extends TestCase
{
    private const RETRY_KEY = "flizpay_public_id_retry";

    private function config(
        bool $hasApiKey = true,
        string $publicId = "",
    ): ConfigInterface {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("hasApiKey")->willReturn($hasApiKey);
        $config->method("getPublicId")->willReturn($publicId);

        return $config;
    }

    private function resolver(
        ?ConfigInterface $config = null,
        ?ConnectionConfigWriter $configWriter = null,
        ?FlizPayApiClient $apiClient = null,
        ?CacheInterface $cache = null,
    ): PublicIdResolver {
        return new PublicIdResolver(
            $config ?? $this->config(),
            $configWriter ?? $this->createStub(ConnectionConfigWriter::class),
            $apiClient ?? $this->createStub(FlizPayApiClient::class),
            $cache ?? $this->createStub(CacheInterface::class),
            $this->createStub(PaymentLogger::class),
        );
    }

    public function testResolveReturnsTheStoredId(): void
    {
        self::assertSame(
            "pub_123",
            $this->resolver($this->config(true, "pub_123"))->resolve(),
        );
    }

    /** Rendering a page must never block on a FLIZpay request. */
    public function testResolveNeverContactsFlizpay(): void
    {
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("fetchPublicId");

        self::assertSame(
            "",
            $this->resolver(null, null, $apiClient)->resolve(),
        );
    }

    public function testResolveReturnsNothingWithoutAnApiKey(): void
    {
        self::assertSame(
            "",
            $this->resolver($this->config(false, "pub_123"))->resolve(),
        );
    }

    public function testEnsureStoresAFetchedId(): void
    {
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("fetchPublicId")
            ->willReturn("pub_456");

        $configWriter = $this->createMock(ConnectionConfigWriter::class);
        $configWriter
            ->expects(self::once())
            ->method("savePublicId")
            ->with("pub_456");

        // A stored id makes the throttle meaningless.
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method("remove")->with(self::RETRY_KEY);

        self::assertSame(
            "pub_456",
            $this->resolver(null, $configWriter, $apiClient, $cache)->ensure(),
        );
    }

    public function testEnsureKeepsTheStoredIdWithoutFetching(): void
    {
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("fetchPublicId");

        self::assertSame(
            "pub_789",
            $this->resolver(
                $this->config(true, "pub_789"),
                null,
                $apiClient,
            )->ensure(),
        );
    }

    public function testEnsureBacksOffAfterAFailedFetch(): void
    {
        $apiClient = $this->createStub(FlizPayApiClient::class);
        $apiClient->method("fetchPublicId")->willReturn(null);

        $configWriter = $this->createMock(ConnectionConfigWriter::class);
        $configWriter->expects(self::never())->method("savePublicId");

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects(self::once())
            ->method("save")
            ->with("1", self::RETRY_KEY, [], 3600);

        self::assertSame(
            "",
            $this->resolver(null, $configWriter, $apiClient, $cache)->ensure(),
        );
    }

    public function testEnsureTreatsAnApiErrorAsAFailure(): void
    {
        $apiClient = $this->createStub(FlizPayApiClient::class);
        $apiClient
            ->method("fetchPublicId")
            ->willThrowException(new LocalizedException(__("unavailable")));

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method("save")->with("1", self::RETRY_KEY);

        self::assertSame(
            "",
            $this->resolver(null, null, $apiClient, $cache)->ensure(),
        );
    }

    public function testThrottledEnsureSkipsTheRequestDuringBackOff(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method("load")->willReturn("1");

        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("fetchPublicId");

        self::assertSame(
            "",
            $this->resolver(null, null, $apiClient, $cache)->ensure(true),
        );
    }

    /** Saving settings is merchant-initiated, so it ignores the back-off. */
    public function testUnthrottledEnsureIgnoresTheBackOff(): void
    {
        $apiClient = $this->createStub(FlizPayApiClient::class);
        $apiClient->method("fetchPublicId")->willReturn("pub_abc");

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method("load");

        self::assertSame(
            "pub_abc",
            $this->resolver(null, null, $apiClient, $cache)->ensure(),
        );
    }

    public function testEnsureDoesNothingWithoutAnApiKey(): void
    {
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("fetchPublicId");

        self::assertSame(
            "",
            $this->resolver($this->config(false), null, $apiClient)->ensure(),
        );
    }

    public function testClearRetryBackoffRemovesTheMarker(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method("remove")->with(self::RETRY_KEY);

        $this->resolver(null, null, null, $cache)->clearRetryBackoff();
    }
}
