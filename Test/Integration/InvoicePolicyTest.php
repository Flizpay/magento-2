<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class InvoicePolicyTest extends TestCase
{
    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testPartialInvoiceIsRejectedBeforeTotalsCollection(): void
    {
        $order = $this->flizpayOrder();
        $item = $order->getAllVisibleItems()[0];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("FLIZpay supports one full invoice only.");

        Bootstrap::getObjectManager()
            ->get(InvoiceService::class)
            ->prepareInvoice($order, [
                (int) $item->getId() => (float) $item->getQtyToInvoice() / 2,
            ]);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSecondInvoicePreparationIsRejected(): void
    {
        $order = $this->flizpayOrder();
        $invoiceService = Bootstrap::getObjectManager()->get(
            InvoiceService::class,
        );
        $invoice = $invoiceService->prepareInvoice($order);
        $invoice->register();
        Bootstrap::getObjectManager()
            ->get(InvoiceRepositoryInterface::class)
            ->save($invoice);
        Bootstrap::getObjectManager()
            ->get(OrderRepositoryInterface::class)
            ->save($order);
        $order = Bootstrap::getObjectManager()
            ->create(Order::class)
            ->load($order->getId());

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("FLIZpay supports one full invoice only.");

        $invoiceService->prepareInvoice($order);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testNonFlizpayPartialInvoiceIsUnaffected(): void
    {
        $order = Bootstrap::getObjectManager()->create(Order::class);
        $order->loadByIncrementId("100000001");
        $item = $order->getAllVisibleItems()[0];

        $invoice = Bootstrap::getObjectManager()
            ->get(InvoiceService::class)
            ->prepareInvoice($order, [
                (int) $item->getId() => (float) $item->getQtyToInvoice() / 2,
            ]);

        self::assertEquals(
            (float) $item->getQtyToInvoice() / 2,
            (float) $invoice->getTotalQty(),
        );
    }

    private function flizpayOrder(): Order
    {
        $order = Bootstrap::getObjectManager()->create(Order::class);
        $order->loadByIncrementId("100000001");
        $order->getPayment()->setMethod("flizpay");

        return $order;
    }
}
