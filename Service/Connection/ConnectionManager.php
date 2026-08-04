<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Connection;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Coordinates merchant webhook registration and verification.
 */
class ConnectionManager
{
    /**
     * @param ConfigInterface $config
     * @param ConnectionConfigWriter $configWriter
     * @param FlizPayApiClient $apiClient
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ConnectionConfigWriter $configWriter,
        private readonly FlizPayApiClient $apiClient,
        private readonly StoreManagerInterface $storeManager,
    ) {}

    /**
     * Register the webhook and store the generated secret when needed.
     *
     * @return bool True when a new connection attempt was started.
     * @throws LocalizedException
     */
    public function connectIfNeeded(): bool
    {
        $apiKey = $this->config->getApiKey();

        if ($apiKey === "") {
            $this->configWriter->reset();

            return false;
        }

        try {
            $webhookUrl = $this->buildWebhookUrl();
        } catch (LocalizedException $exception) {
            $this->configWriter->markFailed();
            throw $exception;
        }

        $apiKeyHash = hash("sha256", $apiKey);
        $status = $this->config->getConnectionStatus();
        $connectionCurrent =
            $status === ConfigInterface::CONNECTION_CONNECTED &&
            $this->config->hasWebhookSecret();
        $sameApiKey = hash_equals(
            $this->config->getConnectionApiKeyHash(),
            $apiKeyHash,
        );
        $sameWebhookUrl = $this->config->getWebhookUrl() === $webhookUrl;

        if ($connectionCurrent && $sameApiKey && $sameWebhookUrl) {
            return false;
        }

        $this->configWriter->markConnecting($webhookUrl, $apiKeyHash);

        try {
            $this->apiClient->registerWebhook($webhookUrl);
            $this->configWriter->saveWebhookSecret(
                $this->apiClient->generateWebhookSecret(),
            );
        } catch (\Throwable) {
            $this->configWriter->markFailed();
            throw new LocalizedException(
                __("Unable to connect Magento to FLIZpay."),
            );
        }

        try {
            $this->configWriter->replaceCashbackData(
                $this->apiClient->fetchCashbackData(),
            );
        } catch (\Throwable) {
            // Cashback is optional, but stale provider rates must not be shown.
            $this->configWriter->replaceCashbackData(null);
        }

        return true;
    }

    /**
     * Confirm that the generated secret authenticated a provider callback.
     *
     * @return void
     */
    public function confirmWebhookConnection(): void
    {
        $this->configWriter->markConnected();
    }

    /**
     * Build the exact public webhook URL used by existing integrations.
     *
     * @return string
     * @throws LocalizedException
     */
    private function buildWebhookUrl(): string
    {
        $store = $this->storeManager->getDefaultStoreView();
        if (!$store instanceof Store) {
            throw new LocalizedException(
                __("Magento has no default store view."),
            );
        }

        $baseUrl = rtrim(
            (string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true),
            "/",
        );

        if (!str_starts_with(strtolower($baseUrl), "https://")) {
            throw new LocalizedException(
                __("Magento secure base URL must use HTTPS."),
            );
        }

        return $baseUrl . "/flizpay/webhook";
    }
}
