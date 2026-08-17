<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Payment;

use FlizPay\Payment\Block\Payment\ReturnPage;
use FlizPay\Payment\Controller\Payment\Success;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use FlizPay\Payment\Service\Payment\ReturnContext;
use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SuccessTest extends TestCase
{
    private const TOKEN = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

    public function testSuccessReturnAcceptsGetOnly(): void
    {
        self::assertTrue(
            is_subclass_of(Success::class, HttpGetActionInterface::class),
        );
        self::assertFalse(
            is_subclass_of(Success::class, HttpPostActionInterface::class),
        );
    }

    public function testUnsettledReturnRendersPendingPageWithToken(): void
    {
        $context = $this->createMock(ReturnContext::class);
        $context->method("isComplete")->willReturn(false);

        $block = $this->createMock(ReturnPage::class);
        $block
            ->expects(self::once())
            ->method("setData")
            ->with("return_token", self::TOKEN);
        $layout = $this->createMock(LayoutInterface::class);
        $layout
            ->method("getBlock")
            ->with("flizpay.payment.pending")
            ->willReturn($block);
        $page = $this->createMock(Page::class);
        $page->method("getLayout")->willReturn($layout);
        $title = $this->createMock(Title::class);
        $title
            ->expects(self::once())
            ->method("set")
            ->with("Payment confirmation");
        $config = $this->createStub(Config::class);
        $config->method("getTitle")->willReturn($title);
        $page->method("getConfig")->willReturn($config);
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method("create")->willReturn($page);

        $controller = $this->controller($context, pageFactory: $pageFactory);

        self::assertSame($page, $controller->execute());
    }

    public function testSettledReturnRestoresSessionAndOpensNativeSuccess(): void
    {
        $order = $this->createMock(Order::class);
        $order->method("getQuoteId")->willReturn(11);
        $order->method("getEntityId")->willReturn(7);
        $order->method("getIncrementId")->willReturn("100000042");

        $context = $this->createMock(ReturnContext::class);
        $context->method("isComplete")->willReturn(true);
        $context->method("getOrder")->willReturn($order);

        $sessionCalls = [];
        $session = $this->checkoutSession($sessionCalls);

        $redirect = $this->createMock(Redirect::class);
        $redirect
            ->expects(self::once())
            ->method("setPath")
            ->with("checkout/onepage/success")
            ->willReturnSelf();
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method("create")->willReturn($redirect);

        $controller = $this->controller(
            $context,
            redirectFactory: $redirectFactory,
            checkoutSession: $session,
        );

        self::assertSame($redirect, $controller->execute());
        self::assertSame(
            [
                ["setLastQuoteId", [11]],
                ["setLastSuccessQuoteId", [11]],
                ["setLastOrderId", [7]],
                ["setLastRealOrderId", ["100000042"]],
            ],
            $sessionCalls,
        );
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

        $sessionCalls = [];
        $session = $this->checkoutSession($sessionCalls);

        $controller = $this->controller(
            null,
            rawFactory: $rawFactory,
            checkoutSession: $session,
        );

        self::assertSame($raw, $controller->execute());
        self::assertSame([], $sessionCalls);
    }

    /**
     * Record magic checkout-session setters invoked by the controller.
     *
     * @param array<int, array{string, array<int, mixed>}> $calls
     * @return CheckoutSession&MockObject
     */
    private function checkoutSession(array &$calls): CheckoutSession
    {
        $session = $this->createMock(CheckoutSession::class);
        $session
            ->method("__call")
            ->willReturnCallback(
                function (string $method, array $arguments) use (
                    &$calls,
                    $session,
                ) {
                    $calls[] = [$method, $arguments];

                    return $session;
                },
            );

        return $session;
    }

    private function controller(
        ?ReturnContext $context,
        ?PageFactory $pageFactory = null,
        ?RawFactory $rawFactory = null,
        ?RedirectFactory $redirectFactory = null,
        ?CheckoutSession $checkoutSession = null,
    ): Success {
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

        return new Success(
            $request,
            $pageFactory ?? $this->createStub(PageFactory::class),
            $rawFactory ?? $this->createStub(RawFactory::class),
            $redirectFactory ?? $this->createStub(RedirectFactory::class),
            $validator,
            $storeManager,
            $checkoutSession ?? $this->createMock(CheckoutSession::class),
            $response,
            $this->createStub(PaymentLogger::class),
        );
    }
}
