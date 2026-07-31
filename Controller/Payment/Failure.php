<?php

declare(strict_types=1);

namespace FlizPay\Payment\Controller\Payment;

use FlizPay\Payment\Service\Payment\PaymentAttemptRepository;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

/**
 * Reports a browser return without mutating payment state.
 */
class Failure implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly PaymentAttemptRepository $attemptRepository,
    ) {}

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();

        try {
            $token = (string) $this->request->getParam("token");

            if ($token === "") {
                throw new \RuntimeException("Missing token.");
            }

            $this->attemptRepository->getByReturnTokenHash(
                hash("sha256", $token),
            );

            return $result->setContents(
                (string) __(
                    "The FLIZpay payment was not completed. Your order has not been marked as paid.",
                ),
            );
        } catch (\Throwable) {
            return $result
                ->setHttpResponseCode(404)
                ->setContents((string) __("Payment return is invalid."));
        }
    }
}
