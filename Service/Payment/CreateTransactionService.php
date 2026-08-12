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
use FlizPay\Payment\Model\PaymentAttempt;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Api\TransactionRequestBuilder;
use FlizPay\Payment\Service\Api\TransactionCreationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Encryption\EncryptorInterface;
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
        private readonly EncryptorInterface $encryptor,
    ) {}

    /**
     * @throws LocalizedException
     *
     */
    public function execute(Order $order): string
    {
        if (!$this->canStartPayment($order)) {
            throw new LocalizedException(
                __("The FLIZpay payment cannot be started."),
            );
        }

        try {
            $attempt = $this->attemptRepository->getByOrderId(
                (int) $order->getEntityId(),
            );
            $attemptId = (string) $attempt->getData("attempt_id");

            $successUrl = $this->decryptAttemptUrl(
                $attempt,
                "encrypted_success_url",
            );
            $failureUrl = $this->decryptAttemptUrl(
                $attempt,
                "encrypted_failure_url",
            );
            $redirectUrl = $this->decryptAttemptUrl(
                $attempt,
                "encrypted_redirect_url",
                true,
            );

            if (
                (string) $attempt->getData("creation_state") === "created" &&
                $redirectUrl !== ""
            ) {
                return $redirectUrl;
            }
        } catch (NoSuchEntityException) {
            $attemptId = bin2hex(random_bytes(16));
            $returnToken = $this->createReturnToken();

            $urlParameters = [
                "token" => $returnToken,
            ];
            $successUrl = $this->urlBuilder->getUrl(
                "flizpay/payment/success",
                $urlParameters,
            );
            $failureUrl = $this->urlBuilder->getUrl(
                "flizpay/payment/failure",
                $urlParameters,
            );

            $attempt = $this->attemptRepository->create([
                "attempt_id" => $attemptId,
                "order_id" => (int) $order->getEntityId(),
                "order_increment_id" => (string) $order->getIncrementId(),
                "quote_id" => $order->getQuoteId(),
                "store_id" => (int) $order->getStoreId(),
                "expected_amount_minor" => $this->toMinorUnits(
                    (string) $order->getGrandTotal(),
                ),
                "currency" => strtoupper(
                    (string) $order->getOrderCurrencyCode(),
                ),
                "creation_state" => "creating",
                "return_token_hash" => hash("sha256", $returnToken),
            ]);

            $attempt->setData(
                "encrypted_success_url",
                $this->encryptor->encrypt($successUrl),
            );
            $attempt->setData(
                "encrypted_failure_url",
                $this->encryptor->encrypt($failureUrl),
            );

            try {
                $this->attemptRepository->save($attempt);
            } catch (\Throwable) {
                $attempt = $this->attemptRepository->getByOrderId(
                    (int) $order->getEntityId(),
                );
                $attemptId = (string) $attempt->getData("attempt_id");

                $successUrl = $this->decryptAttemptUrl(
                    $attempt,
                    "encrypted_success_url",
                );
                $failureUrl = $this->decryptAttemptUrl(
                    $attempt,
                    "encrypted_failure_url",
                );
                $redirectUrl = $this->decryptAttemptUrl(
                    $attempt,
                    "encrypted_redirect_url",
                    true,
                );

                if (
                    (string) $attempt->getData("creation_state") ===
                        "created" &&
                    $redirectUrl !== ""
                ) {
                    return $redirectUrl;
                }
            }
        }

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

        try {
            $createdTransaction = $this->apiClient->createTransaction(
                $request,
                $attemptId,
            );
        } catch (TransactionCreationException $exception) {
            if (
                $exception->getSafeErrorCode() ===
                TransactionCreationException::API_IDEMPOTENCY_CONFLICT
            ) {
                $this->failureHandler->handleAmbiguous(
                    $attempt,
                    $exception->getSafeErrorCode(),
                );
            } elseif ($exception->isDefinite()) {
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
            $createdTransaction["transaction_id"],
        );
        $attempt->setData("provider_status", "pending");
        $attempt->setData("creation_state", "created");
        $attempt->setData("safe_error_code", null);
        $attempt->setData(
            "encrypted_redirect_url",
            $this->encryptor->encrypt($createdTransaction["redirect_url"]),
        );
        $this->attemptRepository->save($attempt);

        return $createdTransaction["redirect_url"];
    }

    private function canStartPayment(Order $order): bool
    {
        return $order->getPayment()->getMethod() ===
            ConfigInterface::METHOD_CODE &&
            $order->getState() === Order::STATE_PENDING_PAYMENT &&
            $this->config->isActive((int) $order->getStoreId()) &&
            $this->config->isConnected();
    }

    private function createReturnToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
    }

    private function decryptAttemptUrl(
        PaymentAttempt $attempt,
        string $field,
        bool $allowEmpty = false,
    ): string {
        $encrypted = (string) $attempt->getData($field);

        if ($encrypted === "") {
            if ($allowEmpty) {
                return "";
            }

            throw new LocalizedException(
                __("The FLIZpay payment cannot be resumed."),
            );
        }

        try {
            $url = $this->encryptor->decrypt($encrypted);
        } catch (\Throwable) {
            throw new LocalizedException(
                __("The FLIZpay payment cannot be resumed."),
            );
        }

        if (!$allowEmpty && $url === "") {
            throw new LocalizedException(
                __("The FLIZpay payment cannot be resumed."),
            );
        }

        return $url;
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
