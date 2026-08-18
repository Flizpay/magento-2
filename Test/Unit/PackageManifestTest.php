<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit;

use PHPUnit\Framework\TestCase;

class PackageManifestTest extends TestCase
{
    public function testComposerManifestDefinesMagentoModulePackage(): void
    {
        $manifestPath = dirname(__DIR__, 2) . "/composer.json";
        $manifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame("flizpay-gmbh/magento2", $manifest["name"]);
        self::assertSame("magento2-module", $manifest["type"]);
        self::assertContains(
            "registration.php",
            $manifest["autoload"]["files"],
        );
        self::assertSame(
            "",
            $manifest["autoload"]["psr-4"]["FlizPay\\Payment\\"],
        );
    }
}
