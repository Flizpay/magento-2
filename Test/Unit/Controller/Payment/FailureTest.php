<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Payment;

use FlizPay\Payment\Controller\Payment\Failure;
use FlizPay\Payment\Service\Payment\ReturnContext;
use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FailureTest extends TestCase
{
    private const TOKEN = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

    public function testFailureReturnAcceptsGetOnly(): void
    {
        self::assertTrue(
            is_subclass_of(Failure::class, HttpGetActionInterface::class),
        );
        self::assertFalse(
            is_subclass_of(Failure::class, HttpPostActionInterface::class),
        );
    }

    public function testUnsettledReturnRendersFailurePage(): void
    {
        $context = $this->createMock(ReturnContext::class);
        $context->method("isComplete")->willReturn(false);

        $page = $this->createStub(Page::class);
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method("create")->willReturn($page);

        $controller = $this->controller($context, pageFactory: $pageFactory);

        self::assertSame($page, $controller->execute());
    }

    public function testSettledOrderRedirectsToSuccessReturn(): void
    {
        $context = $this->createMock(ReturnContext::class);
        $context->method("isComplete")->willReturn(true);

        $redirect = $this->createMock(Redirect::class);
        $redirect
            ->expects(self::once())
            ->method("setPath")
            ->with("flizpay/payment/success", [
                "token" => self::TOKEN,
                "_secure" => true,
            ])
            ->willReturnSelf();
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method("create")->willReturn($redirect);

        $controller = $this->controller(
            $context,
            redirectFactory: $redirectFactory,
        );

        self::assertSame($redirect, $controller->execute());
    }

    public function testInvalidTokenReturnsGenericNotFound(): void
    {
        $raw = $this->createMock(Raw::class);
        $raw
            ->expects(self::once())
            ->method("setHttpResponseCode")
            ->with(404)
            ->willReturnSelf();
        $raw->expects(self::once())->method("setContents")->willReturnSelf();
        $rawFactory = $this->createStub(RawFactory::class);
        $rawFactory->method("create")->willReturn($raw);

        $controller = $this->controller(null, rawFactory: $rawFactory);

        self::assertSame($raw, $controller->execute());
    }

    private function controller(
        ?ReturnContext $context,
        ?PageFactory $pageFactory = null,
        ?RawFactory $rawFactory = null,
        ?RedirectFactory $redirectFactory = null,
    ): Failure {
        $request = $this->createMock(RequestInterface::class);
        $request->method("getParam")->with("token")->willReturn(self::TOKEN);

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
        $response = $this->createMock(HttpResponse::class);
        $response->expects(self::once())->method("setNoCacheHeaders");

        return new Failure(
            $request,
            $pageFactory ?? $this->createStub(PageFactory::class),
            $rawFactory ?? $this->createStub(RawFactory::class),
            $redirectFactory ?? $this->createStub(RedirectFactory::class),
            $validator,
            $storeManager,
            $response,
        );
    }
}
