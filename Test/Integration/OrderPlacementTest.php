<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class OrderPlacementTest extends TestCase
{
    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     */
    public function testGuestOrderIsPersistedAsPendingPayment(): void
    {
        $order = $this->submitFixtureQuote();

        self::assertSame(Order::STATE_PENDING_PAYMENT, $order->getState());
        self::assertSame(Order::STATE_PENDING_PAYMENT, $order->getStatus());
        self::assertSame("flizpay", $order->getPayment()->getMethod());
        self::assertSame(0, $order->getInvoiceCollection()->getSize());
        self::assertFalse($order->getCanSendNewEmailFlag());
        self::assertNull($order->getSendEmail());
        self::assertNull($order->getEmailSent());
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     */
    public function testAuthenticatedOrderIsPersistedAsPendingPayment(): void
    {
        $quote = $this->loadFixtureQuote("test01");

        $order = $this->submitFixtureQuote($quote);

        self::assertSame(Order::STATE_PENDING_PAYMENT, $order->getState());
        self::assertSame(Order::STATE_PENDING_PAYMENT, $order->getStatus());
        self::assertSame("flizpay", $order->getPayment()->getMethod());
        self::assertSame(1, (int) $order->getCustomerId());
        self::assertSame(0, $order->getInvoiceCollection()->getSize());
        self::assertFalse($order->getCanSendNewEmailFlag());
        self::assertNull($order->getSendEmail());
        self::assertNull($order->getEmailSent());
    }

    private function submitFixtureQuote(?Quote $quote = null): Order
    {
        $quote ??= $this->loadFixtureQuote();
        $email = (string) ($quote->getCustomerEmail() ?: "guest@example.test");
        $quote->setCustomerEmail($email);
        $quote->getBillingAddress()->setEmail($email);
        $quote->setIsMultiShipping(false);
        $quote->getShippingAddress()->setEmail($email);
        $quote->getShippingAddress()
            ->setShippingMethod("flatrate_flatrate")
            ->setCollectShippingRates(true);
        $quote->getPayment()->setMethod("flizpay");
        $quote->collectTotals();
        $order = Bootstrap::getObjectManager()
            ->get(QuoteManagement::class)
            ->submit($quote);

        self::assertInstanceOf(Order::class, $order);
        self::assertNotNull($order->getId());

        return $order;
    }

    private function loadFixtureQuote(string $reservedOrderId = "guest_quote"): Quote
    {
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->load($reservedOrderId, "reserved_order_id");
        self::assertNotNull($quote->getId());

        return $quote;
    }
}
