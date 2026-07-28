<?php

declare(strict_types=1);

namespace FlizPay\Payment\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;

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
    ) {}

    /**
     * Continue to Magento success until provider initiation is added.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $result = $this->redirectFactory->create();

        return $result->setPath("checkout/onepage/success");
    }
}
