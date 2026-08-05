<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use FlizPay\Payment\Service\Payment\PaymentStateMapper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentStateMapperTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function nonterminalStates(): array
    {
        return ["pending" => ["pending"], "processing" => ["processing"]];
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    #[DataProvider("nonterminalStates")]
    public function testNonterminalStateNeverInvoices(string $status): void
    {
        [$order, $attempt] = $this->prepareOrder("provider-$status");

        Bootstrap::getObjectManager()
            ->get(PaymentStateMapper::class)
            ->apply("provider-$status", $status);

        $order = Bootstrap::getObjectManager()
            ->create(Order::class)
            ->load($order->getId());
        self::assertSame(Order::STATE_PENDING_PAYMENT, $order->getState());
        self::assertSame(0, $order->getInvoiceCollection()->getSize());
        self::assertSame(
            $status,
            Bootstrap::getObjectManager()
                ->get(PaymentAttemptRepository::class)
                ->getByOrderId((int) $order->getId())
                ->getData("provider_status"),
        );
    }

    /** @return array<string, array{string}> */
    public static function terminalFailureStates(): array
    {
        return ["failed" => ["failed"], "canceled" => ["canceled"]];
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    #[DataProvider("terminalFailureStates")]
    public function testTerminalFailureCancelsAndRestoresQuote(string $status): void
    {
        [$order, $attempt] = $this->prepareOrder("provider-$status");
        $quoteId = (int) $order->getQuoteId();
        $objectManager = Bootstrap::getObjectManager();
        $quoteRepository = $objectManager->get(CartRepositoryInterface::class);
        if ($quoteId === 0) {
            $quote = $objectManager->create(\Magento\Quote\Model\Quote::class);
            $quote->setStoreId((int) $order->getStoreId());
            $quote->setIsActive(false);
            $quoteRepository->save($quote);
            $quoteId = (int) $quote->getId();
            $order->setQuoteId($quoteId);
            $objectManager->get(OrderRepositoryInterface::class)->save($order);
            $attempt->setData("quote_id", $quoteId);
            $objectManager
                ->get(PaymentAttemptRepository::class)
                ->save($attempt);
        } else {
            $quote = $quoteRepository->get($quoteId);
        }
        $quote->setIsActive(false);
        $quoteRepository->save($quote);

        $objectManager
            ->get(PaymentStateMapper::class)
            ->apply("provider-$status", $status);

        $order = $objectManager->create(Order::class)->load($order->getId());
        self::assertSame(Order::STATE_CANCELED, $order->getState());
        self::assertSame(0, $order->getInvoiceCollection()->getSize());
        self::assertTrue((bool) $quoteRepository->get($quoteId)->getIsActive());
        self::assertSame(
            $status,
            $objectManager
                ->get(PaymentAttemptRepository::class)
                ->getByOrderId((int) $order->getId())
                ->getData("provider_status"),
        );
    }

    /** @return array{Order, \FlizPay\Payment\Model\PaymentAttempt} */
    private function prepareOrder(string $providerTransactionId): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId("100000001");
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->getPayment()->setMethod("flizpay");
        $objectManager->get(OrderRepositoryInterface::class)->save($order);

        $repository = $objectManager->get(PaymentAttemptRepository::class);
        $attempt = $repository->create([
            "attempt_id" => "attempt-" . substr(hash("sha256", $providerTransactionId), 0, 16),
            "order_id" => (int) $order->getId(),
            "order_increment_id" => (string) $order->getIncrementId(),
            "quote_id" => $order->getQuoteId(),
            "store_id" => (int) $order->getStoreId(),
            "provider_transaction_id" => $providerTransactionId,
            "expected_amount_minor" => (int) round((float) $order->getGrandTotal() * 100),
            "currency" => (string) $order->getOrderCurrencyCode(),
            "provider_status" => "pending",
            "creation_state" => "created",
            "return_token_hash" => hash("sha256", $providerTransactionId),
        ]);
        $repository->save($attempt);

        return [$order, $attempt];
    }
}
