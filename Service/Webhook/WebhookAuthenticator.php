<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
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
