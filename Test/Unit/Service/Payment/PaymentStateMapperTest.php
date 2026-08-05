<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Payment;

use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use FlizPay\Payment\Service\Payment\PaymentStateMapper;
use FlizPay\Payment\Service\Payment\TerminalFailureHandler;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentStateMapperTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function nonterminalStates(): array
    {
        return ["pending" => ["pending"], "processing" => ["processing"]];
    }

    #[DataProvider("nonterminalStates")]
    public function testRecordsNonterminalState(string $state): void
    {
        $attempt = $this->createMock(PaymentAttempt::class);
        $attempt->method("getData")->with("provider_status")->willReturn(null);
        $attempt->expects(self::once())->method("setData")->with("provider_status", $state);
        $repository = $this->createMock(PaymentAttemptRepository::class);
        $repository->method("getByProviderTransactionId")->willReturn($attempt);
        $repository->expects(self::once())->method("save")->with($attempt);

        $this->mapper($repository)->apply("provider-123", $state);
    }

    public function testDispatchesCompletion(): void
    {
        $attempt = $this->attemptWithStatus("pending");
        $repository = $this->repositoryReturning($attempt);
        $completed = $this->createMock(CompletedPaymentHandler::class);
        $completed->expects(self::once())
            ->method("execute")
            ->with("provider-123", 10000, 9000, "EUR", "100000001");

        $this->mapper($repository, $completed)->apply(
            "provider-123",
            "completed",
            9000,
            10000,
            "EUR",
            "100000001",
        );
    }

    #[DataProvider("failureStates")]
    public function testDispatchesTerminalFailure(string $state): void
    {
        $attempt = $this->attemptWithStatus("processing");
        $repository = $this->repositoryReturning($attempt);
        $terminal = $this->createMock(TerminalFailureHandler::class);
        $terminal->expects(self::once())->method("execute")->with($attempt, $state);

        $this->mapper($repository, null, $terminal)->apply("provider-123", $state);
    }

    /** @return array<string, array{string}> */
    public static function failureStates(): array
    {
        return ["failed" => ["failed"], "canceled" => ["canceled"]];
    }

    public function testRejectsTransitionFromTerminalState(): void
    {
        $this->expectException(LocalizedException::class);
        $this->mapper($this->repositoryReturning($this->attemptWithStatus("failed")))
            ->apply("provider-123", "completed");
    }

    public function testRejectsCompletionWithoutAmounts(): void
    {
        $this->expectException(LocalizedException::class);

        $this->mapper(
            $this->repositoryReturning($this->attemptWithStatus("pending")),
        )->apply("provider-123", "completed");
    }

    private function mapper(
        PaymentAttemptRepository $repository,
        ?CompletedPaymentHandler $completed = null,
        ?TerminalFailureHandler $terminal = null,
    ): PaymentStateMapper {
        return new PaymentStateMapper(
            $repository,
            $completed ?? $this->createStub(CompletedPaymentHandler::class),
            $terminal ?? $this->createStub(TerminalFailureHandler::class),
        );
    }

    private function attemptWithStatus(?string $status): PaymentAttempt
    {
        $attempt = $this->createStub(PaymentAttempt::class);
        $attempt->method("getData")->with("provider_status")->willReturn($status);
        return $attempt;
    }

    private function repositoryReturning(PaymentAttempt $attempt): PaymentAttemptRepository
    {
        $repository = $this->createStub(PaymentAttemptRepository::class);
        $repository->method("getByProviderTransactionId")->willReturn($attempt);
        return $repository;
    }
}
