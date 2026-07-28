<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Gateway\Command;

use FlizPay\Payment\Gateway\Command\InitializeCommand;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class InitializeCommandTest extends TestCase
{
    public function testOrderIsLeftPendingWithoutPaymentMutation(): void
    {
        $stateObject = new DataObject();

        (new InitializeCommand())->execute(["stateObject" => $stateObject]);

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
