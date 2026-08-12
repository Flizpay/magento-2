<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Api\TransactionRequestBuilder;
use FlizPay\Payment\Service\Payment\CreateTransactionService;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use FlizPay\Payment\Service\Payment\InitiationFailureHandler;
use FlizPay\Payment\Service\Api\TransactionCreationException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\EncryptorInterface;
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

        $attempt = $this->createMock(PaymentAttempt::class);
        $saveCount = 0;
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository
            ->method("getByOrderId")
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException());
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
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder
            ->expects(self::exactly(2))
            ->method("getUrl")
            ->with(
                self::anything(),
                self::callback(
                    static fn(array $parameters): bool =>
                        array_keys($parameters) === ["token"],
                ),
            )
            ->willReturn("https://shop.test/return");

        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("createTransaction")
            ->with(
                ["source" => "plugin"],
                self::callback(
                    static fn(string $key): bool =>
                        preg_match('/^[a-f0-9]{32}$/', $key) === 1,
                ),
            )
            ->willReturnCallback(
                static function () use (&$saveCount): array {
                    self::assertSame(1, $saveCount);

                    return [
                        "transaction_id" => "provider-123",
                        "redirect_url" => "https://secure.flizpay.de/pay",
                    ];
                },
            );

        $service = new CreateTransactionService(
            $config,
            $repository,
            $requestBuilder,
            $apiClient,
            $urlBuilder,
            $this->createStub(InitiationFailureHandler::class),
            $this->encryptor(),
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
            $this->encryptor(),
        ))->execute($order);
    }

    public function testCreatedAttemptReturnsStoredRedirectWithoutApiCall(): void
    {
        [$order, $attempt, $config, $repository, $requestBuilder, $urlBuilder] =
            $this->dependencies();
        self::assertInstanceOf(MockObject::class, $attempt);
        $attempt->method("getData")->willReturnMap([
            ["attempt_id", null, "0123456789abcdef0123456789abcdef"],
            ["creation_state", null, "created"],
            ["encrypted_success_url", null, "encrypted-success"],
            ["encrypted_failure_url", null, "encrypted-failure"],
            ["encrypted_redirect_url", null, "encrypted-context"],
        ]);
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository->method("getByOrderId")->with(42)->willReturn($attempt);
        $repository->expects(self::never())->method("create");
        $repository->expects(self::never())->method("save");
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("createTransaction");

        self::assertSame(
            "https://secure.flizpay.de/pay",
            (new CreateTransactionService(
                $config,
                $repository,
                $requestBuilder,
                $apiClient,
                $urlBuilder,
                $this->createStub(InitiationFailureHandler::class),
                $this->encryptor(),
            ))->execute($order),
        );
    }

    public function testAmbiguousAttemptReplaysWithSameKeyAndUrls(): void
    {
        [$order, $attempt, $config, $repository, $requestBuilder, $urlBuilder] =
            $this->dependencies();
        $attemptId = "0123456789abcdef0123456789abcdef";
        self::assertInstanceOf(MockObject::class, $attempt);
        self::assertInstanceOf(MockObject::class, $requestBuilder);
        $attempt->method("getData")->willReturnMap([
            ["attempt_id", null, $attemptId],
            ["creation_state", null, "ambiguous"],
            ["encrypted_success_url", null, "encrypted-success"],
            ["encrypted_failure_url", null, "encrypted-failure"],
            ["encrypted_redirect_url", null, null],
        ]);
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository->method("getByOrderId")->with(42)->willReturn($attempt);
        $repository->expects(self::never())->method("create");
        $requestBuilder
            ->expects(self::once())
            ->method("build")
            ->with(
                $order,
                $attemptId,
                "https://shop.test/success",
                "https://shop.test/failure",
            )
            ->willReturn(["source" => "plugin"]);
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient
            ->expects(self::once())
            ->method("createTransaction")
            ->with(["source" => "plugin"], $attemptId)
            ->willReturn([
                "transaction_id" => "provider-123",
                "redirect_url" => "https://secure.flizpay.de/pay",
            ]);

        self::assertSame(
            "https://secure.flizpay.de/pay",
            (new CreateTransactionService(
                $config,
                $repository,
                $requestBuilder,
                $apiClient,
                $urlBuilder,
                $this->createStub(InitiationFailureHandler::class),
                $this->encryptor(),
            ))->execute($order),
        );
    }

    public function testConcurrentInsertLoadsWinningAttempt(): void
    {
        [$order, $attempt, $config, $repository, $requestBuilder, $urlBuilder] =
            $this->dependencies();
        $winner = $this->createMock(PaymentAttempt::class);
        $winnerId = "0123456789abcdef0123456789abcdef";
        $winner->method("getData")->willReturnMap([
            ["attempt_id", null, $winnerId],
            ["creation_state", null, "created"],
            ["encrypted_success_url", null, "encrypted-success"],
            ["encrypted_failure_url", null, "encrypted-failure"],
            ["encrypted_redirect_url", null, "encrypted-context"],
        ]);
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method("getByOrderId")
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \Magento\Framework\Exception\NoSuchEntityException()),
                $winner,
            );
        $repository->method("create")->willReturn($attempt);
        $repository
            ->expects(self::once())
            ->method("save")
            ->willThrowException(new \RuntimeException("duplicate order"));
        $apiClient = $this->createMock(FlizPayApiClient::class);
        $apiClient->expects(self::never())->method("createTransaction");

        self::assertSame(
            "https://secure.flizpay.de/pay",
            (new CreateTransactionService(
                $config,
                $repository,
                $requestBuilder,
                $apiClient,
                $urlBuilder,
                $this->createStub(InitiationFailureHandler::class),
                $this->encryptor(),
            ))->execute($order),
        );
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
        $attempt = $this->createMock(PaymentAttempt::class);
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository
            ->method("getByOrderId")
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException());
        $repository->method("create")->willReturn($attempt);
        $requestBuilder = $this->createMock(TransactionRequestBuilder::class);
        $requestBuilder->method("build")->willReturn(["source" => "plugin"]);
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method("getUrl")->willReturn("https://shop.test/return");

        return [$order, $attempt, $config, $repository, $requestBuilder, $urlBuilder];
    }

    private function encryptor(): EncryptorInterface
    {
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method("encrypt")->willReturn("encrypted-context");
        $encryptor->method("decrypt")->willReturnMap([
            ["encrypted-success", "https://shop.test/success"],
            ["encrypted-failure", "https://shop.test/failure"],
            ["encrypted-context", "https://secure.flizpay.de/pay"],
        ]);

        return $encryptor;
    }
}
