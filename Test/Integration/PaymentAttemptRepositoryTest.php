<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class PaymentAttemptRepositoryTest extends TestCase
{
    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCreatesSavesAndLoadsAttemptByAllBindings(): void
    {
        $order = Bootstrap::getObjectManager()->create(Order::class);
        $order->loadByIncrementId("100000001");
        self::assertNotNull($order->getId());

        $returnTokenHash = hash("sha256", "return-token");
        $repository = $this->getRepository();
        $attempt = $repository->create([
            "attempt_id" => "attempt-1",
            "order_id" => (int) $order->getId(),
            "order_increment_id" => (string) $order->getIncrementId(),
            "quote_id" => $order->getQuoteId(),
            "store_id" => (int) $order->getStoreId(),
            "provider_transaction_id" => "provider-transaction-1",
            "expected_amount_minor" => 1000,
            "currency" => "EUR",
            "creation_state" => "created",
            "return_token_hash" => $returnTokenHash,
        ]);

        $repository->save($attempt);

        self::assertNotNull($attempt->getId());
        self::assertSame(
            $attempt->getId(),
            $repository->getByOrderId((int) $order->getId())->getId(),
        );
        self::assertSame(
            $attempt->getId(),
            $repository
                ->getByProviderTransactionId("provider-transaction-1")
                ->getId(),
        );
        self::assertSame(
            $attempt->getId(),
            $repository->getByReturnTokenHash($returnTokenHash)->getId(),
        );
    }

    /**
     * @magentoDbIsolation enabled
     */
    public function testThrowsWhenBindingsAreAbsent(): void
    {
        $missingBindings = [
            "order ID" => ["getByOrderId", 2147483647],
            "provider transaction ID" => [
                "getByProviderTransactionId",
                "missing-provider-transaction",
            ],
            "return token hash" => [
                "getByReturnTokenHash",
                hash("sha256", "missing-return-token"),
            ],
        ];

        foreach ($missingBindings as $binding => [$method, $value]) {
            try {
                $this->getRepository()->{$method}($value);
                self::fail(sprintf("Missing %s did not throw.", $binding));
            } catch (NoSuchEntityException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function getRepository(): PaymentAttemptRepository
    {
        return Bootstrap::getObjectManager()->get(
            PaymentAttemptRepository::class,
        );
    }
}
