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

namespace FlizPay\Payment\Service\Connection;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Persists global merchant-connection state.
 */
class ConnectionConfigWriter
{
    private const PATH_PREFIX = "payment/flizpay/";

    /**
     * @param WriterInterface $writer
     * @param EncryptorInterface $encryptor
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        private readonly WriterInterface $writer,
        private readonly EncryptorInterface $encryptor,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Json $json,
    ) {}

    /**
     * Start a new connection attempt and invalidate the old secret.
     *
     * @param string $webhookUrl
     * @param string $apiKeyHash
     * @return void
     */
    public function markConnecting(string $webhookUrl, string $apiKeyHash): void
    {
        $this->save(
            "connection_status",
            ConfigInterface::CONNECTION_CONNECTING,
        );
        $this->save("webhook_url", $webhookUrl);
        $this->save("connection_api_key_hash", $apiKeyHash);
        $this->delete("webhook_secret");
        $this->delete("connection_verified_at");
        $this->cleanConfigCache();
    }

    /**
     * Store the generated webhook secret encrypted.
     *
     * @param string $secret
     * @return void
     */
    public function saveWebhookSecret(string $secret): void
    {
        $this->save("webhook_secret", $this->encryptor->encrypt($secret));
        $this->cleanConfigCache();
    }

    /**
     * Replace the provider-owned cashback configuration.
     *
     * @param array{first_purchase_amount: float, standard_amount: float}|null $data
     * @return void
     */
    public function replaceCashbackData(?array $data): void
    {
        if ($data === null) {
            $this->delete("cashback_data");
        } else {
            $this->save("cashback_data", $this->json->serialize($data));
        }

        $this->cleanConfigCache();
    }

    /**
     * Mark the connection verified by a signed callback.
     *
     * @return void
     */
    public function markConnected(): void
    {
        $this->save("connection_status", ConfigInterface::CONNECTION_CONNECTED);
        $this->save("connection_verified_at", gmdate("Y-m-d H:i:s"));
        $this->cleanConfigCache();
    }

    /**
     * Mark the current connection attempt failed.
     *
     * @return void
     */
    public function markFailed(): void
    {
        $this->save("connection_status", ConfigInterface::CONNECTION_FAILED);
        $this->cleanConfigCache();
    }

    /**
     * Remove connection state when no API key is configured.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->save(
            "connection_status",
            ConfigInterface::CONNECTION_NOT_CONNECTED,
        );
        $this->delete("webhook_url");
        $this->delete("webhook_secret");
        $this->delete("connection_api_key_hash");
        $this->delete("connection_verified_at");
        $this->delete("cashback_data");
        $this->cleanConfigCache();
    }

    /**
     * Save one global connection value.
     *
     * @param string $field
     * @param string $value
     * @return void
     */
    private function save(string $field, string $value): void
    {
        $this->writer->save(
            self::PATH_PREFIX . $field,
            $value,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0,
        );
    }

    /**
     * Delete one global connection value.
     *
     * @param string $field
     * @return void
     */
    private function delete(string $field): void
    {
        $this->writer->delete(
            self::PATH_PREFIX . $field,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0,
        );
    }

    /**
     * Invalidate cached configuration after connection changes.
     *
     * @return void
     */
    private function cleanConfigCache(): void
    {
        $this->cacheTypeList->cleanType("config");
    }
}
