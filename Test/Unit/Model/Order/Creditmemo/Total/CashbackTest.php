<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Model\Order\Creditmemo\Total;

use FlizPay\Payment\Model\Order\Creditmemo\Total\Cashback;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\TestCase;

class CashbackTest extends TestCase
{
    public function testSubtractsUnrefundedCashbackFromCreditmemo(): void
    {
        $order = $this->createStub(Order::class);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 5.0],
            ["base_flizpay_cashback_amount", null, 5.0],
            ["flizpay_cashback_refunded", null, 0.0],
            ["base_flizpay_cashback_refunded", null, 0.0],
        ]);

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method("getOrder")->willReturn($order);
        $creditmemo->method("getDiscountAmount")->willReturn(0.0);
        $creditmemo->method("getBaseDiscountAmount")->willReturn(0.0);
        $creditmemo->method("getGrandTotal")->willReturn(50.0);
        $creditmemo->method("getBaseGrandTotal")->willReturn(50.0);
        $creditmemo->expects(self::once())->method("setDiscountAmount")->with(-5.0);
        $creditmemo->expects(self::once())->method("setBaseDiscountAmount")->with(-5.0);
        $creditmemo->expects(self::once())->method("setGrandTotal")->with(45.0);
        $creditmemo->expects(self::once())->method("setBaseGrandTotal")->with(45.0);
        $creditmemo->expects(self::exactly(2))->method("setData");

        (new Cashback())->collect($creditmemo);

        self::addToAssertionCount(1);
    }
}
