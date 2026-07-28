<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Model\Method\Adapter;
use Magento\Framework\DataObject;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class PaymentMethodTest extends TestCase
{
    public function testConfiguredAdapterInitializesPendingPaymentOnly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $method = $objectManager
            ->get(PaymentHelper::class)
            ->getMethodInstance("flizpay");
        $order = $objectManager->create(Order::class);
        $payment = $objectManager->create(Payment::class);
        $payment->setOrder($order);
        $payment->setMethod("flizpay");
        $method->setInfoInstance($payment);
        $stateObject = new DataObject();

        $method->initialize("authorize", $stateObject);

        self::assertInstanceOf(Adapter::class, $method);
        self::assertSame(
            Order::STATE_PENDING_PAYMENT,
            $stateObject->getData("state"),
        );
        self::assertSame(
            Order::STATE_PENDING_PAYMENT,
            $stateObject->getData("status"),
        );
        self::assertFalse($stateObject->getData("is_notified"));
        self::assertFalse($payment->getIsTransactionPending());
        self::assertSame(0, $order->getInvoiceCollection()->getSize());
    }
}
