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

namespace FlizPay\Payment\Model;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
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
        private readonly Json $json,
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
     * @inheritdoc
     */
    public function getCashbackData(): ?array
    {
        $value = $this->getGlobalValue("cashback_data");
        $data = null;

        if ($value === "") {
            return null;
        }

        try {
            $data = $this->json->unserialize($value);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

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
            return null;
        }

        return [
            "first_purchase_amount" => (float) $firstPurchase,
            "standard_amount" => (float) $standard,
        ];
    }

    /**
     * @inheritdoc
     */
    public function isLoggingEnabled(?int $storeId = null): bool
    {
        return $this->getStoreFlag("logging_enabled", $storeId);
    }

    /**
     * @inheritdoc
     */
    public function isCashbackInTitleEnabled(?int $storeId = null): bool
    {
        return $this->getStoreFlag("display_cashback_in_title", $storeId);
    }

    /**
     * @inheritdoc
     */
    public function isCheckoutLogoEnabled(?int $storeId = null): bool
    {
        return $this->getStoreFlag("show_checkout_logo", $storeId);
    }

    /**
     * @inheritdoc
     */
    public function isCheckoutSubtitleEnabled(?int $storeId = null): bool
    {
        return $this->getStoreFlag("show_checkout_subtitle", $storeId);
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

    /**
     * Read one store-scoped display flag.
     *
     * @param string $field
     * @param int|null $storeId
     * @return bool
     */
    private function getStoreFlag(string $field, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_PREFIX . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }
}
