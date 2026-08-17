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

namespace FlizPay\Payment\ViewModel\Adminhtml;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Resolves credit memo notice visibility from explicit request context.
 */
class CreditmemoNotice implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {}

    public function shouldShow(): bool
    {
        try {
            $invoiceId = (int) $this->request->getParam("invoice_id");
            $orderId = (int) $this->request->getParam("order_id");

            if ($invoiceId > 0) {
                $invoice = $this->invoiceRepository->get($invoiceId);
                $invoiceOrderId = (int) $invoice->getOrderId();

                if ($orderId > 0 && $orderId !== $invoiceOrderId) {
                    return false;
                }

                $orderId = $invoiceOrderId;
            }

            if ($orderId <= 0) {
                return false;
            }

            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();

            return $payment !== null &&
                $payment->getMethod() === ConfigInterface::METHOD_CODE;
        } catch (NoSuchEntityException) {
            return false;
        }
    }
}
