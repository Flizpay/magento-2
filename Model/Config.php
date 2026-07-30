<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay (https://flizpay.de).
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH (https://flizpay.de)
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 * @link    https://flizpay.de
 */

declare(strict_types=1);

namespace FlizPay\Payment\Model;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads scoped FLIZpay configuration without exposing credentials to callers.
 */
class Config implements ConfigInterface
{
    private const PATH_PREFIX = "payment/" . self::METHOD_CODE . "/";

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {}

    /**
     * @inheritdoc
     */
    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_PREFIX . "active",
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * @inheritdoc
     */
    public function hasApiKey(): bool
    {
        return $this->getApiKey() !== "";
    }

    /**
     * @inheritdoc
     */
    public function getApiKey(): string
    {
        return $this->getGlobalValue("api_key");
    }

    /**
     * @inheritdoc
     */
    public function getWebhookSecret(): string
    {
        return $this->getGlobalValue("webhook_secret");
    }

    /**
     * @inheritdoc
     */
    public function hasWebhookSecret(): bool
    {
        return $this->getWebhookSecret() !== "";
    }

    /**
     * @inheritdoc
     */
    public function getConnectionStatus(): string
    {
        return $this->getGlobalValue("connection_status") ?:
            self::CONNECTION_NOT_CONNECTED;
    }

    /**
     * @inheritdoc
     */
    public function isConnected(): bool
    {
        return $this->getConnectionStatus() === self::CONNECTION_CONNECTED &&
            $this->hasWebhookSecret();
    }

    /**
     * @inheritdoc
     */
    public function getWebhookUrl(): string
    {
        return $this->getGlobalValue("webhook_url");
    }

    /**
     * @inheritdoc
     */
    public function getConnectionApiKeyHash(): string
    {
        return $this->getGlobalValue("connection_api_key_hash");
    }

    /**
     * @inheritdoc
     */
    public function getConnectionVerifiedAt(): string
    {
        return $this->getGlobalValue("connection_verified_at");
    }

    /**
     * Read a global credential value.
     *
     * @param string $field
     * @return string
     */
    private function getGlobalValue(string $field): string
    {
        return trim(
            (string) $this->scopeConfig->getValue(
                self::PATH_PREFIX . $field,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
    }
}
