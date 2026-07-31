<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReturnContextValidatorTest extends TestCase
{
    private const TOKEN = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

    /**
     * @return array<string, array{string}>
     */
    public static function malformedTokens(): array
    {
        return [
            "empty" => [""],
            "too short" => ["abc"],
            "too long" => [str_repeat("A", 44)],
            "invalid characters" => [str_repeat("A", 42) . "+"],
        ];
    }

    #[DataProvider("malformedTokens")]
    public function testMalformedTokenNeverReachesStorage(string $token): void
    {
        $attempts = $this->createMock(PaymentAttemptRepository::class);
        $attempts->expects(self::never())->method("getByReturnTokenHash");
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->expects(self::never())->method("get");

        $this->expectException(NoSuchEntityException::class);
        (new ReturnContextValidator($attempts, $orders))->validate($token, 1);
    }

    public function testValidTokenReturnsBoundContext(): void
    {
        $validator = new ReturnContextValidator(
            $this->attemptRepository($this->attempt()),
            $this->orderRepository($this->order()),
        );

        $context = $validator->validate(self::TOKEN, 1);

        self::assertSame(7, (int) $context->getOrder()->getEntityId());
    }

    public function testCrossStoreTokenIsRejected(): void
    {
        $validator = new ReturnContextValidator(
            $this->attemptRepository($this->attempt()),
            $this->orderRepository($this->order()),
        );

        $this->expectException(NoSuchEntityException::class);
        $validator->validate(self::TOKEN, 2);
    }

    public function testForeignPaymentMethodIsRejected(): void
    {
        $validator = new ReturnContextValidator(
            $this->attemptRepository($this->attempt()),
            $this->orderRepository($this->order("checkmo")),
        );

        $this->expectException(NoSuchEntityException::class);
        $validator->validate(self::TOKEN, 1);
    }

    public function testMismatchedIncrementIdIsRejected(): void
    {
        $validator = new ReturnContextValidator(
            $this->attemptRepository($this->attempt("200000042")),
            $this->orderRepository($this->order()),
        );

        $this->expectException(NoSuchEntityException::class);
        $validator->validate(self::TOKEN, 1);
    }

    private function attempt(string $incrementId = "100000042"): PaymentAttempt
    {
        $attempt = $this->createMock(PaymentAttempt::class);
        $attempt->method("getData")->willReturnMap([
            ["return_token_hash", null, hash("sha256", self::TOKEN)],
            ["order_id", null, 7],
            ["order_increment_id", null, $incrementId],
            ["store_id", null, 1],
        ]);

        return $attempt;
    }

    private function order(string $method = "flizpay"): Order
    {
        $payment = $this->createMock(Payment::class);
        $payment->method("getMethod")->willReturn($method);

        $order = $this->createMock(Order::class);
        $order->method("getEntityId")->willReturn(7);
        $order->method("getPayment")->willReturn($payment);
        $order->method("getStoreId")->willReturn(1);
        $order->method("getIncrementId")->willReturn("100000042");

        return $order;
    }

    private function attemptRepository(
        PaymentAttempt $attempt,
    ): PaymentAttemptRepository {
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository
            ->method("getByReturnTokenHash")
            ->with(hash("sha256", self::TOKEN))
            ->willReturn($attempt);

        return $repository;
    }

    private function orderRepository(Order $order): OrderRepositoryInterface
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->method("get")->with(7)->willReturn($order);

        return $repository;
    }
}
