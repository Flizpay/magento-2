<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class CheckoutAssetTest extends TestCase
{
    private function template(): string
    {
        $template = file_get_contents(
            __DIR__ . "/../../../view/frontend/web/template/payment/flizpay.html",
        );
        self::assertIsString($template);

        return $template;
    }

    private function script(): string
    {
        $script = file_get_contents(
            __DIR__ .
                "/../../../view/frontend/web/js/view/payment/method-renderer/flizpay-method.js",
        );
        self::assertIsString($script);

        return $script;
    }

    public function testCheckoutTemplateRendersBothHostedSurfaces(): void
    {
        $template = $this->template();

        self::assertStringContainsString('data-surface="title"', $template);
        self::assertStringContainsString(
            'data-surface="description"',
            $template,
        );
        // Both surfaces share one computed, so the amount stays in step.
        self::assertSame(
            2,
            substr_count($template, "attr: componentAttrs"),
            "both surfaces must bind the shared attributes",
        );
    }

    public function testCheckoutTemplateKeepsTheNativeLabelAndLogo(): void
    {
        $template = $this->template();

        self::assertStringContainsString("ifnot: showHeadline", $template);
        self::assertStringContainsString("text: getTitle()", $template);
        self::assertStringContainsString("visible: showLogo", $template);
        self::assertStringContainsString("getLogoUrl()", $template);
    }

    /**
     * Remounting a <fliz-checkout> makes it refetch its offer text, so the
     * selection-dependent surface must be hidden rather than torn down.
     */
    public function testSelectionTogglesVisibilityInsteadOfRemounting(): void
    {
        $template = $this->template();

        self::assertStringContainsString(
            "visible: isDescriptionVisible()",
            $template,
        );
        self::assertStringNotContainsString(
            "if: isDescriptionVisible",
            $template,
        );
    }

    public function testLocallyBuiltOfferTextIsGone(): void
    {
        $template = $this->template();

        self::assertStringNotContainsString("getDisplayTitle", $template);
        self::assertStringNotContainsString("getCashbackDescription", $template);
        // The component supplies its own "more info" label.
        self::assertStringNotContainsString(
            "https://flizpay.de/shoppers",
            $template,
        );
    }

    public function testRendererReadsTheCheckoutPayloadAndTracksTheQuote(): void
    {
        $script = $this->script();

        self::assertStringContainsString(
            "window.checkoutConfig.payment.flizpay.checkout",
            $script,
        );
        self::assertStringContainsString("Magento_Checkout/js/model/quote", $script);
        self::assertStringContainsString("quote.totals()", $script);
        self::assertStringContainsString("data-public-id", $script);
        self::assertStringContainsString("data-amount", $script);
        self::assertStringNotContainsString("cashback", $script);
    }

    public function testCheckoutAssetsExist(): void
    {
        self::assertFileExists(
            __DIR__ .
                "/../../../view/frontend/web/images/fliz-checkout-logo.svg",
        );
        self::assertFileExists(
            __DIR__ . "/../../../view/frontend/web/css/source/_checkout.css",
        );
        self::assertFileExists(
            __DIR__ .
                "/../../../view/frontend/templates/checkout/widget-script.phtml",
        );
    }
}
