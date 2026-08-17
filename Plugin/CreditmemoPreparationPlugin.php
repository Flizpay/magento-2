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

use FlizPay\Payment\Service\Refund\FullOfflineCreditmemoValidator;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;

/**
 * Rejects partial FLIZpay credit memo input before total collection.
 */
class CreditmemoPreparationPlugin
{
    public function __construct(
        private readonly FullOfflineCreditmemoValidator $validator,
    ) {}

    /**
     * @param CreditmemoFactory $subject
     * @param Order $order
     * @param array<string, mixed> $data
     * @return array{Order, array<string, mixed>}
     */
    public function beforeCreateByOrder(
        CreditmemoFactory $subject,
        Order $order,
        array $data = [],
    ): array {
        $this->validator->validatePreparation($order, $data);

        return [$order, $data];
    }

    /**
     * @param CreditmemoFactory $subject
     * @param Invoice $invoice
     * @param array<string, mixed> $data
     * @return array{Invoice, array<string, mixed>}
     */
    public function beforeCreateByInvoice(
        CreditmemoFactory $subject,
        Invoice $invoice,
        array $data = [],
    ): array {
        $this->validator->validatePreparation($invoice->getOrder(), $data);

        return [$invoice, $data];
    }
}
