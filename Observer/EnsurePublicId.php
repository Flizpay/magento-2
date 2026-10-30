<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 */

declare(strict_types=1);

namespace FlizPay\Payment\Observer;

use FlizPay\Payment\Service\Checkout\PublicIdResolver;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Picks up a missing public id while the merchant is on the configuration page.
 *
 * Connecting the shop normally acquires it, so this only recovers from an
 * earlier failed attempt. It runs in the admin on purpose: the storefront must
 * never spend a checkout request waiting on FLIZpay.
 */
class EnsurePublicId implements ObserverInterface
{
    /**
     * @param PublicIdResolver $publicIdResolver
     * @param PaymentLogger $logger
     */
    public function __construct(
        private readonly PublicIdResolver $publicIdResolver,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * Acquire the public id unless a recent attempt already failed.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->publicIdResolver->ensure(true);
        } catch (\Throwable $exception) {
            $this->logger->warning("FLIZpay public id top-up failed", [
                "exception" => get_class($exception),
            ]);
        }
    }
}
