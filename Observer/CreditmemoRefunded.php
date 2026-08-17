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

namespace FlizPay\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Completes local invoice bookkeeping for an offline FLIZpay refund.
 */
class CreditmemoRefunded implements ObserverInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {}

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getData("creditmemo");
        if (
            !$creditmemo instanceof Creditmemo ||
            (int) $creditmemo->getData("flizpay_full_refund") !== 1
        ) {
            return;
        }

        $invoice = $creditmemo->getInvoice();
        if ($invoice !== null) {
            $invoice->setIsUsedForRefund(1);
            $invoice->setBaseTotalRefunded(
                (float) $invoice->getBaseGrandTotal(),
            );
            $this->invoiceRepository->save($invoice);
        }

        $creditmemo
            ->getOrder()
            ->addCommentToStatusHistory(
                (string) __(
                    "FLIZpay full offline credit memo was created. No refund request was sent to FLIZpay.",
                ),
            );
    }
}
