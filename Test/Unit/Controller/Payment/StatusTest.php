<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Payment;

use FlizPay\Payment\Controller\Payment\Status;
use FlizPay\Payment\Service\Payment\ReturnContext;
use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private const TOKEN = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

    public function testStatusEndpointAcceptsGetOnly(): void
    {
        self::assertTrue(
            is_subclass_of(Status::class, HttpGetActionInterface::class),
        );
        self::assertFalse(
            is_subclass_of(Status::class, HttpPostActionInterface::class),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function settlementStates(): array
    {
        return [
            "pending" => ["pending"],
            "complete" => ["complete"],
            "failed" => ["failed"],
        ];
    }

    #[DataProvider("settlementStates")]
    public function testStatusExposesOnlyLocalSettlementState(
        string $expected,
    ): void {
        $context = $this->createMock(ReturnContext::class);
        $context->method("getPublicStatus")->willReturn($expected);
        $context->expects(self::never())->method("getOrder");

        $json = $this->json();
        $json
            ->expects(self::once())
            ->method("setData")
            ->with(["status" => $expected])
            ->willReturnSelf();
        $json->expects(self::never())->method("setHttpResponseCode");

        self::assertSame($json, $this->controller($context, $json)->execute());
    }

    public function testInvalidTokenReturnsGenericNotFound(): void
    {
        $json = $this->json();
        $json
            ->expects(self::once())
            ->method("setHttpResponseCode")
            ->with(404)
            ->willReturnSelf();
        $json
            ->expects(self::once())
            ->method("setData")
            ->with(self::callback(
                static fn(array $data): bool => !isset($data["status"]),
            ))
            ->willReturnSelf();

        self::assertSame($json, $this->controller(null, $json)->execute());
    }

    /**
     * @return Json&MockObject
     */
    private function json(): Json
    {
        $json = $this->createMock(Json::class);
        $json
            ->expects(self::once())
            ->method("setHeader")
            ->with("Cache-Control", "no-store, private", true)
            ->willReturnSelf();

        return $json;
    }

    private function controller(?ReturnContext $context, Json $json): Status
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method("getParam")->with("token")->willReturn(self::TOKEN);

        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method("create")->willReturn($json);

        $validator = $this->createMock(ReturnContextValidator::class);
        if ($context === null) {
            $validator
                ->method("validate")
                ->willThrowException(
                    NoSuchEntityException::singleField(
                        "return_token",
                        "invalid",
                    ),
                );
        } else {
            $validator
                ->method("validate")
                ->with(self::TOKEN, 1)
                ->willReturn($context);
        }

        $store = $this->createStub(StoreInterface::class);
        $store->method("getId")->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method("getStore")->willReturn($store);

        return new Status($request, $jsonFactory, $validator, $storeManager);
    }
}
