<?php

declare(strict_types=1);

namespace FlizPay\Payment\Controller\Payment;

use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reports Magento-owned settlement state without changing the payment.
 */
class Status implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ReturnContextValidator $returnContextValidator,
        private readonly StoreManagerInterface $storeManager,
    ) {}

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader("Cache-Control", "no-store, private", true);

        try {
            $context = $this->returnContextValidator->validate(
                (string) $this->request->getParam("token"),
                (int) $this->storeManager->getStore()->getId(),
            );

            return $result->setData([
                "status" => $context->getPublicStatus(),
            ]);
        } catch (\Throwable) {
            return $result
                ->setHttpResponseCode(404)
                ->setData([
                    "message" => (string) __("Payment return is invalid."),
                ]);
        }
    }
}
