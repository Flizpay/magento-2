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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
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
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ManagerInterface $messageManager,
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
                return $this->redirectFactory
                    ->create()
                    ->setPath("flizpay/payment/success", [
                        "token" => $token,
                        "_secure" => true,
                    ]);
            }

            if ($context->isTerminalFailure()) {
                $quoteId = $context->getAttempt()->getData("quote_id");
                if ($quoteId !== null) {
                    $quote = $this->quoteRepository->get((int) $quoteId);
                    if ($quote instanceof Quote && $quote->getIsActive()) {
                        $this->checkoutSession->replaceQuote($quote);
                    }
                }
                $this->messageManager->addErrorMessage(
                    __(
                        "Your FLIZpay payment was not completed. Please try again.",
                    ),
                );

                return $this->redirectFactory
                    ->create()
                    ->setPath("checkout", ["_secure" => true]);
            }

            $page = $this->pageFactory->create();
            $page->getConfig()->getTitle()->set(
                (string) __("Payment not completed"),
            );

            return $page;
        } catch (\Throwable $exception) {
            $this->logger->warning("FLIZpay return rejected", [
                "route" => "failure",
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
