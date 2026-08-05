<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Service\Payment\CashbackAdjustmentService;
use FlizPay\Payment\Service\Payment\CashbackCalculator;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
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
        $item = $this->createMock(Item::class);
        $item->method("getParentItemId")->willReturn(0);
        $item->method("getRowTotalInclTax")->willReturn(100.0);
        $item->method("getTaxPercent")->willReturn(0.0);
        $item->method("getDiscountAmount")->willReturn(5.0);
        $item->method("getBaseDiscountAmount")->willReturn(5.0);
        $item->method("getTaxAmount")->willReturn(0.0);
        $item->method("getBaseTaxAmount")->willReturn(0.0);
        $order->method("getAllItems")->willReturn([$item]);
        $order->method("getShippingInclTax")->willReturn(0.0);
        $order->method("getBaseToOrderRate")->willReturn(1.0);
        $order->method("getTaxAmount")->willReturn(19.0);
        $order->method("getBaseTaxAmount")->willReturn(19.0);
        $order->method("getDiscountAmount")->willReturn(-5.0);
        $order->method("getBaseDiscountAmount")->willReturn(-5.0);
        $order->method("getPayment")->willReturn($payment);
        $order->expects(self::once())->method("setDiscountAmount")->with(-15.0);
        $order->expects(self::once())->method("setBaseDiscountAmount")->with(-15.0);
        $order->expects(self::once())->method("setGrandTotal")->with(90.0);
        $order->expects(self::once())->method("setBaseGrandTotal")->with(90.0);
        $order->expects(self::once())->method("setTaxAmount")->with(19.0);
        $order->expects(self::once())->method("setBaseTaxAmount")->with(19.0);

        (new CashbackAdjustmentService(new CashbackCalculator()))
            ->apply($order, 10000, 9000);
    }

    public function testReducesOrderTaxByAllocatedVat(): void
    {
        $item = $this->createMock(Item::class);
        $item->method("getParentItemId")->willReturn(0);
        $item->method("getRowTotalInclTax")->willReturn(58.31);
        $item->method("getTaxPercent")->willReturn(19.0);
        $item->method("getTaxAmount")->willReturn(9.31);
        $item->method("getBaseTaxAmount")->willReturn(9.31);
        $item->expects(self::once())->method("setTaxAmount")->with(8.38);
        $item->expects(self::once())->method("setBaseTaxAmount")->with(8.38);

        $order = $this->createMock(Order::class);
        $order->method("getAllItems")->willReturn([$item]);
        $order->method("getShippingInclTax")->willReturn(0.0);
        $order->method("getBaseToOrderRate")->willReturn(1.0);
        $order->method("getTaxAmount")->willReturn(9.31);
        $order->method("getBaseTaxAmount")->willReturn(9.31);
        $order->method("getDiscountAmount")->willReturn(0.0);
        $order->method("getBaseDiscountAmount")->willReturn(0.0);
        $order->expects(self::once())->method("setTaxAmount")->with(8.38);
        $order->expects(self::once())->method("setBaseTaxAmount")->with(8.38);

        (new CashbackAdjustmentService(new CashbackCalculator()))
            ->apply($order, 5831, 5248);
    }

    public function testReducesTaxOnTaxableShipping(): void
    {
        $item = $this->createMock(Item::class);
        $item->method("getParentItemId")->willReturn(0);
        $item->method("getRowTotalInclTax")->willReturn(23.80);
        $item->method("getTaxPercent")->willReturn(19.0);
        $item->method("getTaxAmount")->willReturn(3.80);
        $item->method("getBaseTaxAmount")->willReturn(3.80);
        $item->expects(self::once())->method("setTaxAmount")->with(3.42);
        $item->expects(self::once())->method("setBaseTaxAmount")->with(3.42);

        $order = $this->createMock(Order::class);
        $order->method("getAllItems")->willReturn([$item]);
        $order->method("getShippingInclTax")->willReturn(5.95);
        $order->method("getShippingAmount")->willReturn(5.00);
        $order->method("getShippingTaxAmount")->willReturn(0.95);
        $order->method("getBaseShippingTaxAmount")->willReturn(0.95);
        $order->method("getBaseToOrderRate")->willReturn(1.0);
        $order->method("getTaxAmount")->willReturn(4.75);
        $order->method("getBaseTaxAmount")->willReturn(4.75);
        $order->method("getDiscountAmount")->willReturn(0.0);
        $order->method("getBaseDiscountAmount")->willReturn(0.0);
        $order->method("getShippingDiscountAmount")->willReturn(0.0);
        $order->method("getBaseShippingDiscountAmount")->willReturn(0.0);
        $order->expects(self::once())->method("setShippingTaxAmount")->with(0.85);
        $order->expects(self::once())->method("setBaseShippingTaxAmount")->with(0.85);
        $order->expects(self::once())->method("setTaxAmount")->with(4.27);
        $order->expects(self::once())->method("setBaseTaxAmount")->with(4.27);
        $order->expects(self::exactly(6))->method("setData");

        (new CashbackAdjustmentService(new CashbackCalculator()))
            ->apply($order, 2975, 2677);
    }

    public function testRejectsFinalAmountAboveOriginalAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CashbackAdjustmentService(new CashbackCalculator()))->apply(
            $this->createStub(Order::class),
            9000,
            10000,
        );
    }
}
