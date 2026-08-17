<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Model\Order\Creditmemo\Total;

use FlizPay\Payment\Model\Order\Creditmemo\Total\Cashback;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class CashbackTest extends TestCase
{
    public function testReconcilesFullMemoToPaidAmount(): void
    {
        $order = $this->order("flizpay", 25.98, 0.0, 1.37);
        $invoice = $this->createMock(Creditmemo::class);
        $invoice->method("getOrder")->willReturn($order);
        $invoice->method("getGrandTotal")->willReturn(26.93);
        $invoice->method("getBaseGrandTotal")->willReturn(26.93);
        $invoice->method("getDiscountAmount")->willReturn(-0.30);
        $invoice->method("getBaseDiscountAmount")->willReturn(-0.30);
        $invoice->expects(self::once())->method("setDiscountAmount")->with(-1.25);
        $invoice
            ->expects(self::once())
            ->method("setBaseDiscountAmount")
            ->with(-1.25);
        $invoice->expects(self::once())->method("setGrandTotal")->with(25.98);
        $invoice
            ->expects(self::once())
            ->method("setBaseGrandTotal")
            ->with(25.98);
        $invoice->expects(self::exactly(3))->method("setData");

        (new Cashback())->collect($invoice);
    }

    public function testCollectedMemoIsIdempotent(): void
    {
        $order = $this->order("flizpay", 25.98, 0.0, 1.37);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method("getOrder")->willReturn($order);
        $creditmemo
            ->method("getData")
            ->with("flizpay_full_refund")
            ->willReturn(1);
        $creditmemo->expects(self::never())->method("setGrandTotal");
        $creditmemo->expects(self::never())->method("setData");

        (new Cashback())->collect($creditmemo);
    }

    public function testNonFlizpayMemoIsUnaffected(): void
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo
            ->method("getOrder")
            ->willReturn($this->order("checkmo", 25.98, 0.0, 1.37));
        $creditmemo->expects(self::never())->method("setGrandTotal");
        $creditmemo->expects(self::never())->method("setData");

        (new Cashback())->collect($creditmemo);
    }

    public function testZeroCashbackStillMarksFullRefund(): void
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo
            ->method("getOrder")
            ->willReturn($this->order("flizpay", 25.98, 0.0, 0.0));
        $creditmemo->method("getGrandTotal")->willReturn(25.98);
        $creditmemo->method("getBaseGrandTotal")->willReturn(25.98);
        $creditmemo->expects(self::exactly(3))->method("setData");
        $creditmemo->expects(self::never())->method("setGrandTotal");

        (new Cashback())->collect($creditmemo);
    }

    private function order(
        string $method,
        float $paid,
        float $refunded,
        float $cashback,
    ): Order {
        $payment = $this->createStub(Payment::class);
        $payment->method("getMethod")->willReturn($method);
        $order = $this->createStub(Order::class);
        $order->method("getPayment")->willReturn($payment);
        $order->method("getTotalPaid")->willReturn($paid);
        $order->method("getBaseTotalPaid")->willReturn($paid);
        $order->method("getTotalRefunded")->willReturn($refunded);
        $order->method("getBaseTotalRefunded")->willReturn($refunded);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, $cashback],
            ["base_flizpay_cashback_amount", null, $cashback],
        ]);

        return $order;
    }
}
