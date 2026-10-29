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

use Magento\Framework\App\DeploymentConfig;

/**
 * Locates the hosted bundle that registers the FLIZpay web components.
 */
class WidgetScriptUrl
{
    // TODO: restore before shipping.
    // private const SCRIPT_URL = "https://app.flizpay.de/web-components/v1/flizpay.js";
    private const SCRIPT_URL = "https://olegs-macbook-pro-1.tail9450f2.ts.net:4441/dist/web-components/v1/flizpay.js";

    /** Set in app/etc/env.php to point at a staging build. */
    private const OVERRIDE_PATH = "flizpay/widget_script_url";

    /**
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
    ) {}

    /**
     * Return the bundle URL.
     *
     * @return string
     */
    public function get(): string
    {
        $override = $this->deploymentConfig->get(self::OVERRIDE_PATH);

        return is_string($override) && trim($override) !== ""
            ? trim($override)
            : self::SCRIPT_URL;
    }
}
