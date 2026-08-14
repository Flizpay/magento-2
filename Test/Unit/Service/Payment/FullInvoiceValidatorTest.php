<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Service\Payment\FullInvoiceValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection;
use PHPUnit\Framework\TestCase;

class FullInvoiceValidatorTest extends TestCase
{
    public function testFullFirstInvoiceIsAccepted(): void
    {
        $order = $this->order("flizpay", 0, [$this->item(10, 2.0)]);

        (new FullInvoiceValidator())->validate($order, [10 => 2]);

        self::addToAssertionCount(1);
    }

    public function testEmptyQuantitiesPrepareAllRemainingItems(): void
    {
        $order = $this->order("flizpay", 0, [$this->item(10, 2.0)]);

        (new FullInvoiceValidator())->validate($order);

        self::addToAssertionCount(1);
    }

    public function testPartialQuantityIsRejected(): void
    {
        $this->expectException(LocalizedException::class);

        (new FullInvoiceValidator())->validate(
            $this->order("flizpay", 0, [$this->item(10, 2.0)]),
            [10 => 1],
        );
    }

    public function testOmittedItemIsRejected(): void
    {
        $this->expectException(LocalizedException::class);

        (new FullInvoiceValidator())->validate(
            $this->order("flizpay", 0, [
                $this->item(10, 1.0),
                $this->item(11, 1.0),
            ]),
            [10 => 1],
        );
    }

    public function testExistingInvoiceIsRejected(): void
    {
        $this->expectException(LocalizedException::class);

        (new FullInvoiceValidator())->validate(
            $this->order("flizpay", 1, [$this->item(10, 1.0)]),
        );
    }

    public function testNonFlizpayOrderIsUnaffected(): void
    {
        $order = $this->order("checkmo", 1, [$this->item(10, 2.0)]);

        (new FullInvoiceValidator())->validate($order, [10 => 1]);

        self::addToAssertionCount(1);
    }

    public function testChildCanUseRequestedParentQuantity(): void
    {
        $child = $this->item(11, 2.0, 10);
        $order = $this->order("flizpay", 0, [$child]);

        (new FullInvoiceValidator())->validate($order, [10 => 2]);

        self::addToAssertionCount(1);
    }

    /**
     * @param Item[] $items
     */
    private function order(
        string $method,
        int $invoiceCount,
        array $items,
    ): Order {
        $payment = $this->createStub(Payment::class);
        $payment->method("getMethod")->willReturn($method);
        $invoices = $this->createStub(Collection::class);
        $invoices->method("getSize")->willReturn($invoiceCount);

        $order = $this->createStub(Order::class);
        $order->method("getPayment")->willReturn($payment);
        $order->method("getInvoiceCollection")->willReturn($invoices);
        $order->method("getAllItems")->willReturn($items);

        return $order;
    }

    private function item(int $id, float $qty, ?int $parentId = null): Item
    {
        $item = $this->createStub(Item::class);
        $item->method("getId")->willReturn($id);
        $item->method("getParentItemId")->willReturn($parentId);
        $item->method("getQtyToInvoice")->willReturn($qty);
        $item->method("isDummy")->willReturn(false);

        return $item;
    }
}
