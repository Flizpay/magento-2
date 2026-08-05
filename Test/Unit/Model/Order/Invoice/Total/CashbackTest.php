<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Model\Order\Invoice\Total;

use FlizPay\Payment\Model\Order\Invoice\Total\Cashback;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection;
use PHPUnit\Framework\TestCase;

class CashbackTest extends TestCase
{
    public function testSubtractsCashbackFromInvoice(): void
    {
        $order = $this->createStub(Order::class);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 5.0],
            ["base_flizpay_cashback_amount", null, 5.0],
            ["flizpay_shipping_cashback_amount", null, 0.0],
            ["base_flizpay_shipping_cashback_amount", null, 0.0],
        ]);
        $invoiceCollection = $this->createStub(Collection::class);
        $invoiceCollection->method("getSize")->willReturn(0);
        $order->method("getInvoiceCollection")->willReturn($invoiceCollection);
        $order->method("getAllVisibleItems")->willReturn([]);
        $order->method("getTaxAmount")->willReturn(0.0);
        $order->method("getBaseTaxAmount")->willReturn(0.0);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method("getOrder")->willReturn($order);
        $invoice->method("getDiscountAmount")->willReturn(0.0);
        $invoice->method("getBaseDiscountAmount")->willReturn(0.0);
        $invoice->method("getGrandTotal")->willReturn(50.0);
        $invoice->method("getBaseGrandTotal")->willReturn(50.0);
        $invoice->method("getAllItems")->willReturn([
            $this->createStub(Invoice\Item::class),
        ]);
        $invoice->expects(self::once())->method("setDiscountAmount")->with(-5.0);
        $invoice->expects(self::once())->method("setBaseDiscountAmount")->with(-5.0);
        $invoice->expects(self::once())->method("setGrandTotal")->with(45.0);
        $invoice->expects(self::once())->method("setBaseGrandTotal")->with(45.0);
        $invoice->expects(self::exactly(2))->method("setData");

        (new Cashback())->collect($invoice);

        self::addToAssertionCount(1);
    }

    public function testDoesNotApplyShippingCashbackTwice(): void
    {
        $order = $this->createStub(Order::class);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 3.86],
            ["base_flizpay_cashback_amount", null, 3.86],
            ["flizpay_shipping_cashback_amount", null, 0.50],
            ["base_flizpay_shipping_cashback_amount", null, 0.50],
        ]);
        $invoiceCollection = $this->createStub(Collection::class);
        $invoiceCollection->method("getSize")->willReturn(0);
        $order->method("getInvoiceCollection")->willReturn($invoiceCollection);
        $order->method("getAllVisibleItems")->willReturn([]);
        $order->method("getTaxAmount")->willReturn(0.0);
        $order->method("getBaseTaxAmount")->willReturn(0.0);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method("getOrder")->willReturn($order);
        $invoice->method("getAllItems")->willReturn([
            $this->createStub(Invoice\Item::class),
        ]);
        $invoice->method("getDiscountAmount")->willReturn(-0.50);
        $invoice->method("getBaseDiscountAmount")->willReturn(-0.50);
        $invoice->method("getGrandTotal")->willReturn(38.10);
        $invoice->method("getBaseGrandTotal")->willReturn(38.10);
        $invoice->expects(self::once())->method("setDiscountAmount")->with(-3.86);
        $invoice->expects(self::once())->method("setBaseDiscountAmount")->with(-3.86);
        $invoice->expects(self::once())->method("setGrandTotal")->with(34.74);
        $invoice->expects(self::once())->method("setBaseGrandTotal")->with(34.74);

        (new Cashback())->collect($invoice);
    }

    public function testDoesNotApplyReducedVatTwice(): void
    {
        $item = $this->createStub(OrderItem::class);
        $item->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 5.83],
            ["base_flizpay_cashback_amount", null, 5.83],
        ]);
        $item->method("getTaxPercent")->willReturn(19.0);
        $item->method("getTaxAmount")->willReturn(8.38);
        $item->method("getBaseTaxAmount")->willReturn(8.38);

        $order = $this->createStub(Order::class);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 5.83],
            ["base_flizpay_cashback_amount", null, 5.83],
            ["flizpay_shipping_cashback_amount", null, 0.0],
            ["base_flizpay_shipping_cashback_amount", null, 0.0],
        ]);
        $order->method("getTaxAmount")->willReturn(8.38);
        $order->method("getBaseTaxAmount")->willReturn(8.38);
        $order->method("getAllVisibleItems")->willReturn([$item]);
        $invoiceCollection = $this->createStub(Collection::class);
        $invoiceCollection->method("getSize")->willReturn(0);
        $order->method("getInvoiceCollection")->willReturn($invoiceCollection);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method("getOrder")->willReturn($order);
        $invoice->method("getAllItems")->willReturn([
            $this->createStub(Invoice\Item::class),
        ]);
        $invoice->method("getDiscountAmount")->willReturn(0.0);
        $invoice->method("getBaseDiscountAmount")->willReturn(0.0);
        $invoice->method("getGrandTotal")->willReturn(57.38);
        $invoice->method("getBaseGrandTotal")->willReturn(57.38);
        $invoice->expects(self::once())->method("setDiscountAmount")->with(-4.90);
        $invoice->expects(self::once())->method("setBaseDiscountAmount")->with(-4.90);
        $invoice->expects(self::once())->method("setGrandTotal")->with(52.48);
        $invoice->expects(self::once())->method("setBaseGrandTotal")->with(52.48);

        (new Cashback())->collect($invoice);
    }

    public function testDoesNotApplyTaxableShippingVatReductionTwice(): void
    {
        $item = $this->createStub(OrderItem::class);
        $item->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 2.38],
            ["base_flizpay_cashback_amount", null, 2.38],
        ]);
        $item->method("getTaxPercent")->willReturn(19.0);
        $item->method("getTaxAmount")->willReturn(3.42);
        $item->method("getBaseTaxAmount")->willReturn(3.42);

        $order = $this->createStub(Order::class);
        $order->method("getData")->willReturnMap([
            ["flizpay_cashback_amount", null, 2.98],
            ["base_flizpay_cashback_amount", null, 2.98],
            ["flizpay_shipping_cashback_amount", null, 0.60],
            ["base_flizpay_shipping_cashback_amount", null, 0.60],
        ]);
        $order->method("getTaxAmount")->willReturn(4.27);
        $order->method("getBaseTaxAmount")->willReturn(4.27);
        $order->method("getShippingTaxAmount")->willReturn(0.85);
        $order->method("getBaseShippingTaxAmount")->willReturn(0.85);
        $order->method("getShippingInclTax")->willReturn(5.95);
        $order->method("getBaseShippingInclTax")->willReturn(5.95);
        $order->method("getShippingAmount")->willReturn(5.00);
        $order->method("getBaseShippingAmount")->willReturn(5.00);
        $order->method("getAllVisibleItems")->willReturn([$item]);
        $invoiceCollection = $this->createStub(Collection::class);
        $invoiceCollection->method("getSize")->willReturn(0);
        $order->method("getInvoiceCollection")->willReturn($invoiceCollection);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method("getOrder")->willReturn($order);
        $invoice->method("getAllItems")->willReturn([
            $this->createStub(Invoice\Item::class),
        ]);
        $invoice->method("getDiscountAmount")->willReturn(-0.60);
        $invoice->method("getBaseDiscountAmount")->willReturn(-0.60);
        $invoice->method("getGrandTotal")->willReturn(28.67);
        $invoice->method("getBaseGrandTotal")->willReturn(28.67);
        $invoice->expects(self::once())->method("setDiscountAmount")->with(-2.50);
        $invoice->expects(self::once())->method("setBaseDiscountAmount")->with(-2.50);
        $invoice->expects(self::once())->method("setGrandTotal")->with(26.77);
        $invoice->expects(self::once())->method("setBaseGrandTotal")->with(26.77);

        (new Cashback())->collect($invoice);
    }
}
