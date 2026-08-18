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

namespace FlizPay\Payment\Service\Api;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\ModuleVersion;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Minimal client for authenticated FLIZpay API requests.
 */
class FlizPayApiClient
{
    private const API_BASE_URL = "https://api.flizpay.de";
    private const REQUEST_TIMEOUT_SECONDS = 30;

    /**
     * @param Curl $httpClient
     * @param Json $json
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     * @param ModuleVersion $moduleVersion
     */
    public function __construct(
        private readonly Curl $httpClient,
        private readonly Json $json,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly ModuleVersion $moduleVersion,
    ) {}

    /**
     * Register the Magento webhook URL for the merchant.
     *
     * @param string $webhookUrl
     * @return void
     * @throws LocalizedException
     */
    public function registerWebhook(string $webhookUrl): void
    {
        $data = $this->request("POST", "/business/edit", [
            "webhookUrl" => $webhookUrl,
        ]);

        if (($data["webhookUrl"] ?? null) !== $webhookUrl) {
            throw new LocalizedException(
                __("FLIZpay did not accept the webhook URL."),
            );
        }
    }

    /**
     * Generate the secret used to authenticate callbacks.
     *
     * @return string
     * @throws LocalizedException
     */
    public function generateWebhookSecret(): string
    {
        $data = $this->request("GET", "/business/generate-webhook-key");
        $secret = $data["webhookKey"] ?? null;
        if (!is_string($secret) || $secret === "") {
            throw new LocalizedException(
                __("FLIZpay did not return a webhook secret."),
            );
        }

        return $secret;
    }

    /**
     * Fetch normalized cashback percentages configured for the merchant.
     *
     * @return array{first_purchase_amount: float, standard_amount: float}
     * @throws LocalizedException
     */
    public function fetchCashbackData(): array
    {
        $data = $this->request("GET", "/business/cashback");

        $firstPurchase = $data["cashback"]["firstPurchaseAmount"] ?? null;
        $standard = $data["cashback"]["amount"] ?? null;

        if (
            !is_numeric($firstPurchase) ||
            !is_numeric($standard) ||
            !is_finite((float) $firstPurchase) ||
            !is_finite((float) $standard) ||
            (float) $firstPurchase < 0 ||
            (float) $standard < 0
        ) {
            throw new LocalizedException(
                __("FLIZpay returned invalid cashback configuration."),
            );
        }

        return [
            "first_purchase_amount" => (float) $firstPurchase,
            "standard_amount" => (float) $standard,
        ];
    }

    /**
     * Create one FLIZpay transaction without automatic retries.
     *
     * @param array<string, mixed> $requestBody
     * @param string $idempotencyKey
     * @return array{transaction_id: string, redirect_url: string}
     * @throws LocalizedException
     */
    public function createTransaction(
        array $requestBody,
        string $idempotencyKey,
    ): array {
        if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $idempotencyKey)) {
            throw new \InvalidArgumentException(
                "FLIZpay idempotency key is invalid.",
            );
        }

        try {
            $data = $this->request("POST", "/transactions", $requestBody, [
                "Idempotency-Key" => $idempotencyKey,
            ]);
        } catch (LocalizedException $exception) {
            $status = $this->httpClient->getStatus();
            $definite = $status >= 400 && $status < 500;
            $code = match ($status) {
                409 => TransactionCreationException::API_IDEMPOTENCY_CONFLICT,
                401,
                403
                    => TransactionCreationException::API_AUTHENTICATION_FAILED,
                default => $definite
                    ? TransactionCreationException::API_REJECTED
                    : TransactionCreationException::API_TRANSPORT_ERROR,
            };

            throw new TransactionCreationException($code, $definite);
        }

        $transactionId = $data["transactionId"] ?? null;
        $redirectUrl = $data["redirectUrl"] ?? null;

        if (!$this->isValidTransactionResponse($transactionId, $redirectUrl)) {
            $this->logFailure(
                "/transactions",
                $this->httpClient->getStatus(),
                new \UnexpectedValueException("Invalid transaction response."),
            );

            throw new TransactionCreationException(
                TransactionCreationException::API_INVALID_RESPONSE,
                true,
            );
        }

        return [
            "transaction_id" => trim($transactionId),
            "redirect_url" => trim($redirectUrl),
        ];
    }

    /**
     * Execute one authenticated FLIZpay API request.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     * @throws LocalizedException
     * @phpstan-param array<string, mixed>|null $body
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
    ): array {
        $apiKey = $this->config->getApiKey();

        if ($apiKey === "") {
            throw new LocalizedException(__("FLIZpay API key is missing."));
        }

        $status = null;

        try {
            $this->httpClient->setTimeout(self::REQUEST_TIMEOUT_SECONDS);
            $this->httpClient->setHeaders(
                array_merge(
                    [
                        "Accept" => "application/json",
                        "Content-Type" => "application/json",
                        "User-Agent" =>
                            "FlizPayMagento2/" . $this->moduleVersion->get(),
                        "x-api-key" => $apiKey,
                    ],
                    $headers,
                ),
            );

            $url = self::API_BASE_URL . $path;

            if ($method === "POST") {
                $this->httpClient->post(
                    $url,
                    $this->json->serialize($body ?? []),
                );
            } else {
                $this->httpClient->get($url);
            }

            $status = $this->httpClient->getStatus();

            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException("Unexpected HTTP status");
            }

            $response = $this->json->unserialize($this->httpClient->getBody());

            if (!is_array($response)) {
                throw new \RuntimeException("Invalid API response");
            }

            $data = is_array($response["data"] ?? null)
                ? $response["data"]
                : $response;

            if (!is_array($data)) {
                throw new \RuntimeException("Invalid API data");
            }

            return $data;
        } catch (\Throwable $exception) {
            $this->logFailure($path, $status, $exception);

            throw new LocalizedException(
                __("Unable to connect Magento to FLIZpay."),
            );
        }
    }

    /**
     * Record diagnostics that cannot expose request or response content.
     *
     * @param string $path
     * @param int|null $status
     * @param \Throwable $exception
     * @return void
     */
    private function logFailure(
        string $path,
        ?int $status,
        \Throwable $exception,
    ): void {
        $this->logger->warning("FLIZpay API request failed", [
            "path" => $path,
            "status" => $status,
            "exception" => get_class($exception),
        ]);
    }

    private function isValidTransactionResponse(
        mixed $transactionId,
        mixed $redirectUrl,
    ): bool {
        return is_string($transactionId) &&
            trim($transactionId) !== "" &&
            is_string($redirectUrl) &&
            trim($redirectUrl) !== "";
    }
}
