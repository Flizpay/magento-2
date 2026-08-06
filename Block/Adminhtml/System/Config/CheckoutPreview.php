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

namespace FlizPay\Payment\Block\Adminhtml\System\Config;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Cashback\PercentageFormatter;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

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
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param PercentageFormatter $percentageFormatter
     * @param Json $json
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly PercentageFormatter $percentageFormatter,
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
        $data = $this->config->getCashbackData();

        return $data !== null &&
            ($data["first_purchase_amount"] > 0 ||
                $data["standard_amount"] > 0);
    }

    /**
     * Return the cashback title suffix shown next to the method name.
     *
     * @return string
     */
    public function getCashbackTitleSuffix(): string
    {
        if (!$this->hasCashback()) {
            return "";
        }

        $data = $this->config->getCashbackData();
        $max = max($data["first_purchase_amount"], $data["standard_amount"]);

        return (string) __(
            "- Up to %1% Cashback",
            $this->percentageFormatter->format($max),
        );
    }

    /**
     * Return the checkout subtitle matching the current cashback state.
     *
     * @return string
     */
    public function getSubtitle(): string
    {
        $data = $this->config->getCashbackData();

        if (
            $data === null ||
            ($data["first_purchase_amount"] <= 0 &&
                $data["standard_amount"] <= 0)
        ) {
            return (string) __(
                "Secure payments in direct collaboration with your bank. We support small businesses and keep your data private, stored securely in Germany.",
            );
        }

        $shopName = $this->getShopName();
        $formattedStandard = $this->percentageFormatter->format(
            $data["standard_amount"],
        );

        if (
            $data["first_purchase_amount"] > 0 &&
            $data["standard_amount"] > 0
        ) {
            return (string) __(
                "Secure payments in direct collaboration with your bank. After your first FLIZpay payment at %1, you will continue to receive %2% cashback.",
                $shopName,
                $formattedStandard,
            );
        }

        if ($data["first_purchase_amount"] > 0) {
            return (string) __(
                "Secure payments in direct collaboration with your bank. No additional cashback after your first FLIZpay payment at %1.",
                $shopName,
            );
        }

        return (string) __(
            "Secure payments in direct collaboration with your bank. Receive %1% cashback for every FLIZpay payment at %2.",
            $formattedStandard,
            $shopName,
        );
    }

    /**
     * Whether the checkout logo toggle is currently enabled.
     *
     * @return bool
     */
    public function isLogoEnabled(): bool
    {
        return $this->config->isCheckoutLogoEnabled();
    }

    /**
     * Whether the checkout subtitle toggle is currently enabled.
     *
     * @return bool
     */
    public function isSubtitleEnabled(): bool
    {
        return $this->config->isCheckoutSubtitleEnabled();
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
     * Return the default store name for subtitle copy.
     *
     * @return string
     */
    private function getShopName(): string
    {
        try {
            return (string) $this->storeManager->getStore()->getName();
        } catch (\Throwable) {
            return "";
        }
    }
}
