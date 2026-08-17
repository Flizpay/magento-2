<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\ViewModel\Adminhtml;

use FlizPay\Payment\ViewModel\Adminhtml\CreditmemoNotice;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreditmemoNoticeTest extends TestCase
{
    public function testOrderRequestShowsNoticeForFlizpay(): void
    {
        $viewModel = $this->viewModel(
            ["invoice_id" => null, "order_id" => 42],
            "flizpay",
        );

        self::assertTrue($viewModel->shouldShow());
    }

    public function testOrderRequestHidesNoticeForOtherPaymentMethod(): void
    {
        $viewModel = $this->viewModel(
            ["invoice_id" => null, "order_id" => 42],
            "checkmo",
        );

        self::assertFalse($viewModel->shouldShow());
    }

    public function testInvoiceRequestResolvesAuthoritativeOrder(): void
    {
        $viewModel = $this->viewModel(
            ["invoice_id" => 7, "order_id" => null],
            "flizpay",
            42,
        );

        self::assertTrue($viewModel->shouldShow());
    }

    public function testMismatchedInvoiceAndOrderRequestIsRejected(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->expects(self::never())->method("get");

        $viewModel = $this->viewModel(
            ["invoice_id" => 7, "order_id" => 99],
            "flizpay",
            42,
            $orders,
        );

        self::assertFalse($viewModel->shouldShow());
    }

    public function testMissingRequestIdentifiersHideNotice(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->expects(self::never())->method("get");

        $viewModel = $this->viewModel(
            ["invoice_id" => null, "order_id" => null],
            "flizpay",
            null,
            $orders,
        );

        self::assertFalse($viewModel->shouldShow());
    }

    public function testMissingEntityHidesNotice(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders
            ->method("get")
            ->willThrowException(new NoSuchEntityException());

        $viewModel = $this->viewModel(
            ["invoice_id" => null, "order_id" => 42],
            "flizpay",
            null,
            $orders,
        );

        self::assertFalse($viewModel->shouldShow());
    }

    /**
     * @param array{invoice_id: int|null, order_id: int|null} $params
     */
    private function viewModel(
        array $params,
        string $method,
        ?int $invoiceOrderId = null,
        ?OrderRepositoryInterface $orders = null,
    ): CreditmemoNotice {
        $request = $this->createStub(RequestInterface::class);
        $request->method("getParam")->willReturnMap([
            ["invoice_id", null, $params["invoice_id"]],
            ["order_id", null, $params["order_id"]],
        ]);

        $payment = $this->createStub(OrderPaymentInterface::class);
        $payment->method("getMethod")->willReturn($method);
        $order = $this->createStub(OrderInterface::class);
        $order->method("getPayment")->willReturn($payment);
        if ($orders === null) {
            $orders = $this->createStub(OrderRepositoryInterface::class);
            $orders->method("get")->willReturn($order);
        }

        $invoice = $this->createStub(InvoiceInterface::class);
        $invoice->method("getOrderId")->willReturn($invoiceOrderId);
        $invoices = $this->createStub(InvoiceRepositoryInterface::class);
        $invoices->method("get")->willReturn($invoice);

        return new CreditmemoNotice($request, $orders, $invoices);
    }
}
