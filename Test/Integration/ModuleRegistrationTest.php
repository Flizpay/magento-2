<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Module\ModuleListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ModuleRegistrationTest extends TestCase
{
    public function testModuleIsRegisteredAndEnabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $moduleList = $objectManager->get(ModuleListInterface::class);
        $componentRegistrar = $objectManager->get(ComponentRegistrar::class);

        self::assertNotNull($moduleList->getOne("Flizpay_Payment"));
        self::assertSame(
            realpath(dirname(__DIR__, 2)),
            realpath(
                (string) $componentRegistrar->getPath(
                    ComponentRegistrar::MODULE,
                    "Flizpay_Payment",
                ),
            ),
        );
    }
}
