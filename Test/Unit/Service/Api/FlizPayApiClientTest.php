<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Api;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Api\TransactionCreationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class FlizPayApiClientTest extends TestCase
{
    public function testRegistersWebhookUrl(): void
    {
        $httpClient = $this->createMock(Curl::class);
        $httpClient->expects(self::once())->method("setTimeout")->with(30);
        $httpClient
            ->expects(self::once())
            ->method("setHeaders")
            ->with(self::callback(
                static fn(array $headers): bool =>
                    !isset($headers["Idempotency-Key"]),
            ));
        $httpClient
            ->expects(self::once())
            ->method("post")
            ->with(
                "https://api.flizpay.de/business/edit",
                "serialized-request",
            );
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");

        $json = $this->createStub(Json::class);
        $json->method("serialize")->willReturn("serialized-request");
        $json->method("unserialize")->willReturn([
            "data" => ["webhookUrl" => "https://shop.test/flizpay/webhook"],
        ]);

        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");

        (new FlizPayApiClient(
            $httpClient,
            $json,
            $config,
            $this->createStub(LoggerInterface::class),
        ))->registerWebhook(
            "https://shop.test/flizpay/webhook",
        );
    }

    public function testGeneratesWebhookSecret(): void
    {
        $httpClient = $this->createMock(Curl::class);
        $httpClient
            ->expects(self::once())
            ->method("get")
            ->with("https://api.flizpay.de/business/generate-webhook-key");
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");

        $json = $this->createStub(Json::class);
        $json->method("unserialize")->willReturn([
            "data" => ["webhookKey" => "generated-secret"],
        ]);

        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");

        self::assertSame(
            "generated-secret",
            (new FlizPayApiClient(
                $httpClient,
                $json,
                $config,
                $this->createStub(LoggerInterface::class),
            ))
                ->generateWebhookSecret(),
        );
    }

    public function testFetchesNormalizedCashbackData(): void
    {
        $httpClient = $this->createMock(Curl::class);
        $httpClient
            ->expects(self::once())
            ->method("get")
            ->with("https://api.flizpay.de/business/cashback");
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");

        $json = $this->createStub(Json::class);
        $json->method("unserialize")->willReturn([
            "data" => [
                "cashback" => [
                    "firstPurchaseAmount" => 5,
                    "amount" => "2.5",
                    "unit" => "percentage",
                ],
            ],
        ]);

        self::assertSame(
            ["first_purchase_amount" => 5.0, "standard_amount" => 2.5],
            $this->createClient($httpClient, $json)->fetchCashbackData(),
        );
    }

    public function testRejectsInvalidCashbackData(): void
    {
        $httpClient = $this->createStub(Curl::class);
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");
        $json = $this->createStub(Json::class);
        $json->method("unserialize")->willReturn([
            "data" => [
                "cashback" => [
                    "firstPurchaseAmount" => -1,
                    "amount" => 2,
                    "unit" => "percentage",
                ],
            ],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            "FLIZpay returned invalid cashback configuration.",
        );

        $this->createClient($httpClient, $json)->fetchCashbackData();
    }

    public function testCreatesTransactionWithOnePost(): void
    {
        $request = ["amount" => "10.00", "currency" => "EUR"];
        $httpClient = $this->createMock(Curl::class);
        $httpClient
            ->expects(self::once())
            ->method("setHeaders")
            ->with(self::callback(
                static fn(array $headers): bool =>
                    $headers["Idempotency-Key"] ===
                    "0123456789abcdef0123456789abcdef",
            ));
        $httpClient
            ->expects(self::once())
            ->method("post")
            ->with(
                "https://api.flizpay.de/transactions",
                "serialized-request",
            );
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");

        $json = $this->createMock(Json::class);
        $json
            ->expects(self::once())
            ->method("serialize")
            ->with($request)
            ->willReturn("serialized-request");
        $json->method("unserialize")->willReturn([
            "data" => [
                "transactionId" => "transaction-123",
                "redirectUrl" => "https://secure.flizpay.de/pay/token",
            ],
        ]);

        $transaction = $this->createClient($httpClient, $json)
            ->createTransaction(
                $request,
                "0123456789abcdef0123456789abcdef",
            );

        self::assertSame("transaction-123", $transaction["transaction_id"]);
        self::assertSame(
            "https://secure.flizpay.de/pay/token",
            $transaction["redirect_url"],
        );
    }

    /** @param array<string, mixed> $response */
    #[DataProvider("invalidTransactionResponseProvider")]
    public function testRejectsInvalidTransactionResponse(array $response): void
    {
        $httpClient = $this->createStub(Curl::class);
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");

        $json = $this->createStub(Json::class);
        $json->method("unserialize")->willReturn($response);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Unable to connect Magento to FLIZpay.");

        $this->createClient($httpClient, $json)->createTransaction(
            [],
            "0123456789abcdef0123456789abcdef",
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidTransactionResponseProvider(): array
    {
        return [
            "empty transaction ID" => [[
                "transactionId" => " ",
                "redirectUrl" => "https://secure.flizpay.de/pay",
            ]],
            "empty redirect" => [[
                "transactionId" => "transaction-123",
                "redirectUrl" => " ",
            ]],
        ];
    }

    public function testFailedCreationIsNotRetriedOrLoggedWithBodies(): void
    {
        $httpClient = $this->createMock(Curl::class);
        $httpClient->expects(self::once())->method("post");
        $httpClient->method("getStatus")->willReturn(500);
        $httpClient
            ->expects(self::never())
            ->method("getBody");

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method("warning")
            ->with(
                "FLIZpay API request failed",
                [
                    "path" => "/transactions",
                    "status" => 500,
                    "exception" => \RuntimeException::class,
                ],
            );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Unable to connect Magento to FLIZpay.");

        $this->createClient(
            $httpClient,
            $this->createStub(Json::class),
            $logger,
        )->createTransaction(
            ["secret" => "request-body"],
            "0123456789abcdef0123456789abcdef",
        );
    }

    public function testClientErrorIsDefiniteCreationFailure(): void
    {
        $httpClient = $this->createStub(Curl::class);
        $httpClient->method("getStatus")->willReturn(400);

        try {
            $this->createClient($httpClient, $this->createStub(Json::class))
                ->createTransaction([], "0123456789abcdef0123456789abcdef");
            self::fail("Expected transaction creation to fail.");
        } catch (TransactionCreationException $exception) {
            self::assertTrue($exception->isDefinite());
            self::assertSame(
                TransactionCreationException::API_REJECTED,
                $exception->getSafeErrorCode(),
            );
        }
    }

    public function testServerErrorIsAmbiguousCreationFailure(): void
    {
        $httpClient = $this->createStub(Curl::class);
        $httpClient->method("getStatus")->willReturn(500);

        try {
            $this->createClient($httpClient, $this->createStub(Json::class))
                ->createTransaction([], "0123456789abcdef0123456789abcdef");
            self::fail("Expected transaction creation to fail.");
        } catch (TransactionCreationException $exception) {
            self::assertFalse($exception->isDefinite());
            self::assertSame(
                TransactionCreationException::API_TRANSPORT_ERROR,
                $exception->getSafeErrorCode(),
            );
        }
    }

    public function testConflictHasDedicatedCreationFailureCode(): void
    {
        $httpClient = $this->createStub(Curl::class);
        $httpClient->method("getStatus")->willReturn(409);

        try {
            $this->createClient($httpClient, $this->createStub(Json::class))
                ->createTransaction([], "0123456789abcdef0123456789abcdef");
            self::fail("Expected transaction creation to fail.");
        } catch (TransactionCreationException $exception) {
            self::assertTrue($exception->isDefinite());
            self::assertSame(
                TransactionCreationException::API_IDEMPOTENCY_CONFLICT,
                $exception->getSafeErrorCode(),
            );
        }
    }

    public function testRejectsInvalidIdempotencyKeyBeforeRequest(): void
    {
        $httpClient = $this->createMock(Curl::class);
        $httpClient->expects(self::never())->method("post");

        $this->expectException(\InvalidArgumentException::class);
        $this->createClient($httpClient, $this->createStub(Json::class))
            ->createTransaction([], "too-short");
    }

    /**
     * @param Curl $httpClient
     * @param Json $json
     * @param LoggerInterface|null $logger
     * @return FlizPayApiClient
     */
    private function createClient(
        Curl $httpClient,
        Json $json,
        ?LoggerInterface $logger = null,
    ): FlizPayApiClient {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");

        return new FlizPayApiClient(
            $httpClient,
            $json,
            $config,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
