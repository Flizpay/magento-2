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

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * Resolves an opaque return token to its locally bound Magento order.
 */
class ReturnContextValidator
{
    public function __construct(
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @throws NoSuchEntityException
     */
    public function validate(string $token, int $storeId): ReturnContext
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw NoSuchEntityException::singleField("return_token", "invalid");
        }

        $tokenHash = hash("sha256", $token);
        $attempt = $this->attemptRepository->getByReturnTokenHash($tokenHash);

        if (
            !hash_equals(
                (string) $attempt->getData("return_token_hash"),
                $tokenHash,
            )
        ) {
            throw NoSuchEntityException::singleField("return_token", "invalid");
        }

        $expiresAt = (string) $attempt->getData("expires_at");

        if ($expiresAt !== "" && strtotime($expiresAt) < time()) {
            throw NoSuchEntityException::singleField("return_token", "expired");
        }

        $order = $this->orderRepository->get(
            (int) $attempt->getData("order_id"),
        );
        $payment = $order instanceof Order ? $order->getPayment() : null;

        if (
            !$order instanceof Order ||
            !$payment instanceof Payment ||
            (int) $attempt->getData("store_id") !== $storeId ||
            (int) $order->getStoreId() !== $storeId ||
            (string) $attempt->getData("order_increment_id") !==
                (string) $order->getIncrementId() ||
            $payment->getMethod() !== ConfigInterface::METHOD_CODE
        ) {
            throw NoSuchEntityException::singleField("return_token", "invalid");
        }

        return new ReturnContext($attempt, $order);
    }
}
