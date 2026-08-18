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

namespace FlizPay\Payment\Service;

use Magento\Framework\Module\PackageInfo;

/**
 * Resolves the installed module version from its Composer package metadata.
 */
class ModuleVersion
{
    private const MODULE_NAME = "FlizPay_Payment";

    public function __construct(private readonly PackageInfo $packageInfo) {}

    public function get(): string
    {
        return $this->packageInfo->getVersion(self::MODULE_NAME);
    }
}
