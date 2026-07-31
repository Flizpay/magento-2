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
        $eventTable = $resource->getTableName("flizpay_webhook_event");

        self::assertTrue($connection->isTableExists($attemptTable));
        self::assertTrue($connection->isTableExists($eventTable));
        self::assertArrayHasKey(
            "safe_error_code",
            $connection->describeTable($attemptTable),
        );

        $attemptIndexes = $connection->getIndexList($attemptTable);
        self::assertSame(
            AdapterInterface::INDEX_TYPE_UNIQUE,
            $attemptIndexes["FLIZPAY_PAYMENT_ATTEMPT_ORDER_ID"]["INDEX_TYPE"],
        );
        self::assertSame(
            AdapterInterface::INDEX_TYPE_UNIQUE,
            $attemptIndexes["FLIZPAY_PAYMENT_ATTEMPT_ATTEMPT_ID"]["INDEX_TYPE"],
        );

        $eventIndexes = $connection->getIndexList($eventTable);
        self::assertSame(
            AdapterInterface::INDEX_TYPE_UNIQUE,
            $eventIndexes["FLIZPAY_WEBHOOK_EVENT_PAYLOAD_FINGERPRINT"][
                "INDEX_TYPE"
            ],
        );
    }
}
