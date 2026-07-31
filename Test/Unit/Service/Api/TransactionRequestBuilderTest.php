<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Api;

use FlizPay\Payment\Service\Api\TransactionRequestBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;

class TransactionRequestBuilderTest extends TestCase
{
    public function testBuildsSupportedTransactionFieldsFromOrder(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method("getGrandTotal")->willReturn(10.1);
        $order->method("getOrderCurrencyCode")->willReturn("eur");
        $order->method("getIncrementId")->willReturn("100000123");
        $order->method("getEntityId")->willReturn(123);
        $order->method("getStoreId")->willReturn(4);
        $order->method("getCustomerEmail")->willReturn(" customer@example.test ");
        $order->method("getCustomerFirstname")->willReturn(" Ada ");
        $order->method("getCustomerLastname")->willReturn(" Lovelace ");

        $request = (new TransactionRequestBuilder())->build(
            $order,
            "attempt-456",
            "https://shop.test/flizpay/success",
            "https://shop.test/flizpay/failure",
        );

        self::assertSame([
            "amount" => "10.10",
            "currency" => "EUR",
            "externalId" => "100000123",
            "source" => "plugin",
            "successUrl" => "https://shop.test/flizpay/success",
            "failureUrl" => "https://shop.test/flizpay/failure",
            "customer" => [
                "email" => "customer@example.test",
                "firstName" => "Ada",
                "lastName" => "Lovelace",
            ],
            "metadata" => [
                "platform" => "magento",
                "magentoOrderId" => "123",
                "storeId" => "4",
                "attemptId" => "attempt-456",
            ],
        ], $request);
        self::assertArrayNotHasKey("shipping", $request);
        self::assertArrayNotHasKey("products", $request);
    }

    public function testOmitsEmptyCustomerFields(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method("getGrandTotal")->willReturn(1.0);
        $order->method("getOrderCurrencyCode")->willReturn("EUR");
        $order->method("getIncrementId")->willReturn("100000124");
        $order->method("getEntityId")->willReturn(124);
        $order->method("getStoreId")->willReturn(1);
        $order->method("getCustomerEmail")->willReturn("");
        $order->method("getCustomerFirstname")->willReturn(null);
        $order->method("getCustomerLastname")->willReturn("  ");

        $request = (new TransactionRequestBuilder())->build(
            $order,
            "attempt-789",
            "https://shop.test/success",
            "https://shop.test/failure",
        );

        self::assertSame("1.00", $request["amount"]);
        self::assertArrayNotHasKey("customer", $request);
    }

    public function testRejectsInvalidGrandTotal(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method("getGrandTotal")->willReturn(-1.0);
        $order->method("getOrderCurrencyCode")->willReturn("EUR");
        $order->method("getIncrementId")->willReturn("100000125");

        $this->expectException(\InvalidArgumentException::class);

        (new TransactionRequestBuilder())->build(
            $order,
            "attempt-999",
            "https://shop.test/success",
            "https://shop.test/failure",
        );
    }
}
