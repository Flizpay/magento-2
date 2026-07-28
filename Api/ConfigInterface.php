<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay (https://flizpay.de).
 *
 * @package Flizpay_Payment
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
}
