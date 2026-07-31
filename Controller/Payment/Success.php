<?php

declare(strict_types=1);

namespace FlizPay\Payment\Controller\Payment;

use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Displays payment state without settling an order.
 */
class Success implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly PaymentAttemptRepository $attemptRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();

        try {
            $token = (string) $this->request->getParam("token");

            if ($token === "") {
                throw new \RuntimeException("Missing token.");
            }
            $attempt = $this->attemptRepository->getByReturnTokenHash(
                hash("sha256", $token),
            );
            $order = $this->orderRepository->get(
                (int) $attempt->getData("order_id"),
            );

            if (!$order instanceof Order) {
                throw new \RuntimeException("Invalid order.");
            }

            $paid = $order->getInvoiceCollection()->getSize() > 0;

            return $result->setContents(
                (string) __(
                    $paid
                        ? "Your FLIZpay payment is confirmed."
                        : "Your FLIZpay payment is being confirmed. You may close this page.",
                ),
            );
        } catch (\Throwable) {
            return $result
                ->setHttpResponseCode(404)
                ->setContents((string) __("Payment return is invalid."));
        }
    }
}
