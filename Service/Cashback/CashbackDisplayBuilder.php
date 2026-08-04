<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Cashback;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use NumberFormatter;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds localized checkout copy from provider-owned cashback percentages.
 */
class CashbackDisplayBuilder
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ResolverInterface $localeResolver,
        private readonly StoreManagerInterface $storeManager,
    ) {}

    public function build(?int $storeId = null): CashbackDisplay
    {
        $data = $this->config->getCashbackData();

        $available =
            $this->config->isConnected() &&
            $data !== null &&
            ($data["first_purchase_amount"] > 0 ||
                $data["standard_amount"] > 0);

        $showLogo = $this->config->isCheckoutLogoEnabled($storeId);

        if (!$available) {
            return new CashbackDisplay(
                false,
                null,
                null,
                "FLIZpay",
                $this->config->isCheckoutSubtitleEnabled($storeId)
                    ? (string) __(
                        "Secure payments in direct collaboration with your bank. We support small businesses and keep your data private, stored securely in Germany.",
                    )
                    : null,
                $showLogo,
            );
        }

        $firstPurchase = $data["first_purchase_amount"];
        $standard = $data["standard_amount"];

        $type =
            $firstPurchase > 0 && $standard > 0
                ? "both"
                : ($firstPurchase > 0
                    ? "first"
                    : "standard");

        $formattedValue = $this->formatPercentage(
            max($firstPurchase, $standard),
        );

        $title = $this->config->isCashbackInTitleEnabled($storeId)
            ? (string) __("FLIZpay - Up to %1% Cashback", $formattedValue)
            : "FLIZpay";

        $description = $this->config->isCheckoutSubtitleEnabled($storeId)
            ? $this->buildDescription($type, $standard, $storeId)
            : null;

        return new CashbackDisplay(
            true,
            $type,
            $formattedValue,
            $title,
            $description,
            $showLogo,
        );
    }

    private function formatPercentage(float $value): string
    {
        $formatter = new NumberFormatter(
            $this->localeResolver->getLocale(),
            NumberFormatter::DECIMAL,
        );
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
        $formattedValue = $formatter->format($value);

        return is_string($formattedValue) ? $formattedValue : (string) $value;
    }

    private function buildDescription(
        string $type,
        float $standard,
        ?int $storeId,
    ): string {
        $shopName = (string) $this->storeManager->getStore($storeId)->getName();
        $formattedStandard = $this->formatPercentage($standard);

        if ($type === "both") {
            return (string) __(
                "Secure payments in direct collaboration with your bank. After your first FLIZpay payment at %1, you will continue to receive %2% cashback.",
                $shopName,
                $formattedStandard,
            );
        }

        if ($type === "first") {
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
}
