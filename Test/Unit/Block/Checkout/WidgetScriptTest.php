<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Block\Checkout;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Block\Checkout\WidgetScript;
use FlizPay\Payment\Service\Checkout\PublicIdResolver;
use FlizPay\Payment\Service\Checkout\WidgetScriptUrl;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\TestCase;

class WidgetScriptTest extends TestCase
{
    public function testDisabledPaymentMethodDoesNotLoadTheBundle(): void
    {
        self::assertFalse($this->block(active: false)->isEnabled());
    }

    public function testHiddenTextSurfacesDoNotLoadTheBundle(): void
    {
        self::assertFalse(
            $this->block(headline: false, description: false)->isEnabled(),
        );
    }

    public function testMissingPublicIdDoesNotLoadTheBundle(): void
    {
        self::assertFalse($this->block(publicId: "")->isEnabled());
    }

    public function testHeadlineAloneLoadsTheBundle(): void
    {
        self::assertTrue($this->block(description: false)->isEnabled());
    }

    public function testDescriptionAloneLoadsTheBundle(): void
    {
        self::assertTrue($this->block(headline: false)->isEnabled());
    }

    public function testBothTextSurfacesLoadTheBundle(): void
    {
        self::assertTrue($this->block()->isEnabled());
    }

    private function block(
        bool $active = true,
        bool $headline = true,
        bool $description = true,
        string $publicId = "pub_123",
    ): WidgetScript {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("isActive")->willReturn($active);
        $config->method("isHeadlineEnabled")->willReturn($headline);
        $config->method("isDescriptionEnabled")->willReturn($description);

        $publicIdResolver = $this->createStub(PublicIdResolver::class);
        $publicIdResolver->method("resolve")->willReturn($publicId);

        return new WidgetScript(
            $this->createStub(Context::class),
            $publicIdResolver,
            $this->createStub(WidgetScriptUrl::class),
            $config,
        );
    }
}
