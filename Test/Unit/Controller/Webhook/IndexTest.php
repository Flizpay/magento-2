<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Webhook;

use FlizPay\Payment\Controller\Webhook\Index;
use FlizPay\Payment\Service\Connection\ConnectionManager;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use FlizPay\Payment\Service\Webhook\WebhookAuthenticator;
use FlizPay\Payment\Service\Webhook\WebhookProcessor;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    public function testWebhookAcceptsPostOnly(): void
    {
        self::assertTrue(is_subclass_of(Index::class, HttpPostActionInterface::class));
        self::assertFalse(is_subclass_of(Index::class, HttpGetActionInterface::class));
    }

    public function testSignedTestCallbackActivatesConnection(): void
    {
        $rawBody = '{"test":true}';
        $request = $this->createStub(Http::class);
        $request->method("getContent")->willReturn($rawBody);
        $request->method("getHeader")->willReturn("signature");

        $result = $this->createMock(Json::class);
        $result
            ->expects(self::once())
            ->method("setData")
            ->with(["data" => ["alive" => true]])
            ->willReturnSelf();
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method("create")->willReturn($result);

        $serializer = $this->createStub(JsonSerializer::class);
        $serializer->method("unserialize")->willReturn(["test" => true]);
        $authenticator = $this->createStub(WebhookAuthenticator::class);
        $authenticator->method("authenticate")->willReturn(true);

        $connectionManager = $this->createMock(ConnectionManager::class);
        $connectionManager
            ->expects(self::once())
            ->method("confirmWebhookConnection");

        self::assertSame(
            $result,
            (new Index(
                $request,
                $jsonFactory,
                $serializer,
                $authenticator,
                $connectionManager,
                $this->createStub(ConnectionConfigWriter::class),
                $this->createStub(WebhookProcessor::class),
            ))->execute(),
        );
    }

    public function testSignedCompletedCallbackIsProcessed(): void
    {
        $rawBody = '{"transactionId":"provider-123","status":"completed"}';
        $request = $this->createStub(Http::class);
        $request->method("getContent")->willReturn($rawBody);
        $request->method("getHeader")->willReturn("signature");

        $result = $this->createMock(Json::class);
        $result
            ->expects(self::once())
            ->method("setData")
            ->with(["data" => ["received" => true]])
            ->willReturnSelf();
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method("create")->willReturn($result);

        $serializer = $this->createStub(JsonSerializer::class);
        $serializer->method("unserialize")->willReturn([
            "transactionId" => "provider-123",
            "status" => "completed",
            "amount" => "90.00",
            "originalAmount" => "100.00",
        ]);
        $authenticator = $this->createStub(WebhookAuthenticator::class);
        $authenticator->method("authenticate")->willReturn(true);

        $connectionManager = $this->createMock(ConnectionManager::class);
        $connectionManager
            ->expects(self::never())
            ->method("confirmWebhookConnection");
        $processor = $this->createMock(WebhookProcessor::class);
        $processor
            ->expects(self::once())
            ->method("process")
            ->with(self::callback(
                static fn($payload): bool =>
                     $payload->getTransactionId() === "provider-123" &&
                     $payload->getStatus() === "completed" &&
                     $payload->getAmountMinor() === 9000 &&
                     $payload->getOriginalAmountMinor() === 10000,
            ));

        self::assertSame(
            $result,
            (new Index(
                $request,
                $jsonFactory,
                $serializer,
                $authenticator,
                $connectionManager,
                $this->createStub(ConnectionConfigWriter::class),
                $processor,
            ))->execute(),
        );
    }

    public function testSignedCashbackUpdateReplacesCachedValues(): void
    {
        $rawBody = '{"updateCashbackInfo":true,"firstPurchaseAmount":5,"amount":2.5}';
        $request = $this->createStub(Http::class);
        $request->method("getContent")->willReturn($rawBody);
        $request->method("getHeader")->willReturn("signature");

        $result = $this->createMock(Json::class);
        $result
            ->expects(self::once())
            ->method("setData")
            ->with([
                "success" => true,
                "message" => "Cashback information updated",
            ])
            ->willReturnSelf();
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method("create")->willReturn($result);
        $serializer = $this->createStub(JsonSerializer::class);
        $serializer->method("unserialize")->willReturn([
            "updateCashbackInfo" => true,
            "firstPurchaseAmount" => 5,
            "amount" => 2.5,
        ]);
        $authenticator = $this->createMock(WebhookAuthenticator::class);
        $authenticator
            ->expects(self::once())
            ->method("authenticate")
            ->with($rawBody, "signature")
            ->willReturn(true);
        $writer = $this->createMock(ConnectionConfigWriter::class);
        $writer
            ->expects(self::once())
            ->method("replaceCashbackData")
            ->with([
                "first_purchase_amount" => 5.0,
                "standard_amount" => 2.5,
            ]);
        $processor = $this->createMock(WebhookProcessor::class);
        $processor->expects(self::never())->method("process");

        self::assertSame(
            $result,
            (new Index(
                $request,
                $jsonFactory,
                $serializer,
                $authenticator,
                $this->createStub(ConnectionManager::class),
                $writer,
                $processor,
            ))->execute(),
        );
    }

    public function testSignedCashbackUpdateDefaultsMissingAmountsToZero(): void
    {
        $request = $this->createStub(Http::class);
        $request->method("getContent")->willReturn(
            '{"updateCashbackInfo":true}',
        );
        $request->method("getHeader")->willReturn("signature");
        $result = $this->createStub(Json::class);
        $result->method("setData")->willReturnSelf();
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method("create")->willReturn($result);
        $serializer = $this->createStub(JsonSerializer::class);
        $serializer->method("unserialize")->willReturn([
            "updateCashbackInfo" => true,
        ]);
        $authenticator = $this->createStub(WebhookAuthenticator::class);
        $authenticator->method("authenticate")->willReturn(true);
        $writer = $this->createMock(ConnectionConfigWriter::class);
        $writer
            ->expects(self::once())
            ->method("replaceCashbackData")
            ->with([
                "first_purchase_amount" => 0.0,
                "standard_amount" => 0.0,
            ]);

        (new Index(
            $request,
            $jsonFactory,
            $serializer,
            $authenticator,
            $this->createStub(ConnectionManager::class),
            $writer,
            $this->createStub(WebhookProcessor::class),
        ))->execute();
    }

    public function testUnsignedCashbackUpdateDoesNotChangeCachedValues(): void
    {
        $request = $this->createStub(Http::class);
        $request->method("getContent")->willReturn(
            '{"updateCashbackInfo":true,"amount":5}',
        );
        $request->method("getHeader")->willReturn("invalid");
        $result = $this->createMock(Json::class);
        $result
            ->expects(self::once())
            ->method("setHttpResponseCode")
            ->with(401)
            ->willReturnSelf();
        $result->method("setData")->willReturnSelf();
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method("create")->willReturn($result);
        $authenticator = $this->createStub(WebhookAuthenticator::class);
        $authenticator->method("authenticate")->willReturn(false);
        $writer = $this->createMock(ConnectionConfigWriter::class);
        $writer->expects(self::never())->method("replaceCashbackData");

        (new Index(
            $request,
            $jsonFactory,
            $this->createStub(JsonSerializer::class),
            $authenticator,
            $this->createStub(ConnectionManager::class),
            $writer,
            $this->createStub(WebhookProcessor::class),
        ))->execute();
    }
}
