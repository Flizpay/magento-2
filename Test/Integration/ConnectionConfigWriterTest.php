<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use Magento\Framework\App\Cache\InstanceFactory;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Cache\ConfigInterface as CacheConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ConnectionConfigWriterTest extends TestCase
{
    public function testSavedPublicIdIsVisibleInTheSameRequest(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(WriterInterface::class)->delete("payment/flizpay/public_id");
        $objectManager->get(ReinitableConfigInterface::class)->reinit();

        $config = $objectManager->get(ConfigInterface::class);
        // Warm Magento's in-memory default-scope config before writing.
        self::assertSame("", $config->getPublicId());

        $this->writer()->savePublicId("pub_new");

        self::assertSame("pub_new", $config->getPublicId());
    }

    public function testReconnectClearsThePublicIdInTheSameRequest(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(WriterInterface::class)->save("payment/flizpay/public_id", "pub_old");
        $objectManager->get(ReinitableConfigInterface::class)->reinit();

        $config = $objectManager->get(ConfigInterface::class);
        self::assertSame("pub_old", $config->getPublicId());

        $this->writer()->markConnecting(
            "https://shop.test/flizpay/webhook",
            hash("sha256", "new-api-key"),
        );

        self::assertSame("", $config->getPublicId());
        self::assertSame(ConfigInterface::CONNECTION_CONNECTING, $config->getConnectionStatus());
    }

    private function writer(): ConnectionConfigWriter
    {
        $objectManager = Bootstrap::getObjectManager();
        // Bypass WarmConfigCache interception present in newer Magento versions.
        // The writer must refresh in-memory config even without that plugin.
        $cacheTypeList = new TypeList(
            $objectManager->get(CacheConfigInterface::class),
            $objectManager->get(StateInterface::class),
            $objectManager->get(InstanceFactory::class),
            $objectManager->get(CacheInterface::class),
        );

        return $objectManager->create(ConnectionConfigWriter::class, [
            "cacheTypeList" => $cacheTypeList,
        ]);
    }
}
