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

namespace FlizPay\Payment\Api;

/**
 * Provides access to FLIZpay payment configuration.
 */
interface ConfigInterface
{
    /** Payment method code. */
    public const METHOD_CODE = "flizpay";

    /** Connection is not configured. */
    public const CONNECTION_NOT_CONNECTED = "not_connected";

    /** Connection setup is waiting for the signed test callback. */
    public const CONNECTION_CONNECTING = "connecting";

    /** Connection was verified by the signed test callback. */
    public const CONNECTION_CONNECTED = "connected";

    /** Connection setup failed. */
    public const CONNECTION_FAILED = "failed";

    /**
     * Check whether FLIZpay is enabled for a store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive(?int $storeId = null): bool;

    /**
     * Check whether the global API key exists.
     *
     * @return bool
     */
    public function hasApiKey(): bool;

    /**
     * Check whether the generated webhook secret exists.
     *
     * @return bool
     */
    public function hasWebhookSecret(): bool;

    /**
     * Return the decrypted global API key.
     *
     * @return string
     */
    public function getApiKey(): string;

    /**
     * Return the decrypted global webhook secret.
     *
     * @return string
     */
    public function getWebhookSecret(): string;

    /**
     * Return the merchant connection status.
     *
     * @return string
     */
    public function getConnectionStatus(): string;

    /**
     * Check whether the signed connection test succeeded.
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Return the registered webhook URL.
     *
     * @return string
     */
    public function getWebhookUrl(): string;

    /**
     * Return the API-key fingerprint used by the connection.
     *
     * @return string
     */
    public function getConnectionApiKeyHash(): string;

    /**
     * Return the last successful connection-test timestamp.
     *
     * @return string
     */
    public function getConnectionVerifiedAt(): string;

    /**
     * Return normalized provider cashback percentages.
     *
     * @return array{first_purchase_amount: float, standard_amount: float}|null
     */
    public function getCashbackData(): ?array;

    /**
     * Check whether verbose diagnostic logging is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLoggingEnabled(?int $storeId = null): bool;

    /**
     * Check whether cashback should be included in the payment title.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCashbackInTitleEnabled(?int $storeId = null): bool;

    /**
     * Check whether the FLIZpay checkout logo should be displayed.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCheckoutLogoEnabled(?int $storeId = null): bool;

    /**
     * Check whether the FLIZpay checkout subtitle should be displayed.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCheckoutSubtitleEnabled(?int $storeId = null): bool;
}
