<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Controller\Payment;

use FlizPay\Payment\Controller\Payment\Start;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use PHPUnit\Framework\TestCase;

class StartTest extends TestCase
{
    public function testStartHandoffAcceptsPostOnly(): void
    {
        self::assertTrue(is_subclass_of(Start::class, HttpPostActionInterface::class));
        self::assertFalse(is_subclass_of(Start::class, HttpGetActionInterface::class));
    }
}
