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

namespace FlizPay\Payment\Controller\Payment;

use FlizPay\Payment\Service\Logging\PaymentLogger;
use FlizPay\Payment\Service\Payment\CreateTransactionService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Receives the POST handoff after Magento persists the order.
 */
class Start implements HttpPostActionInterface
{
    /**
     * @param RedirectFactory $redirectFactory
     */
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly RawFactory $rawFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreateTransactionService $createTransactionService,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * Continue to Magento success until provider initiation is added.
     *
     * @return Redirect
     */
    public function execute(): Redirect|Raw
    {
        $orderId = (int) $this->checkoutSession->getData("last_order_id");

        try {
            $order = $this->orderRepository->get($orderId);

            if (!$order instanceof Order) {
                throw new \RuntimeException(
                    "Unsupported order implementation.",
                );
            }

            $redirectUrl = $this->createTransactionService->execute($order);

            $result = $this->redirectFactory->create();
            $result->setHttpResponseCode(303);

            return $result->setUrl($redirectUrl);
        } catch (\Throwable $exception) {
            $this->logger->error("FLIZpay payment start failed", [
                "order_id" => $orderId,
                "exception" => get_class($exception),
                "message" => $exception->getMessage(),
            ]);
            return $this->rawFactory
                ->create()
                ->setHttpResponseCode(503)
                ->setContents(
                    (string) __(
                        "FLIZpay could not be started. Your order has not been paid.",
                    ),
                );
        }
    }
}
