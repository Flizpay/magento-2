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
 * Minimal client for FLIZpay merchant connection setup.
 */
class FlizPayApiClient
{
    private const API_BASE_URL = "https://olegs-macbook-pro-1.tail9450f2.ts.net:4440";
    private const REQUEST_TIMEOUT_SECONDS = 30;

    /**
     * @param Curl $httpClient
     * @param Json $json
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Curl $httpClient,
        private readonly Json $json,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
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

        try {
            $this->httpClient->setTimeout(self::REQUEST_TIMEOUT_SECONDS);
            $this->httpClient->setHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json",
                "User-Agent" => "FlizPayMagento2/0.1.0",
                "x-api-key" => $apiKey,
            ]);

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
                $this->logger->warning("FLIZpay API request rejected", [
                    "method" => $method,
                    "path" => $path,
                    "status" => $status,
                    "contentType" =>
                        $this->httpClient->getHeaders()["Content-Type"] ?? "",
                    "bodyPreview" => substr(
                        (string) $this->httpClient->getBody(),
                        0,
                        500,
                    ),
                ]);

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
            $this->logger->warning("FLIZpay API request failed", [
                "method" => $method,
                "path" => $path,
                "exception" => get_class($exception),
                "reason" => $exception->getMessage(),
            ]);

            throw new LocalizedException(
                __("Unable to connect Magento to FLIZpay."),
            );
        }
    }
}
