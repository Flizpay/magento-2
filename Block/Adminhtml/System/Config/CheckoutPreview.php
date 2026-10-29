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

namespace FlizPay\Payment\Block\Adminhtml\System\Config;

use FlizPay\Payment\Service\Checkout\CheckoutData;
use FlizPay\Payment\Service\Checkout\CheckoutDataBuilder;
use FlizPay\Payment\Service\Checkout\WidgetScriptUrl;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;
use NumberFormatter;

/**
 * Renders a live checkout preview of the FLIZpay payment method row.
 *
 * The offer text comes from the same hosted component the storefront uses, so
 * the preview cannot drift from what customers see.
 */
class CheckoutPreview extends Field
{
    private const TEMPLATE = "FlizPay_Payment::system/config/checkout-preview.phtml";

    /** Sample basket the preview asks the component to price, in cents. */
    private const SAMPLE_AMOUNT = 10000;

    /**
     * Initialize the checkout-preview renderer.
     *
     * @param Context $context
     * @param CheckoutDataBuilder $checkoutDataBuilder
     * @param WidgetScriptUrl $scriptUrl
     * @param Json $json
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CheckoutDataBuilder $checkoutDataBuilder,
        private readonly WidgetScriptUrl $scriptUrl,
        private readonly Json $json,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the preview markup instead of a form input.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setTemplate(self::TEMPLATE);
        $this->setData("field_id_prefix", $this->fieldIdPrefix($element));

        return $this->toHtml();
    }

    /**
     * Whether the component can render, or the merchant still has to connect.
     *
     * @return bool
     */
    public function hasPublicId(): bool
    {
        return $this->getCheckoutData()->isRenderable();
    }

    /**
     * Return the shop's public id.
     *
     * @return string
     */
    public function getPublicId(): string
    {
        return $this->getCheckoutData()->publicId;
    }

    /**
     * Return the locale the preview renders in.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->getCheckoutData()->locale;
    }

    /**
     * Return the sample basket amount in cents.
     *
     * @return int
     */
    public function getSampleAmount(): int
    {
        return self::SAMPLE_AMOUNT;
    }

    /**
     * Return the sample basket amount for the hint, in the admin locale.
     *
     * Formatted from the same constant the preview element receives, so the
     * hint can never name a different basket than the one being priced.
     *
     * @return string
     */
    public function getFormattedSampleAmount(): string
    {
        $formatter = new NumberFormatter(
            $this->getLocale(),
            NumberFormatter::CURRENCY,
        );
        $formatted = $formatter->formatCurrency(
            self::SAMPLE_AMOUNT / 100,
            "EUR",
        );

        return is_string($formatted)
            ? $formatted
            : (string) (self::SAMPLE_AMOUNT / 100);
    }

    /**
     * Whether the offer headline replaces the payment method name.
     *
     * @return bool
     */
    public function isHeadlineEnabled(): bool
    {
        return $this->getCheckoutData()->showHeadline;
    }

    /**
     * Whether the checkout logo toggle is currently enabled.
     *
     * @return bool
     */
    public function isLogoEnabled(): bool
    {
        return $this->getCheckoutData()->showLogo;
    }

    /**
     * Whether the offer description toggle is currently enabled.
     *
     * @return bool
     */
    public function isDescriptionEnabled(): bool
    {
        return $this->getCheckoutData()->showDescription;
    }

    /**
     * Return the hosted bundle URL that upgrades the preview elements.
     *
     * @return string
     */
    public function getScriptUrl(): string
    {
        return $this->scriptUrl->get();
    }

    /**
     * Return the FLIZpay checkout logo URL.
     *
     * Resolves the same frontend asset the checkout renders so the preview
     * always matches what customers actually see.
     *
     * @return string
     */
    public function getLogoUrl(): string
    {
        return $this->getViewFileUrl(
            "FlizPay_Payment::images/fliz-checkout-logo.svg",
            ["area" => "frontend"],
        );
    }

    /**
     * Return the x-magento-init JSON wiring the preview to the toggle fields.
     *
     * @return string
     */
    public function getInitializationJson(): string
    {
        $prefix = (string) $this->getData("field_id_prefix");

        return $this->json->serialize([
            "#flizpay-checkout-preview" => [
                "FlizPay_Payment/js/checkout-preview" => [
                    "fields" => [
                        "headline" => $prefix . "show_headline",
                        "logo" => $prefix . "show_logo",
                        "description" => $prefix . "show_description",
                    ],
                    "component" => [
                        "publicId" => $this->getPublicId(),
                        "locale" => $this->getLocale(),
                        "amount" => $this->getSampleAmount(),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Derive the sibling-field element-id prefix from the preview element.
     *
     * The preview and its toggle fields share one group, so stripping the
     * "preview" field id yields the prefix of every sibling element id
     * (e.g. "flizpay_settings_preview" -> "flizpay_settings_"), letting the
     * same block work in any section the group is rendered in.
     *
     * @param AbstractElement $element
     * @return string
     */
    private function fieldIdPrefix(AbstractElement $element): string
    {
        $htmlId = (string) $element->getHtmlId();
        $suffixPosition = strrpos($htmlId, "preview");

        return $suffixPosition === false
            ? "flizpay_settings_"
            : substr($htmlId, 0, $suffixPosition);
    }

    /**
     * Build the same component inputs the storefront checkout receives.
     *
     * @return CheckoutData
     */
    private function getCheckoutData(): CheckoutData
    {
        return $this->checkoutDataBuilder->build();
    }
}
