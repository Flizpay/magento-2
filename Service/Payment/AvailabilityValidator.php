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

        if (!$this->config->isActive($storeId) || !$this->config->hasApiKey()) {
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
