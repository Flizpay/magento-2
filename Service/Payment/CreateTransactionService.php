<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Api\TransactionRequestBuilder;
use FlizPay\Payment\Service\Api\TransactionCreationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;

/**
 * Creates the first provider transaction for a persisted Magento order.
 */
class CreateTransactionService
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly TransactionRequestBuilder $requestBuilder,
        private readonly FlizPayApiClient $apiClient,
        private readonly UrlInterface $urlBuilder,
        private readonly InitiationFailureHandler $failureHandler,
    ) {}

    /**
     * @throws LocalizedException
     *
     */
    public function execute(Order $order): string
    {
        if (
            $order->getPayment()->getMethod() !==
                ConfigInterface::METHOD_CODE ||
            $order->getState() !== Order::STATE_PENDING_PAYMENT ||
            !$this->config->isActive((int) $order->getStoreId()) ||
            !$this->config->isConnected()
        ) {
            throw new LocalizedException(
                __("The FLIZpay payment cannot be started."),
            );
        }

        $attemptId = bin2hex(random_bytes(16));
        $returnToken = $this->createReturnToken();

        $attempt = $this->attemptRepository->create([
            "attempt_id" => $attemptId,
            "order_id" => (int) $order->getEntityId(),
            "order_increment_id" => (string) $order->getIncrementId(),
            "quote_id" => $order->getQuoteId(),
            "store_id" => (int) $order->getStoreId(),
            "expected_amount_minor" => $this->toMinorUnits(
                (string) $order->getGrandTotal(),
            ),
            "currency" => strtoupper((string) $order->getOrderCurrencyCode()),
            "creation_state" => "creating",
            "return_token_hash" => hash("sha256", $returnToken),
        ]);

        $urlParameters = [
            "token" => $returnToken,
            "_secure" => true,
            "_scope" => (int) $order->getStoreId(),
        ];

        $successUrl = $this->urlBuilder->getUrl(
            "flizpay/payment/success",
            $urlParameters,
        );

        $failureUrl = $this->urlBuilder->getUrl(
            "flizpay/payment/failure",
            $urlParameters,
        );

        if (
            !str_starts_with(strtolower($successUrl), "https://") ||
            !str_starts_with(strtolower($failureUrl), "https://")
        ) {
            throw new LocalizedException(
                __("Magento secure base URL must use HTTPS."),
            );
        }

        $request = $this->requestBuilder->build(
            $order,
            $attemptId,
            $successUrl,
            $failureUrl,
        );

        $this->attemptRepository->save($attempt);

        try {
            $createdTransaction = $this->apiClient->createTransaction($request);
        } catch (TransactionCreationException $exception) {
            if ($exception->isDefinite()) {
                $this->failureHandler->handleDefinite(
                    $attempt,
                    $order,
                    $exception->getSafeErrorCode(),
                );
            } else {
                $this->failureHandler->handleAmbiguous(
                    $attempt,
                    $exception->getSafeErrorCode(),
                );
            }

            throw $exception;
        }
        $attempt->setData(
            "provider_transaction_id",
            $createdTransaction->getTransactionId(),
        );
        $attempt->setData("provider_status", "pending");
        $attempt->setData("creation_state", "created");
        $attempt->setData("safe_error_code", null);
        $this->attemptRepository->save($attempt);

        return $createdTransaction->getRedirectUrl();
    }

    private function createReturnToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
    }

    private function toMinorUnits(string $amount): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $amount)) {
            throw new \InvalidArgumentException(
                "Invalid Magento order amount.",
            );
        }

        return (int) round((float) $amount * 100);
    }
}
