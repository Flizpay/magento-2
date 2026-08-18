<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Gateway\Command;

use FlizPay\Payment\Gateway\Command\InitializeCommand;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class InitializeCommandTest extends TestCase
{
    public function testOrderIsLeftPendingWithoutPaymentMutation(): void
    {
        $stateObject = new DataObject();
        $order = $this->createMock(Order::class);
        $order
            ->expects(self::once())
            ->method("setCanSendNewEmailFlag")
            ->with(false);
        $payment = $this->createStub(Payment::class);
        $payment->method("getOrder")->willReturn($order);
        $paymentData = $this->createStub(PaymentDataObjectInterface::class);
        $paymentData->method("getPayment")->willReturn($payment);

        (new InitializeCommand())->execute([
            "stateObject" => $stateObject,
            "payment" => $paymentData,
        ]);

        self::assertSame(
            Order::STATE_PENDING_PAYMENT,
            $stateObject->getData("state"),
        );
        self::assertSame(
            Order::STATE_PENDING_PAYMENT,
            $stateObject->getData("status"),
        );
        self::assertFalse($stateObject->getData("is_notified"));
    }
}
