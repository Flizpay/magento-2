<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Integration;

use FlizPay\Payment\Service\Payment\CompletedPaymentHandler;
use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\ItemFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CashbackVatScenarioTest extends TestCase
{
    /**
     * Scenario A proves the baseline settlement shape: a net catalog price at
     * 19% VAT and untaxed EUR 5.00 shipping. The provider's gross cashback must
     * become a net product discount, product VAT reduction, and shipping
     * allocation without changing the provider final amount.
     */
    public static function scenarioA(): array
    {
        return [
            [
                self::scenario(
                    "A",
                    [["rate" => 19.0, "tax" => 3.8, "gross" => 23.8]],
                    5.0,
                    0.0,
                    2880,
                    2592,
                    [2.38],
                    0.5,
                    [19 => 3.42],
                ),
            ],
        ];
    }

    /**
     * Scenario B starts from tax-inclusive catalog configuration. At webhook
     * settlement time Magento has persisted the same net, gross, and VAT shape
     * as Scenario A. This dataset protects the requirement that settlement use
     * persisted order values rather than current catalog tax configuration.
     */
    public static function scenarioB(): array
    {
        return [
            [
                self::scenario(
                    "B",
                    [["rate" => 19.0, "tax" => 3.8, "gross" => 23.8]],
                    5.0,
                    0.0,
                    2880,
                    2592,
                    [2.38],
                    0.5,
                    [19 => 3.42],
                ),
            ],
        ];
    }

    /**
     * Scenario C proves taxable shipping for tax-exclusive inputs. Cashback is
     * allocated over product and shipping gross amounts, so both product VAT
     * and shipping VAT must be reduced exactly once and every financial
     * document must settle to EUR 26.77.
     */
    public static function scenarioC(): array
    {
        return [
            [
                self::scenario(
                    "C",
                    [["rate" => 19.0, "tax" => 3.8, "gross" => 23.8]],
                    5.0,
                    0.95,
                    2975,
                    2677,
                    [2.38],
                    0.6,
                    [19 => 4.27],
                ),
            ],
        ];
    }

    /**
     * Scenario D starts from tax-inclusive product and shipping configuration.
     * Magento persists the same settlement shape as Scenario C. This protects
     * gross-input compatibility and prevents shipping cashback or shipping VAT
     * from being applied twice.
     */
    public static function scenarioD(): array
    {
        return [
            [
                self::scenario(
                    "D",
                    [["rate" => 19.0, "tax" => 3.8, "gross" => 23.8]],
                    5.0,
                    0.95,
                    2975,
                    2677,
                    [2.38],
                    0.6,
                    [19 => 4.27],
                ),
            ],
        ];
    }

    /**
     * Scenario E proves mixed-rate normalization. The 19% and 7% products must
     * receive independent cashback and VAT reductions, and each
     * sales_order_tax row must contain only the sum of its own tax-item rows.
     */
    public static function scenarioE(): array
    {
        return [
            [
                self::scenario(
                    "E",
                    [
                        ["rate" => 19.0, "tax" => 3.8, "gross" => 23.8],
                        ["rate" => 7.0, "tax" => 1.4, "gross" => 21.4],
                    ],
                    5.0,
                    0.0,
                    5020,
                    4518,
                    [2.38, 2.14],
                    0.5,
                    [19 => 3.42, 7 => 1.26],
                ),
            ],
        ];
    }

    /**
     * Scenario F protects deterministic cent allocation. EUR 4.99 untaxed
     * shipping produces EUR 2.88 cashback; product and shipping allocations
     * must sum to that value exactly and all documents must settle to EUR 25.91.
     */
    public static function scenarioF(): array
    {
        return [
            [
                self::scenario(
                    "F",
                    [["rate" => 19.0, "tax" => 3.8, "gross" => 23.8]],
                    4.99,
                    0.0,
                    2879,
                    2591,
                    [2.38],
                    0.5,
                    [19 => 3.42],
                ),
            ],
        ];
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    #[DataProvider("scenarioA")]
    #[DataProvider("scenarioB")]
    #[DataProvider("scenarioC")]
    #[DataProvider("scenarioD")]
    #[DataProvider("scenarioE")]
    #[DataProvider("scenarioF")]
    public function testCompletedPaymentReconcilesVatScenario(
        array $scenario,
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $order = $this->prepareOrder($scenario);
        $this->replaceTaxRows($order, $scenario);

        $providerTransactionId =
            "vat-scenario-" . strtolower($scenario["name"]);
        $attempts = $objectManager->get(PaymentAttemptRepository::class);
        $attempts->save(
            $attempts->create([
                "attempt_id" => "attempt-" . strtolower($scenario["name"]),
                "order_id" => (int) $order->getId(),
                "order_increment_id" => (string) $order->getIncrementId(),
                "quote_id" => $order->getQuoteId(),
                "store_id" => (int) $order->getStoreId(),
                "provider_transaction_id" => $providerTransactionId,
                "expected_amount_minor" => $scenario["original_minor"],
                "currency" => "EUR",
                "creation_state" => "created",
                "return_token_hash" => hash("sha256", $providerTransactionId),
            ]),
        );

        $objectManager
            ->get(CompletedPaymentHandler::class)
            ->execute(
                $providerTransactionId,
                $scenario["original_minor"],
                $scenario["final_minor"],
                "EUR",
                (string) $order->getIncrementId(),
            );

        $order = $objectManager->create(Order::class)->load($order->getId());
        $final = $scenario["final_minor"] / 100;
        $cashback =
            ($scenario["original_minor"] - $scenario["final_minor"]) / 100;
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        self::assertSame(
            Order::STATE_PROCESSING,
            $order->getState(),
            $scenario["name"],
        );
        self::assertSame(
            1,
            $order->getInvoiceCollection()->getSize(),
            $scenario["name"],
        );
        self::assertInstanceOf(Invoice::class, $invoice);
        self::assertSame(Invoice::STATE_PAID, (int) $invoice->getState());
        self::assertEquals(
            $cashback,
            (float) $order->getData("flizpay_cashback_amount"),
        );
        self::assertEquals(
            $scenario["shipping_cashback"],
            (float) $order->getData("flizpay_shipping_cashback_amount"),
        );
        self::assertEquals($final, (float) $order->getGrandTotal());
        self::assertEquals($final, (float) $invoice->getGrandTotal());
        self::assertEquals(
            $final,
            (float) $order->getPayment()->getAmountOrdered(),
        );
        self::assertEquals(
            $final,
            (float) $order->getPayment()->getAmountPaid(),
        );
        self::assertEquals($final, (float) $order->getTotalInvoiced());
        self::assertEquals($final, (float) $order->getTotalPaid());
        self::assertEquals(0.0, (float) $order->getTotalDue());

        $itemCashback = 0.0;
        foreach (
            array_values($order->getAllVisibleItems())
            as $index => $item
        ) {
            self::assertEquals(
                $scenario["item_cashback"][$index],
                (float) $item->getData("flizpay_cashback_amount"),
                $scenario["name"] . " item " . $index,
            );
            $itemCashback += (float) $item->getData("flizpay_cashback_amount");
        }
        self::assertEquals(
            $cashback,
            $itemCashback +
                (float) $order->getData("flizpay_shipping_cashback_amount"),
        );

        $persistedTaxRows = $connection->fetchAll(
            $connection
                ->select()
                ->from($resource->getTableName("sales_order_tax"), [
                    "tax_id",
                    "percent",
                    "amount",
                ])
                ->where("order_id = ?", (int) $order->getId()),
        );
        $taxRows = [];
        foreach ($persistedTaxRows as $taxRow) {
            $taxRows[(int) $taxRow["percent"]] = (float) $taxRow["amount"];
            $detailTotal = (float) $connection->fetchOne(
                $connection
                    ->select()
                    ->from(
                        $resource->getTableName("sales_order_tax_item"),
                        "SUM(amount)",
                    )
                    ->where("tax_id = ?", (int) $taxRow["tax_id"]),
            );
            self::assertEquals((float) $taxRow["amount"], $detailTotal);
        }
        foreach ($scenario["expected_tax_rows"] as $rate => $amount) {
            self::assertArrayHasKey($rate, $taxRows, $scenario["name"]);
            self::assertEquals($amount, $taxRows[$rate]);
        }
        self::assertEquals((float) $order->getTaxAmount(), array_sum($taxRows));

        $captureCount = (int) $connection->fetchOne(
            $connection
                ->select()
                ->from(
                    $resource->getTableName("sales_payment_transaction"),
                    "COUNT(*)",
                )
                ->where("order_id = ?", (int) $order->getId())
                ->where("txn_type = ?", "capture"),
        );
        self::assertSame(1, $captureCount);
        self::assertSame(
            1,
            (int) $connection->fetchOne(
                $connection
                    ->select()
                    ->from(
                        $resource->getTableName("flizpay_payment_attempt"),
                        "COUNT(*)",
                    )
                    ->where("order_id = ?", (int) $order->getId()),
            ),
        );
        self::assertSame(
            1,
            (int) $connection->fetchOne(
                $connection
                    ->select()
                    ->from(
                        $resource->getTableName("sales_payment_transaction"),
                        "COUNT(*)",
                    )
                    ->where("order_id = ?", (int) $order->getId()),
            ),
        );
        self::assertSame(
            0,
            (int) $connection->fetchOne(
                $connection
                    ->select()
                    ->from(
                        $resource->getTableName("sales_creditmemo"),
                        "COUNT(*)",
                    )
                    ->where("order_id = ?", (int) $order->getId()),
            ),
        );
        self::assertSame(
            "completed",
            $attempts
                ->getByProviderTransactionId($providerTransactionId)
                ->getData("provider_status"),
        );
    }

    private function prepareOrder(array $scenario): Order
    {
        $objectManager = Bootstrap::getObjectManager();
        $repository = $objectManager->get(OrderRepositoryInterface::class);
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId("100000001");
        self::assertNotNull($order->getId());

        $items = array_values($order->getAllVisibleItems());
        $template = $items[0];
        foreach (array_slice($items, 1) as $extra) {
            $extra->delete();
        }
        while (count($items) < count($scenario["items"])) {
            $item = $objectManager->get(ItemFactory::class)->create();
            $item->setData($template->getData());
            $item->setId(null);
            $item->setOrder($order);
            $item->setOrderId($order->getId());
            $item->setQuoteItemId(null);
            $item->setSku(
                "scenario-" . $scenario["name"] . "-" . count($items),
            );
            $item->setName(
                "Scenario " . $scenario["name"] . " item " . count($items),
            );
            $order->addItem($item);
            $items[] = $item;
        }

        $subtotal = 0.0;
        $subtotalInclTax = 0.0;
        $tax = 0.0;
        foreach ($scenario["items"] as $index => $definition) {
            $item = $items[$index];
            $net = $definition["gross"] - $definition["tax"];
            $item->setQtyOrdered(1.0);
            $item->setPrice($net);
            $item->setBasePrice($net);
            $item->setRowTotal($net);
            $item->setBaseRowTotal($net);
            $item->setPriceInclTax($definition["gross"]);
            $item->setBasePriceInclTax($definition["gross"]);
            $item->setRowTotalInclTax($definition["gross"]);
            $item->setBaseRowTotalInclTax($definition["gross"]);
            $item->setTaxPercent($definition["rate"]);
            $item->setTaxAmount($definition["tax"]);
            $item->setBaseTaxAmount($definition["tax"]);
            $item->setDiscountAmount(0.0);
            $item->setBaseDiscountAmount(0.0);
            $subtotal += $net;
            $subtotalInclTax += $definition["gross"];
            $tax += $definition["tax"];
        }

        $shippingGross = $scenario["shipping_net"] + $scenario["shipping_tax"];
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->setOrderCurrencyCode("EUR");
        $order->setBaseCurrencyCode("EUR");
        $order->setBaseToOrderRate(1.0);
        $order->setSubtotal($subtotal);
        $order->setBaseSubtotal($subtotal);
        $order->setSubtotalInclTax($subtotalInclTax);
        $order->setBaseSubtotalInclTax($subtotalInclTax);
        $order->setShippingAmount($scenario["shipping_net"]);
        $order->setBaseShippingAmount($scenario["shipping_net"]);
        $order->setShippingInclTax($shippingGross);
        $order->setBaseShippingInclTax($shippingGross);
        $order->setShippingTaxAmount($scenario["shipping_tax"]);
        $order->setBaseShippingTaxAmount($scenario["shipping_tax"]);
        $order->setShippingDiscountAmount(0.0);
        $order->setBaseShippingDiscountAmount(0.0);
        $order->setTaxAmount($tax + $scenario["shipping_tax"]);
        $order->setBaseTaxAmount($tax + $scenario["shipping_tax"]);
        $order->setDiscountAmount(0.0);
        $order->setBaseDiscountAmount(0.0);
        $order->setGrandTotal($scenario["original_minor"] / 100);
        $order->setBaseGrandTotal($scenario["original_minor"] / 100);
        $order->getPayment()->setMethod("flizpay");

        return $repository->save($order);
    }

    private function replaceTaxRows(Order $order, array $scenario): void
    {
        $resource = Bootstrap::getObjectManager()->get(
            ResourceConnection::class,
        );
        $connection = $resource->getConnection();
        $taxTable = $resource->getTableName("sales_order_tax");
        $taxItemTable = $resource->getTableName("sales_order_tax_item");
        $existingIds = $connection->fetchCol(
            $connection
                ->select()
                ->from($taxTable, "tax_id")
                ->where("order_id = ?", (int) $order->getId()),
        );
        if ($existingIds) {
            $connection->delete($taxItemTable, [
                "tax_id IN (?)" => $existingIds,
            ]);
        }
        $connection->delete($taxTable, [
            "order_id = ?" => (int) $order->getId(),
        ]);

        $taxIds = [];
        foreach ($scenario["items"] as $definition) {
            $rate = (int) $definition["rate"];
            if (isset($taxIds[$rate])) {
                continue;
            }
            $connection->insert($taxTable, [
                "order_id" => (int) $order->getId(),
                "code" => "DE VAT " . $rate . "%",
                "title" => "DE VAT " . $rate . "%",
                "percent" => $definition["rate"],
                "priority" => 0,
                "position" => 0,
                "amount" => 0.0,
                "base_amount" => 0.0,
                "base_real_amount" => 0.0,
                "process" => 0,
            ]);
            $taxIds[$rate] = (int) $connection->lastInsertId($taxTable);
        }

        foreach (
            array_values($order->getAllVisibleItems())
            as $index => $item
        ) {
            $definition = $scenario["items"][$index];
            $connection->insert($taxItemTable, [
                "tax_id" => $taxIds[(int) $definition["rate"]],
                "item_id" => (int) $item->getId(),
                "tax_percent" => $definition["rate"],
                "amount" => $definition["tax"],
                "base_amount" => $definition["tax"],
                "real_amount" => $definition["tax"],
                "real_base_amount" => $definition["tax"],
                "taxable_item_type" => "product",
            ]);
        }

        if ($scenario["shipping_tax"] > 0) {
            $rate = (int) $scenario["items"][0]["rate"];
            $connection->insert($taxItemTable, [
                "tax_id" => $taxIds[$rate],
                "item_id" => null,
                "tax_percent" => $rate,
                "amount" => $scenario["shipping_tax"],
                "base_amount" => $scenario["shipping_tax"],
                "real_amount" => $scenario["shipping_tax"],
                "real_base_amount" => $scenario["shipping_tax"],
                "taxable_item_type" => "shipping",
            ]);
        }
    }

    private static function scenario(
        string $name,
        array $items,
        float $shippingNet,
        float $shippingTax,
        int $originalMinor,
        int $finalMinor,
        array $itemCashback,
        float $shippingCashback,
        array $expectedTaxRows,
    ): array {
        return [
            "name" => $name,
            "items" => $items,
            "shipping_net" => $shippingNet,
            "shipping_tax" => $shippingTax,
            "original_minor" => $originalMinor,
            "final_minor" => $finalMinor,
            "item_cashback" => $itemCashback,
            "shipping_cashback" => $shippingCashback,
            "expected_tax_rows" => $expectedTaxRows,
        ];
    }
}
