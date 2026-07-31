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
            "https://api.flizpay.de",
            ["secure.flizpay.de"],
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
                "https://api.flizpay.de",
                ["secure.flizpay.de"],
            ))
                ->generateWebhookSecret(),
        );
    }

    public function testCreatesTransactionWithOnePost(): void
    {
        $request = ["amount" => "10.00", "currency" => "EUR"];
        $httpClient = $this->createMock(Curl::class);
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
            ->createTransaction($request);

        self::assertSame("transaction-123", $transaction->getTransactionId());
        self::assertSame(
            "https://secure.flizpay.de/pay/token",
            $transaction->getRedirectUrl(),
        );
    }

    public function testUsesInjectedApiBaseUrlAndRedirectHost(): void
    {
        $httpClient = $this->createMock(Curl::class);
        $httpClient
            ->expects(self::once())
            ->method("post")
            ->with("https://api.example.test/v1/transactions", "request");
        $httpClient->method("getStatus")->willReturn(200);
        $httpClient->method("getBody")->willReturn("response");

        $json = $this->createStub(Json::class);
        $json->method("serialize")->willReturn("request");
        $json->method("unserialize")->willReturn([
            "transactionId" => "transaction-123",
            "redirectUrl" => "https://checkout.example.test/pay",
        ]);

        $transaction = $this->createClient(
            $httpClient,
            $json,
            $this->createStub(LoggerInterface::class),
            "https://api.example.test/v1/",
            ["checkout.example.test"],
        )->createTransaction([]);

        self::assertSame(
            "https://checkout.example.test/pay",
            $transaction->getRedirectUrl(),
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

        $this->createClient($httpClient, $json)->createTransaction([]);
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
            "HTTP redirect" => [[
                "transactionId" => "transaction-123",
                "redirectUrl" => "http://secure.flizpay.de/pay",
            ]],
            "lookalike redirect host" => [[
                "transactionId" => "transaction-123",
                "redirectUrl" => "https://secure.flizpay.de.example.test/pay",
            ]],
            "redirect credentials" => [[
                "transactionId" => "transaction-123",
                "redirectUrl" => "https://user@secure.flizpay.de/pay",
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
        )->createTransaction(["secret" => "request-body"]);
    }

    public function testClientErrorIsDefiniteCreationFailure(): void
    {
        $httpClient = $this->createStub(Curl::class);
        $httpClient->method("getStatus")->willReturn(400);

        try {
            $this->createClient($httpClient, $this->createStub(Json::class))
                ->createTransaction([]);
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
                ->createTransaction([]);
            self::fail("Expected transaction creation to fail.");
        } catch (TransactionCreationException $exception) {
            self::assertFalse($exception->isDefinite());
            self::assertSame(
                TransactionCreationException::API_TRANSPORT_ERROR,
                $exception->getSafeErrorCode(),
            );
        }
    }

    /**
     * @param Curl $httpClient
     * @param Json $json
     * @param LoggerInterface|null $logger
     * @param string $baseUrl
     * @param list<string> $redirectHosts
     * @return FlizPayApiClient
     */
    private function createClient(
        Curl $httpClient,
        Json $json,
        ?LoggerInterface $logger = null,
        string $baseUrl = "https://api.flizpay.de",
        array $redirectHosts = ["secure.flizpay.de"],
    ): FlizPayApiClient {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getApiKey")->willReturn("api-key");

        return new FlizPayApiClient(
            $httpClient,
            $json,
            $config,
            $logger ?? $this->createStub(LoggerInterface::class),
            $baseUrl,
            $redirectHosts,
        );
    }
}
