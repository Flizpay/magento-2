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

namespace FlizPay\Payment\Service\Cashback;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds localized checkout copy from provider-owned cashback percentages.
 */
class CashbackDisplayBuilder
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PercentageFormatter $percentageFormatter,
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
                        "Pay with FLIZ. Stop carrying the hidden cost of payments. The European solution.",
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

        $formattedFirstPurchase = $this->percentageFormatter->format(
            $firstPurchase,
        );
        $formattedValue = $this->percentageFormatter->format(
            max($firstPurchase, $standard),
        );

        $title = $this->config->isCashbackInTitleEnabled($storeId)
            ? $this->buildTitle($type, $formattedFirstPurchase, $formattedValue)
            : "FLIZpay";

        $description = $this->config->isCheckoutSubtitleEnabled($storeId)
            ? $this->buildDescription(
                $type,
                $firstPurchase,
                $standard,
                $storeId,
            )
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

    private function buildTitle(
        string $type,
        string $formattedFirstPurchase,
        string $formattedValue,
    ): string {
        if ($type === "first") {
            return (string) __(
                "FLIZpay - Save %1% on your first payment",
                $formattedFirstPurchase,
            );
        }

        if ($type === "both") {
            return (string) __("FLIZpay - Save up to %1%", $formattedValue);
        }

        return (string) __("FLIZpay - Up to %1% discount", $formattedValue);
    }

    private function buildDescription(
        string $type,
        float $firstPurchase,
        float $standard,
        ?int $storeId,
    ): string {
        $shopName = (string) $this->storeManager->getStore($storeId)->getName();
        $formattedFirstPurchase = $this->percentageFormatter->format(
            $firstPurchase,
        );
        $formattedStandard = $this->percentageFormatter->format($standard);

        if ($type === "both") {
            return (string) __(
                "Get %1% discount on your first payment, then %2% on every payment after that at %3.",
                $formattedFirstPurchase,
                $formattedStandard,
                $shopName,
            );
        }

        if ($type === "first") {
            return (string) __(
                "Get %1% discount on your first payment at %2.",
                $formattedFirstPurchase,
                $shopName,
            );
        }

        return (string) __(
            "Get %1% discount on every payment at %2.",
            $formattedStandard,
            $shopName,
        );
    }
}
