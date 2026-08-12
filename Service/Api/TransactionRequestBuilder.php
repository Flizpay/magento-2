<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds the supported FLIZpay transaction payload from a persisted order.
 */
class TransactionRequestBuilder
{
    /**
     * @param OrderInterface $order
     * @param string $attemptId
     * @param string $successUrl
     * @param string $failureUrl
     * @return array<string, mixed>
     */
    public function build(
        OrderInterface $order,
        string $attemptId,
        string $successUrl,
        string $failureUrl,
    ): array {
        $currency = strtoupper(trim((string) $order->getOrderCurrencyCode()));
        $externalId = trim((string) $order->getIncrementId());
        $attemptId = trim($attemptId);
        $successUrl = trim($successUrl);
        $failureUrl = trim($failureUrl);

        if (
            $currency === "" ||
            $externalId === "" ||
            $attemptId === "" ||
            $successUrl === "" ||
            $failureUrl === ""
        ) {
            throw new \InvalidArgumentException(
                "FLIZpay transaction fields must not be empty.",
            );
        }

        $request = [
            "amount" => $this->formatAmount($order->getGrandTotal()),
            "currency" => $currency,
            "externalId" => $externalId,
            "source" => "plugin",
            "successUrl" => $successUrl,
            "failureUrl" => $failureUrl,
        ];

        $customer = array_filter(
            [
                "email" => trim((string) $order->getCustomerEmail()),
                "firstName" => trim((string) $order->getCustomerFirstname()),
                "lastName" => trim((string) $order->getCustomerLastname()),
            ],
            static fn(string $value): bool => $value !== "",
        );

        if ($customer !== []) {
            $request["customer"] = $customer;
        }

        $request["metadata"] = [
            "platform" => "magento",
            "magentoOrderId" => (string) $order->getEntityId(),
            "storeId" => (string) $order->getStoreId(),
            "attemptId" => $attemptId,
        ];

        return $request;
    }

    /**
     * @param float|string|int|null $amount
     * @return string
     */
    private function formatAmount(float|string|int|null $amount): string
    {
        if (
            $amount === null ||
            !is_numeric($amount) ||
            !is_finite((float) $amount) ||
            (float) $amount <= 0
        ) {
            throw new \InvalidArgumentException(
                "Magento order grand total is invalid.",
            );
        }

        return number_format((float) $amount, 2, ".", "");
    }
}
