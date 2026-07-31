<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Service\Api\CreatedTransaction;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Api\TransactionRequestBuilder;
use FlizPay\Payment\Service\Payment\CreateTransactionService;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use FlizPay\Payment\Service\Payment\InitiationFailureHandler;
use FlizPay\Payment\Service\Api\TransactionCreationException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CreateTransactionServiceTest extends TestCase
{
    public function testPersistsAttemptBeforeSingleProviderRequest(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isActive")->willReturn(true);
        $config->method("isConnected")->willReturn(true);

        $payment = $this->createStub(Payment::class);
        $payment->method("getMethod")->willReturn("flizpay");
        $order = $this->createStub(Order::class);
        $order->method("getPayment")->willReturn($payment);
        $order->method("getState")->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->method("getStoreId")->willReturn(1);
        $order->method("getEntityId")->willReturn(42);
        $order->method("getIncrementId")->willReturn("100000042");
        $order->method("getQuoteId")->willReturn(12);
        $order->method("getGrandTotal")->willReturn("10.00");
        $order->method("getOrderCurrencyCode")->willReturn("EUR");

        $attempt = $this->createStub(PaymentAttempt::class);
        $saveCount = 0;
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository
            ->expects(self::once())
            ->method("create")
            ->with(self::callback(
                static fn(array $data): bool =>
                    $data["order_id"] === 42 &&
                    $data["expected_amount_minor"] === 1000 &&
                    $data["creation_state"] === "creating" &&
                    strlen($data["return_token_hash"]) === 64,
            ))
            ->willReturn($attempt);
        $repository
            ->expects(self::exactly(2))
            ->method("save")
            ->willReturnCallback(
                static function (PaymentAttempt $saved) use (&$saveCount): PaymentAttempt {
                    $saveCount++;

                    return $saved;
                },
            );

        $requestBuilder = $this->createStub(TransactionRequestBuilder::class);
        $requestBuilder->method("build")->willReturn(["source" => "plugin"]);
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method("getUrl")->willReturn("https://shop.test/return");

        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("createTransaction")
            ->with(["source" => "plugin"])
            ->willReturnCallback(
                static function () use (&$saveCount): CreatedTransaction {
                    self::assertSame(1, $saveCount);

                    return CreatedTransaction::fromResponse([
                        "transactionId" => "provider-123",
                        "redirectUrl" => "https://secure.flizpay.de/pay",
                    ]);
                },
            );

        $service = new CreateTransactionService(
            $config,
            $repository,
            $requestBuilder,
            $apiClient,
            $urlBuilder,
            $this->createStub(InitiationFailureHandler::class),
        );

        self::assertSame(
            "https://secure.flizpay.de/pay",
            $service->execute($order),
        );
        self::assertSame(2, $saveCount);
    }

    public function testAmbiguousCreationFailureIsRecordedWithoutRetry(): void
    {
        [$order, $attempt, $config, $repository, $requestBuilder, $urlBuilder] =
            $this->dependencies();
        $repository->expects(self::once())->method("save")->with($attempt);
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("createTransaction")
            ->willThrowException(
                new TransactionCreationException(
                    TransactionCreationException::API_TRANSPORT_ERROR,
                    false,
                ),
            );
        $failureHandler = $this->createMock(InitiationFailureHandler::class);
        $failureHandler
            ->expects(self::once())
            ->method("handleAmbiguous")
            ->with($attempt, TransactionCreationException::API_TRANSPORT_ERROR);

        $this->expectException(TransactionCreationException::class);
        (new CreateTransactionService(
            $config,
            $repository,
            $requestBuilder,
            $apiClient,
            $urlBuilder,
            $failureHandler,
        ))->execute($order);
    }

    /**
     * @return array{Order, PaymentAttempt, ConfigInterface, PaymentAttemptRepository&MockObject, TransactionRequestBuilder, UrlInterface}
     */
    private function dependencies(): array
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isActive")->willReturn(true);
        $config->method("isConnected")->willReturn(true);
        $payment = $this->createStub(Payment::class);
        $payment->method("getMethod")->willReturn("flizpay");
        $order = $this->createStub(Order::class);
        $order->method("getPayment")->willReturn($payment);
        $order->method("getState")->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->method("getStoreId")->willReturn(1);
        $order->method("getEntityId")->willReturn(42);
        $order->method("getIncrementId")->willReturn("100000042");
        $order->method("getQuoteId")->willReturn(12);
        $order->method("getGrandTotal")->willReturn("10.00");
        $order->method("getOrderCurrencyCode")->willReturn("EUR");
        $attempt = $this->createStub(PaymentAttempt::class);
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository->method("create")->willReturn($attempt);
        $requestBuilder = $this->createStub(TransactionRequestBuilder::class);
        $requestBuilder->method("build")->willReturn(["source" => "plugin"]);
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method("getUrl")->willReturn("https://shop.test/return");

        return [$order, $attempt, $config, $repository, $requestBuilder, $urlBuilder];
    }
}
