<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Api;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
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
}
