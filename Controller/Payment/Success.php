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

use FlizPay\Payment\Block\Payment\ReturnPage;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use FlizPay\Payment\Service\Payment\ReturnContextValidator;
use Magento\Checkout\Model\Session as CheckoutSession;
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
 * Displays payment state without settling an order.
 */
class Success implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RawFactory $rawFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ReturnContextValidator $returnContextValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly CheckoutSession $checkoutSession,
        private readonly HttpResponse $response,
        private readonly PaymentLogger $logger,
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
                $order = $context->getOrder();
                $quoteId = (int) $order->getQuoteId();
                // Magic checkout-session setters are proxied to session
                // storage and satisfy Magento's native success-page validator.
                // @phpstan-ignore-next-line
                $this->checkoutSession
                    ->setLastQuoteId($quoteId)
                    ->setLastSuccessQuoteId($quoteId)
                    ->setLastOrderId((int) $order->getEntityId())
                    ->setLastRealOrderId((string) $order->getIncrementId());

                return $this->redirectFactory
                    ->create()
                    ->setPath("checkout/onepage/success");
            }

            $page = $this->pageFactory->create();
            $block = $page->getLayout()->getBlock("flizpay.payment.pending");
            if ($block instanceof ReturnPage) {
                $block->setData("return_token", $token);
            }

            return $page;
        } catch (\Throwable $exception) {
            $this->logger->warning("FLIZpay return rejected", [
                "route" => "success",
                "exception" => get_class($exception),
                "message" => $exception->getMessage(),
            ]);

            return $this->rawFactory
                ->create()
                ->setHttpResponseCode(404)
                ->setContents((string) __("Payment return is invalid."));
        }
    }
}
