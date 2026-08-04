<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
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
        $order->getPayment()->setMethod("flizpay");
        $originalAmountMinor = (int) round((float) $order->getGrandTotal() * 100);
        $finalAmountMinor = $originalAmountMinor - 1000;
        $objectManager->get(OrderRepositoryInterface::class)->save($order);

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
            );

        $order = $objectManager->create(Order::class)->load($order->getId());
        self::assertSame(Order::STATE_PROCESSING, $order->getState());
        self::assertSame(1, $order->getInvoiceCollection()->getSize());
        self::assertEquals(10.0, (float) $order->getData("flizpay_cashback_amount"));
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
            -10.0,
            (float) $order->getInvoiceCollection()->getFirstItem()->getDiscountAmount(),
        );
        self::assertEquals(
            10.0,
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
        self::assertSame(
            "completed",
            $repository
                ->getByProviderTransactionId("provider-completed-123")
                ->getData("provider_status"),
        );
    }
}
