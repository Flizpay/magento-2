<?php

declare(strict_types=1);

namespace FlizPay\Payment\Controller\Payment;

use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reports a browser return without mutating payment state.
 */
class Failure implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RawFactory $rawFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ReturnContextValidator $returnContextValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpResponse $response,
    ) {}

    public function execute(): Page|Raw|Redirect
    {
        $this->response->setNoCacheHeaders();

        try {
            $token = (string) $this->request->getParam("token");
            $context = $this->returnContextValidator->validate(
                $token,
                (int) $this->storeManager->getStore()->getId(),
            );

            if ($context->isComplete()) {
                return $this->redirectFactory
                    ->create()
                    ->setPath("flizpay/payment/success", [
                        "token" => $token,
                        "_secure" => true,
                    ]);
            }

            return $this->pageFactory->create();
        } catch (\Throwable) {
            return $this->rawFactory
                ->create()
                ->setHttpResponseCode(404)
                ->setContents((string) __("Payment return is invalid."));
        }
    }
}
