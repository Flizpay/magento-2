<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Api;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Minimal client for authenticated FLIZpay API requests.
 */
class FlizPayApiClient
{
    private const API_BASE_URL = "https://olegs-macbook-pro-1.tail9450f2.ts.net:4440";
    private const REDIRECT_HOST = "olegs-macbook-pro-1.tail9450f2.ts.net";
    private const REQUEST_TIMEOUT_SECONDS = 30;

    private readonly string $baseUrl;

    /** @var list<string> */
    private readonly array $redirectHosts;

    /**
     * @param Curl $httpClient
     * @param Json $json
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     * @param string $baseUrl
     * @param list<string> $redirectHosts
     */
    public function __construct(
        private readonly Curl $httpClient,
        private readonly Json $json,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        string $baseUrl = self::API_BASE_URL,
        array $redirectHosts = [self::REDIRECT_HOST],
    ) {
        $baseUrl = rtrim($baseUrl, "/");
        $parts = parse_url($baseUrl);
        if (
            !is_array($parts) ||
            strtolower((string) ($parts["scheme"] ?? "")) !== "https" ||
            empty($parts["host"]) ||
            isset($parts["user"]) ||
            isset($parts["pass"]) ||
            isset($parts["query"]) ||
            isset($parts["fragment"])
        ) {
            throw new \InvalidArgumentException(
                "FLIZpay API base URL must be an absolute HTTPS URL.",
            );
        }

        $redirectHosts = array_values(
            array_unique(
                array_map(
                    static fn(string $host): string => strtolower(trim($host)),
                    $redirectHosts,
                ),
            ),
        );
        if (
            $redirectHosts === [] ||
            array_filter(
                $redirectHosts,
                static fn(string $host): bool => $host === "" ||
                    filter_var(
                        $host,
                        FILTER_VALIDATE_DOMAIN,
                        FILTER_FLAG_HOSTNAME,
                    ) === false,
            ) !== []
        ) {
            throw new \InvalidArgumentException(
                "FLIZpay redirect hosts must be valid hostnames.",
            );
        }

        $this->baseUrl = $baseUrl;
        $this->redirectHosts = $redirectHosts;
    }

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

        $firstPurchase = $data["first_purchase_amount"] ?? null;
        $standard = $data["standard_amount"] ?? null;

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
     * @param array<string, mixed> $request
     * @return CreatedTransaction
     * @throws LocalizedException
     */
    public function createTransaction(array $request): CreatedTransaction
    {
        try {
            $data = $this->request("POST", "/transactions", $request);
        } catch (LocalizedException $exception) {
            $status = $this->httpClient->getStatus();
            $definite = $status >= 400 && $status < 500;
            $code =
                $status === 401 || $status === 403
                    ? TransactionCreationException::API_AUTHENTICATION_FAILED
                    : ($definite
                        ? TransactionCreationException::API_REJECTED
                        : TransactionCreationException::API_TRANSPORT_ERROR);

            throw new TransactionCreationException($code, $definite);
        }

        try {
            return CreatedTransaction::fromResponse(
                $data,
                $this->redirectHosts,
            );
        } catch (\Throwable $exception) {
            $this->logFailure(
                "/transactions",
                $this->httpClient->getStatus(),
                $exception,
            );

            throw new TransactionCreationException(
                TransactionCreationException::API_INVALID_RESPONSE,
                true,
            );
        }
    }

    /**
     * Execute one authenticated FLIZpay API request.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array<string, mixed>
     * @throws LocalizedException
     * @phpstan-param array<string, mixed>|null $body
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
    ): array {
        $apiKey = $this->config->getApiKey();

        if ($apiKey === "") {
            throw new LocalizedException(__("FLIZpay API key is missing."));
        }

        $status = null;

        try {
            $this->httpClient->setTimeout(self::REQUEST_TIMEOUT_SECONDS);
            $this->httpClient->setHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json",
                "User-Agent" => "FlizPayMagento2/0.1.0",
                "x-api-key" => $apiKey,
            ]);

            $url = $this->baseUrl . $path;

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
}
