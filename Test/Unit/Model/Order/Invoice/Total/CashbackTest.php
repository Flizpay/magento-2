<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Model\Order\Invoice\Total;

use FlizPay\Payment\Model\Order\Invoice\Total\Cashback;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\TestCase;

class CashbackTest extends TestCase
{
    public function testSubtractsCashbackFromInvoice(): void
    {
        $order = $this->createStub(Order::class);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 5.0],
            ["base_flizpay_cashback_amount", null, 5.0],
        ]);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method("getOrder")->willReturn($order);
        $invoice->method("getDiscountAmount")->willReturn(0.0);
        $invoice->method("getBaseDiscountAmount")->willReturn(0.0);
        $invoice->method("getGrandTotal")->willReturn(50.0);
        $invoice->method("getBaseGrandTotal")->willReturn(50.0);
        $invoice->expects(self::once())->method("setDiscountAmount")->with(-5.0);
        $invoice->expects(self::once())->method("setBaseDiscountAmount")->with(-5.0);
        $invoice->expects(self::once())->method("setGrandTotal")->with(45.0);
        $invoice->expects(self::once())->method("setBaseGrandTotal")->with(45.0);
        $invoice->expects(self::exactly(2))->method("setData");

        (new Cashback())->collect($invoice);

        self::addToAssertionCount(1);
    }
}
