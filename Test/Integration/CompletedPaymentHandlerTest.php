<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CompletedPaymentHandlerTest extends TestCase
{
    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCompletedPaymentCreatesPaidInvoiceAndCapture(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId("100000001");
        self::assertNotNull($order->getId());

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->setOrderCurrencyCode("EUR");
        $order->setBaseCurrencyCode("EUR");
        $order->setBaseToOrderRate(1.0);
        $order->getPayment()->setMethod("flizpay");
        $orderItem = $order->getAllVisibleItems()[0];
        $orderItem->setTaxPercent(19.0);
        $orderItem->setRowTotal(49.0);
        $orderItem->setBaseRowTotal(49.0);
        $orderItem->setRowTotalInclTax(58.31);
        $orderItem->setBaseRowTotalInclTax(58.31);
        $orderItem->setTaxAmount(9.31);
        $orderItem->setBaseTaxAmount(9.31);
        $order->setSubtotal(49.0);
        $order->setBaseSubtotal(49.0);
        $order->setSubtotalInclTax(58.31);
        $order->setBaseSubtotalInclTax(58.31);
        $order->setTaxAmount(9.31);
        $order->setBaseTaxAmount(9.31);
        $order->setGrandTotal(58.31);
        $order->setBaseGrandTotal(58.31);
        $originalAmountMinor = (int) round((float) $order->getGrandTotal() * 100);
        $finalAmountMinor = $originalAmountMinor - 583;
        $objectManager->get(OrderRepositoryInterface::class)->save($order);
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert(
            $resource->getTableName("sales_order_tax"),
            [
                "order_id" => (int) $order->getId(),
                "code" => "DE Standard VAT 19%",
                "title" => "DE Standard VAT 19%",
                "percent" => 19.0,
                "priority" => 0,
                "position" => 0,
                "amount" => 9.31,
                "base_amount" => 9.31,
                "base_real_amount" => 9.31,
                "process" => 0,
            ],
        );

        $repository = $objectManager->get(PaymentAttemptRepository::class);
        $repository->save($repository->create([
            "attempt_id" => "completed-attempt",
            "order_id" => (int) $order->getId(),
            "order_increment_id" => (string) $order->getIncrementId(),
            "quote_id" => $order->getQuoteId(),
            "store_id" => (int) $order->getStoreId(),
            "provider_transaction_id" => "provider-completed-123",
            "expected_amount_minor" => $originalAmountMinor,
            "currency" => (string) $order->getOrderCurrencyCode(),
            "creation_state" => "created",
            "return_token_hash" => hash("sha256", "completed-return"),
        ]));

        $objectManager
            ->get(CompletedPaymentHandler::class)
            ->execute(
                "provider-completed-123",
                $originalAmountMinor,
                $finalAmountMinor,
                "EUR",
                (string) $order->getIncrementId(),
            );

        $order = $objectManager->create(Order::class)->load($order->getId());
        self::assertSame(Order::STATE_PROCESSING, $order->getState());
        self::assertSame(1, $order->getInvoiceCollection()->getSize());
        self::assertFalse($order->canInvoice());
        self::assertEquals(5.83, (float) $order->getData("flizpay_cashback_amount"));
        self::assertEquals(8.38, (float) $order->getTaxAmount());
        self::assertEquals(
            $finalAmountMinor / 100,
            (float) $order->getGrandTotal(),
        );
        self::assertEquals(
            $finalAmountMinor / 100,
            (float) $order->getPayment()->getAmountOrdered(),
        );
        self::assertEquals(
            $finalAmountMinor / 100,
            (float) $order->getInvoiceCollection()->getFirstItem()->getGrandTotal(),
        );
        self::assertEquals(
            (float) $order->getGrandTotal(),
            (float) $order->getInvoiceCollection()->getFirstItem()->getGrandTotal(),
        );
        self::assertEquals(
            4.90,
            abs(
                (float) $order->getInvoiceCollection()->getFirstItem()->getDiscountAmount(),
            ),
        );
        self::assertEquals(
            -4.90,
            (float) $order->getInvoiceCollection()->getFirstItem()->getDiscountAmount(),
        );
        self::assertEquals(
            5.83,
            (float) $order->getInvoiceCollection()->getFirstItem()->getData(
                "flizpay_cashback_amount",
            ),
        );
        self::assertEquals(
            $finalAmountMinor / 100,
            (float) $order->getTotalPaid(),
        );
        self::assertEquals(
            $finalAmountMinor / 100,
            (float) $order->getTotalInvoiced(),
        );
        self::assertEquals(
            $finalAmountMinor / 100,
            (float) $order->getPayment()->getAmountPaid(),
        );
        self::assertSame(
            Invoice::STATE_PAID,
            (int) $order->getInvoiceCollection()->getFirstItem()->getState(),
        );
        self::assertEquals(
            8.38,
            (float) $connection->fetchOne(
                $connection->select()
                    ->from($resource->getTableName("sales_order_tax"), "amount")
                    ->where("order_id = ?", (int) $order->getId()),
            ),
        );
        self::assertSame(
            "completed",
            $repository
                ->getByProviderTransactionId("provider-completed-123")
                ->getData("provider_status"),
        );
        self::assertSame(1, (int) $order->getSendEmail());
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testExistingInvoicePreventsCompletion(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId("100000001");
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->setOrderCurrencyCode("EUR");
        $order->setBaseCurrencyCode("EUR");
        $order->getPayment()->setMethod("flizpay");
        $objectManager->get(OrderRepositoryInterface::class)->save($order);

        $invoice = $objectManager
            ->get(InvoiceService::class)
            ->prepareInvoice($order);
        $invoice->register();
        $objectManager->get(InvoiceRepositoryInterface::class)->save($invoice);
        $objectManager->get(OrderRepositoryInterface::class)->save($order);

        $amountMinor = (int) round((float) $order->getGrandTotal() * 100);
        $repository = $objectManager->get(PaymentAttemptRepository::class);
        $repository->save($repository->create([
            "attempt_id" => "existing-invoice-attempt",
            "order_id" => (int) $order->getId(),
            "order_increment_id" => (string) $order->getIncrementId(),
            "quote_id" => $order->getQuoteId(),
            "store_id" => (int) $order->getStoreId(),
            "provider_transaction_id" => "provider-existing-invoice",
            "expected_amount_minor" => $amountMinor,
            "currency" => "EUR",
            "creation_state" => "created",
            "return_token_hash" => hash("sha256", "existing-invoice-return"),
        ]));

        try {
            $objectManager
                ->get(CompletedPaymentHandler::class)
                ->execute(
                    "provider-existing-invoice",
                    $amountMinor,
                    $amountMinor,
                    "EUR",
                    (string) $order->getIncrementId(),
                );
            self::fail("Expected existing invoice validation to fail.");
        } catch (LocalizedException $exception) {
            self::assertSame(
                "FLIZpay payment has an unexpected existing invoice.",
                $exception->getMessage(),
            );
        }

        self::assertNotSame(
            "completed",
            $repository
                ->getByProviderTransactionId("provider-existing-invoice")
                ->getData("provider_status"),
        );
    }
}
