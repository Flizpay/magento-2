<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay.
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 * @link    https://flizpay.de
 */

declare(strict_types=1);

namespace FlizPay\Payment\Plugin;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\RefundInvoice;

/**
 * Prevents the REST invoice endpoint from requesting an online refund.
 */
class RefundInvoicePlugin
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @param RefundInvoice $subject
     * @param int $invoiceId
     * @param array<int, mixed> $items
     * @param bool $isOnline
     * @param mixed ...$remainingArguments
     * @return array<int, mixed>
     */
    public function beforeExecute(
        RefundInvoice $subject,
        $invoiceId,
        array $items = [],
        $isOnline = false,
        ...$remainingArguments,
    ): array {
        $invoice = $this->invoiceRepository->get((int) $invoiceId);
        $order = $this->orderRepository->get((int) $invoice->getOrderId());
        if (
            $order->getPayment()->getMethod() ===
                ConfigInterface::METHOD_CODE &&
            (bool) $isOnline
        ) {
            throw new LocalizedException(
                __(
                    "FLIZpay online refunds are not supported. Choose Refund Offline.",
                ),
            );
        }

        return [$invoiceId, $items, $isOnline, ...$remainingArguments];
    }
}
