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

use FlizPay\Payment\Service\Cashback\CashbackDisplayBuilder;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Renders a live checkout preview of the FLIZpay payment method row.
 */
class CheckoutPreview extends Field
{
    private const TEMPLATE = "FlizPay_Payment::system/config/checkout-preview.phtml";

    /**
     * Initialize the checkout-preview renderer.
     *
     * @param Context $context
     * @param CashbackDisplayBuilder $cashbackDisplayBuilder
     * @param Json $json
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CashbackDisplayBuilder $cashbackDisplayBuilder,
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
     * Whether an active cashback campaign exists.
     *
     * @return bool
     */
    public function hasCashback(): bool
    {
        return (bool) $this->getCheckoutPresentation()["available"];
    }

    /**
     * Return the cashback title suffix shown next to the method name.
     *
     * @return string
     */
    public function getCashbackTitleSuffix(): string
    {
        $title = (string) $this->getCheckoutPresentation()["title"];

        return $title === "FLIZpay" ? "" : substr($title, strlen("FLIZpay"));
    }

    /**
     * Return the checkout subtitle matching the current cashback state.
     *
     * @return string
     */
    public function getSubtitle(): string
    {
        return (string) ($this->getCheckoutPresentation()["description"] ?? "");
    }

    /**
     * Whether the checkout logo toggle is currently enabled.
     *
     * @return bool
     */
    public function isLogoEnabled(): bool
    {
        return (bool) $this->getCheckoutPresentation()["showLogo"];
    }

    /**
     * Whether the checkout subtitle toggle is currently enabled.
     *
     * @return bool
     */
    public function isSubtitleEnabled(): bool
    {
        return $this->getCheckoutPresentation()["description"] !== null;
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
                        "logo" => $prefix . "show_checkout_logo",
                        "subtitle" => $prefix . "show_checkout_subtitle",
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
     * Build the same customer-facing presentation used by storefront checkout.
     *
     * Delegating to the shared builder keeps the admin preview's copy and
     * visibility settings synchronized with the payment method customers see.
     *
     * @return array<string, bool|string|null>
     */
    private function getCheckoutPresentation(): array
    {
        return $this->cashbackDisplayBuilder->build()->toArray();
    }
}
