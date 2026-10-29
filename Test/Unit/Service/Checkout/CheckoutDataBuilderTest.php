<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Checkout;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Checkout\CheckoutDataBuilder;
use FlizPay\Payment\Service\Checkout\PublicIdResolver;
use Magento\Framework\Locale\ResolverInterface;
use PHPUnit\Framework\TestCase;

class CheckoutDataBuilderTest extends TestCase
{
    private function build(
        string $publicId,
        bool $headline = true,
        bool $description = true,
        bool $logo = true,
        string $locale = "de_DE",
    ): \FlizPay\Payment\Service\Checkout\CheckoutData {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isHeadlineEnabled")->willReturn($headline);
        $config->method("isDescriptionEnabled")->willReturn($description);
        $config->method("isLogoEnabled")->willReturn($logo);

        $publicIdResolver = $this->createStub(PublicIdResolver::class);
        $publicIdResolver->method("resolve")->willReturn($publicId);

        $localeResolver = $this->createStub(ResolverInterface::class);
        $localeResolver->method("getLocale")->willReturn($locale);

        return (new CheckoutDataBuilder(
            $config,
            $publicIdResolver,
            $localeResolver,
        ))->build();
    }

    public function testItPassesWhatTheComponentNeeds(): void
    {
        $data = $this->build("pub_123");

        self::assertSame("pub_123", $data->publicId);
        self::assertSame("de_DE", $data->locale);
        self::assertTrue($data->showHeadline);
        self::assertTrue($data->showDescription);
        self::assertTrue($data->showLogo);
        self::assertTrue($data->isRenderable());
    }

    /** Without a public id the element has nothing to fetch. */
    public function testItIsNotRenderableWithoutAPublicId(): void
    {
        self::assertFalse($this->build("")->isRenderable());
    }

    public function testItReflectsTheDisplayToggles(): void
    {
        $data = $this->build("pub_123", false, false, false);

        self::assertFalse($data->showHeadline);
        self::assertFalse($data->showDescription);
        self::assertFalse($data->showLogo);
        // Toggles never gate the public id; the plain label does that.
        self::assertTrue($data->isRenderable());
    }

    /**
     * The amount changes with the cart, so it is supplied by the browser and
     * must never be baked into the server payload.
     */
    public function testThePayloadCarriesNoAmountOrOfferText(): void
    {
        self::assertSame(
            ["publicId", "locale", "showHeadline", "showDescription", "showLogo"],
            array_keys($this->build("pub_123")->toArray()),
        );
    }
}
