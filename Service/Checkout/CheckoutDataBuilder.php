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
use Magento\Framework\Locale\ResolverInterface;

/**
 * Collects the non-sensitive inputs the hosted checkout component needs.
 */
class CheckoutDataBuilder
{
    /**
     * @param ConfigInterface $config
     * @param PublicIdResolver $publicIdResolver
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PublicIdResolver $publicIdResolver,
        private readonly ResolverInterface $localeResolver,
    ) {}

    /**
     * Build the component inputs for one store.
     *
     * @param int|null $storeId
     * @return CheckoutData
     */
    public function build(?int $storeId = null): CheckoutData
    {
        return new CheckoutData(
            $this->publicIdResolver->resolve(),
            $this->localeResolver->getLocale(),
            $this->config->isHeadlineEnabled($storeId),
            $this->config->isDescriptionEnabled($storeId),
            $this->config->isLogoEnabled($storeId),
        );
    }
}
