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
}
