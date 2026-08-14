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

namespace FlizPay\Payment\Service\Payment;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;

/**
 * Applies all local checkout availability rules without remote calls.
 */
class AvailabilityValidator
{
    /**
     * @param ConfigInterface $config
     */
    public function __construct(private readonly ConfigInterface $config) {}

    /**
     * Check whether a quote can use FLIZpay.
     *
     * @param Quote|null $quote
     * @return bool
     */
    public function isAvailable(?Quote $quote): bool
    {
        if ($quote === null) {
            return false;
        }

        $store = $quote->getStore();
        $storeId = (int) $store->getId();

        $configured =
            $this->config->isActive($storeId) &&
            $this->config->hasApiKey() &&
            $this->config->isConnected();

        if (!$configured) {
            return false;
        }

        if (
            strtoupper((string) $quote->getData("quote_currency_code")) !==
            "EUR"
        ) {
            return false;
        }

        $secureBaseUrl = (string) $store->getBaseUrl(
            UrlInterface::URL_TYPE_LINK,
            true,
        );

        if (!str_starts_with(strtolower($secureBaseUrl), "https://")) {
            return false;
        }

        return true;
    }
}
