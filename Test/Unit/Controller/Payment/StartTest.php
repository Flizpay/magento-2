<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Payment;

use FlizPay\Payment\Controller\Payment\Start;
use FlizPay\Payment\Service\Payment\CreateTransactionService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class StartTest extends TestCase
{
    public function testStartHandoffAcceptsPostOnly(): void
    {
        self::assertTrue(is_subclass_of(Start::class, HttpPostActionInterface::class));
        self::assertFalse(is_subclass_of(Start::class, HttpGetActionInterface::class));
    }

    public function testSuccessfulStartReturnsSeeOtherRedirect(): void
    {
        $session = $this->createMock(CheckoutSession::class);
        $session
            ->expects(self::once())
            ->method("getData")
            ->with("last_order_id")
            ->willReturn(42);
        $order = $this->createStub(Order::class);
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders
            ->expects(self::once())
            ->method("get")
            ->with(42)
            ->willReturn($order);

        $service = $this->createMock(CreateTransactionService::class);
        $service
            ->expects(self::once())
            ->method("execute")
            ->with($order)
            ->willReturn("https://secure.flizpay.de/pay");

        $redirect = $this->createMock(Redirect::class);
        $redirect
            ->expects(self::once())
            ->method("setHttpResponseCode")
            ->with(303)
            ->willReturnSelf();
        $redirect
            ->expects(self::once())
            ->method("setUrl")
            ->with("https://secure.flizpay.de/pay")
            ->willReturnSelf();
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method("create")->willReturn($redirect);

        self::assertSame(
            $redirect,
            (new Start(
                $redirectFactory,
                $this->createStub(RawFactory::class),
                $session,
                $orders,
                $service,
            ))->execute(),
        );
    }
}
