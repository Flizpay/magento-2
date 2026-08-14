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

namespace FlizPay\Payment\Service\Webhook;

use FlizPay\Payment\Api\ConfigInterface;

/**
 * Authenticates FLIZpay webhooks against their exact request bytes.
 */
class WebhookAuthenticator
{
    /**
     * @param ConfigInterface $config
     */
    public function __construct(private readonly ConfigInterface $config) {}

    /**
     * Compare the supplied signature with the exact raw request body.
     *
     * @param string $rawBody
     * @param string $signature
     * @return bool
     */
    public function authenticate(string $rawBody, string $signature): bool
    {
        $secret = $this->config->getWebhookSecret();

        if ($secret === "" || $signature === "") {
            return false;
        }

        return hash_equals(hash_hmac("sha256", $rawBody, $secret), $signature);
    }
}
