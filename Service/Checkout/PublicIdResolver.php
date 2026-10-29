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

namespace FlizPay\Payment\Service\Checkout;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Api\FlizPayApiClient;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use Magento\Framework\App\CacheInterface;

/**
 * Owns the shop's public id, the only FLIZpay identifier the browser receives.
 *
 * Reading and acquiring are deliberately separate: rendering a page must never
 * block on a FLIZpay request, so only admin-side callers acquire an id.
 */
class PublicIdResolver
{
    /** Marks a recent failed acquisition so callers back off for an hour. */
    private const RETRY_CACHE_KEY = "flizpay_public_id_retry";

    private const RETRY_BACKOFF_SECONDS = 3600;

    /**
     * @param ConfigInterface $config
     * @param ConnectionConfigWriter $configWriter
     * @param FlizPayApiClient $apiClient
     * @param CacheInterface $cache
     * @param PaymentLogger $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ConnectionConfigWriter $configWriter,
        private readonly FlizPayApiClient $apiClient,
        private readonly CacheInterface $cache,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * Return the stored public id without ever contacting FLIZpay.
     *
     * An empty string means the components cannot render, so callers keep the
     * plain payment method label.
     *
     * @return string
     */
    public function resolve(): string
    {
        if (!$this->config->hasApiKey()) {
            return "";
        }

        return $this->config->getPublicId();
    }

    /**
     * Fetch and store the public id when one is missing.
     *
     * @param bool $throttled Skip the request shortly after a failed attempt.
     * @return string
     */
    public function ensure(bool $throttled = false): string
    {
        if (!$this->config->hasApiKey()) {
            return "";
        }

        $publicId = $this->config->getPublicId();

        if ($publicId !== "") {
            return $publicId;
        }

        if ($throttled && $this->cache->load(self::RETRY_CACHE_KEY)) {
            return "";
        }

        try {
            $publicId = $this->apiClient->fetchPublicId();
        } catch (\Throwable $exception) {
            $this->logger->warning("FLIZpay public id fetch failed", [
                "exception" => get_class($exception),
            ]);
            $publicId = null;
        }

        if ($publicId === null) {
            $this->cache->save(
                "1",
                self::RETRY_CACHE_KEY,
                [],
                self::RETRY_BACKOFF_SECONDS,
            );

            return "";
        }

        $this->configWriter->savePublicId($publicId);
        // A stored id makes the throttle meaningless; clearing it keeps a later
        // credential change from inheriting this attempt's back-off.
        $this->cache->remove(self::RETRY_CACHE_KEY);

        return $publicId;
    }

    /**
     * Allow the next acquisition to run without waiting out the back-off.
     *
     * @return void
     */
    public function clearRetryBackoff(): void
    {
        $this->cache->remove(self::RETRY_CACHE_KEY);
    }
}
