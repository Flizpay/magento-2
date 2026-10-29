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

/**
 * Everything <fliz-checkout> needs that the shop already knows.
 *
 * Carries no credentials and no locally stored discount rules; the component
 * owns the offer text. An empty public id means it cannot render, so callers
 * keep the plain payment method label.
 *
 * The amount is deliberately absent: at checkout it changes with the cart and
 * is supplied by the browser, and the admin preview uses a sample basket.
 */
class CheckoutData
{
    /**
     * @param string $publicId
     * @param string $locale
     * @param bool $showHeadline
     * @param bool $showDescription
     * @param bool $showLogo
     */
    public function __construct(
        public readonly string $publicId,
        public readonly string $locale,
        public readonly bool $showHeadline,
        public readonly bool $showDescription,
        public readonly bool $showLogo,
    ) {}

    /**
     * Whether the component can render at all.
     *
     * @return bool
     */
    public function isRenderable(): bool
    {
        return $this->publicId !== "";
    }

    /** @return array<string, bool|string> */
    public function toArray(): array
    {
        return [
            "publicId" => $this->publicId,
            "locale" => $this->locale,
            "showHeadline" => $this->showHeadline,
            "showDescription" => $this->showDescription,
            "showLogo" => $this->showLogo,
        ];
    }
}
