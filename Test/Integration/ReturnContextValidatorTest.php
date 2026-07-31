<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ReturnContextValidatorTest extends TestCase
{
    private const TOKEN = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testReturnStaysPendingUntilWebhookSettlement(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $order = $this->prepareOrder();
        $storeId = (int) $order->getStoreId();

        $validator = $objectManager->get(ReturnContextValidator::class);
        $context = $validator->validate(self::TOKEN, $storeId);

        self::assertFalse($context->isComplete());
        self::assertSame(
            (int) $order->getId(),
            (int) $context->getOrder()->getEntityId(),
        );

        $objectManager
            ->get(CompletedPaymentHandler::class)
            ->execute("provider-return-123");

        self::assertTrue(
            $validator->validate(self::TOKEN, $storeId)->isComplete(),
        );
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCrossStoreTokenExposesNoOrderInformation(): void
    {
        $order = $this->prepareOrder();

        $this->expectException(NoSuchEntityException::class);
        Bootstrap::getObjectManager()
            ->get(ReturnContextValidator::class)
            ->validate(self::TOKEN, (int) $order->getStoreId() + 1);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testUnknownTokenIsRejected(): void
    {
        $this->prepareOrder();

        $this->expectException(NoSuchEntityException::class);
        Bootstrap::getObjectManager()
            ->get(ReturnContextValidator::class)
            ->validate(str_repeat("B", 43), 1);
    }

    private function prepareOrder(): Order
    {
        $objectManager = Bootstrap::getObjectManager();
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId("100000001");
        self::assertNotNull($order->getId());

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->getPayment()->setMethod("flizpay");
        $objectManager->get(OrderRepositoryInterface::class)->save($order);

        $repository = $objectManager->get(PaymentAttemptRepository::class);
        $repository->save($repository->create([
            "attempt_id" => "return-flow-attempt",
            "order_id" => (int) $order->getId(),
            "order_increment_id" => (string) $order->getIncrementId(),
            "quote_id" => $order->getQuoteId(),
            "store_id" => (int) $order->getStoreId(),
            "provider_transaction_id" => "provider-return-123",
            "expected_amount_minor" => (int) round(
                (float) $order->getGrandTotal() * 100,
            ),
            "currency" => (string) $order->getOrderCurrencyCode(),
            "creation_state" => "created",
            "return_token_hash" => hash("sha256", self::TOKEN),
        ]));

        return $order;
    }
}
