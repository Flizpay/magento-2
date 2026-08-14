<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay.
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 * @link    https://flizpay.de
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Model\PaymentAttemptFactory;
use FlizPay\Payment\Model\ResourceModel\PaymentAttempt as PaymentAttemptResource;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Creates, saves, and retrieves payment attempts.
 */
class PaymentAttemptRepository
{
    /**
     * @param PaymentAttemptFactory $paymentAttemptFactory
     * @param PaymentAttemptResource $paymentAttemptResource
     */
    public function __construct(
        private readonly PaymentAttemptFactory $paymentAttemptFactory,
        private readonly PaymentAttemptResource $paymentAttemptResource,
    ) {}

    /**
     * Create an unpersisted payment attempt populated with the supplied data.
     *
     * @param array<string, mixed> $data Payment attempt data.
     * @return PaymentAttempt
     */
    public function create(array $data = []): PaymentAttempt
    {
        $paymentAttempt = $this->paymentAttemptFactory->create();
        $paymentAttempt->setData($data);

        return $paymentAttempt;
    }

    /**
     * Persist a payment attempt.
     *
     * @param PaymentAttempt $paymentAttempt
     * @return PaymentAttempt
     */
    public function save(PaymentAttempt $paymentAttempt): PaymentAttempt
    {
        $this->paymentAttemptResource->save($paymentAttempt);

        return $paymentAttempt;
    }

    /**
     * Load the payment attempt bound to an order.
     *
     * @param int $orderId
     * @return PaymentAttempt
     * @throws NoSuchEntityException
     */
    public function getByOrderId(int $orderId): PaymentAttempt
    {
        return $this->getByField("order_id", $orderId);
    }

    /**
     * Load the payment attempt bound to a provider transaction.
     *
     * @param string $providerTransactionId
     * @return PaymentAttempt
     * @throws NoSuchEntityException
     */
    public function getByProviderTransactionId(
        string $providerTransactionId,
    ): PaymentAttempt {
        return $this->getByField(
            "provider_transaction_id",
            $providerTransactionId,
        );
    }

    /**
     * Load the payment attempt bound to a SHA-256 return-token hash.
     *
     * @param string $returnTokenHash
     * @return PaymentAttempt
     * @throws NoSuchEntityException
     */
    public function getByReturnTokenHash(
        string $returnTokenHash,
    ): PaymentAttempt {
        return $this->getByField("return_token_hash", $returnTokenHash);
    }

    /**
     * Load a payment attempt by a schema field.
     *
     * @param string $field
     * @param int|string $value
     * @return PaymentAttempt
     * @throws NoSuchEntityException
     */
    private function getByField(
        string $field,
        int|string $value,
    ): PaymentAttempt {
        $paymentAttempt = $this->create();
        $this->paymentAttemptResource->load($paymentAttempt, $value, $field);

        if ($paymentAttempt->getId() === null) {
            throw NoSuchEntityException::singleField($field, $value);
        }

        return $paymentAttempt;
    }
}
