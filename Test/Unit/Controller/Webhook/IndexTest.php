<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Webhook;

use FlizPay\Payment\Controller\Webhook\Index;
use FlizPay\Payment\Service\Connection\ConnectionManager;
use FlizPay\Payment\Service\Webhook\WebhookAuthenticator;
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
            ))->execute(),
        );
    }
}
