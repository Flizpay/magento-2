<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Service\Payment\CashbackAdjustmentService;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class CashbackAdjustmentServiceTest extends TestCase
{
    public function testAppliesCashbackToOrderAndPayment(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects(self::once())->method("setAmountOrdered")->with(90.0);
        $payment->expects(self::once())->method("setBaseAmountOrdered")->with(90.0);

        $order = $this->createMock(Order::class);
        $order->method("getDiscountAmount")->willReturn(-5.0);
        $order->method("getBaseDiscountAmount")->willReturn(-5.0);
        $order->method("getPayment")->willReturn($payment);
        $order->expects(self::once())->method("setDiscountAmount")->with(-15.0);
        $order->expects(self::once())->method("setBaseDiscountAmount")->with(-15.0);
        $order->expects(self::once())->method("setGrandTotal")->with(90.0);
        $order->expects(self::once())->method("setBaseGrandTotal")->with(90.0);

        (new CashbackAdjustmentService())->apply($order, 10000, 9000);
    }

    public function testRejectsFinalAmountAboveOriginalAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CashbackAdjustmentService())->apply(
            $this->createStub(Order::class),
            9000,
            10000,
        );
    }
}
