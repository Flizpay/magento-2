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

namespace FlizPay\Payment\Block\Checkout;

use FlizPay\Payment\Service\Checkout\PublicIdResolver;
use FlizPay\Payment\Service\Checkout\WidgetScriptUrl;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Loads the hosted bundle that upgrades the <fliz-checkout> elements.
 */
class WidgetScript extends Template
{
    /**
     * @param Context $context
     * @param PublicIdResolver $publicIdResolver
     * @param WidgetScriptUrl $scriptUrl
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly PublicIdResolver $publicIdResolver,
        private readonly WidgetScriptUrl $scriptUrl,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the bundle is worth loading for this store.
     *
     * Without a public id the elements have nothing to fetch and the checkout
     * keeps the plain label, so the script would do nothing.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->publicIdResolver->resolve() !== "";
    }

    /**
     * Return the hosted bundle URL.
     *
     * @return string
     */
    public function getScriptUrl(): string
    {
        return $this->scriptUrl->get();
    }
}
