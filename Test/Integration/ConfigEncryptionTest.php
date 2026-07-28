<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ConfigEncryptionTest extends TestCase
{
    /**
     * @magentoAppArea adminhtml
     */
    public function testAdminConfigurationUsesEncryptedCredentialBackend(): void
    {
        $structure = Bootstrap::getObjectManager()->get(Structure::class);
        $apiKey = $structure->getElementByConfigPath("payment/flizpay/api_key");

        self::assertInstanceOf(Field::class, $apiKey);
        self::assertTrue($apiKey->hasBackendModel());
        self::assertInstanceOf(Encrypted::class, $apiKey->getBackendModel());
    }

    public function testCredentialBackendStoresEncryptedValue(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $backend = $objectManager->create(Encrypted::class);
        $backend->setData([
            "path" => "payment/flizpay/api_key",
            "scope" => "default",
            "scope_id" => 0,
            "value" => "integration-api-key",
        ]);
        $backend->save();

        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $storedValue = (string) $connection->fetchOne(
            $connection
                ->select()
                ->from($resource->getTableName("core_config_data"), ["value"])
                ->where("path = ?", "payment/flizpay/api_key")
                ->where("scope = ?", "default")
                ->where("scope_id = ?", 0),
        );

        self::assertNotSame("integration-api-key", $storedValue);
        self::assertSame(
            "integration-api-key",
            $objectManager
                ->get(EncryptorInterface::class)
                ->decrypt($storedValue),
        );

        $backend->delete();
    }
}
