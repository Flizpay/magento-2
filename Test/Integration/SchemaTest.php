<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    public function testPaymentPersistenceTablesAndUniqueBindingsExist(): void
    {
        $resource = Bootstrap::getObjectManager()->get(
            ResourceConnection::class,
        );
        $connection = $resource->getConnection();
        $attemptTable = $resource->getTableName("flizpay_payment_attempt");

        self::assertTrue($connection->isTableExists($attemptTable));
        $attemptColumns = $connection->describeTable($attemptTable);
        self::assertArrayHasKey("safe_error_code", $attemptColumns);
        self::assertArrayHasKey("encrypted_success_url", $attemptColumns);
        self::assertArrayHasKey("encrypted_failure_url", $attemptColumns);
        self::assertArrayHasKey("encrypted_redirect_url", $attemptColumns);
        self::assertArrayHasKey("expires_at", $attemptColumns);

        $attemptIndexes = $connection->getIndexList($attemptTable);
        self::assertSame(
            AdapterInterface::INDEX_TYPE_UNIQUE,
            $attemptIndexes["FLIZPAY_PAYMENT_ATTEMPT_ORDER_ID"]["INDEX_TYPE"],
        );
        self::assertSame(
            AdapterInterface::INDEX_TYPE_UNIQUE,
            $attemptIndexes["FLIZPAY_PAYMENT_ATTEMPT_ATTEMPT_ID"]["INDEX_TYPE"],
        );
    }
}
