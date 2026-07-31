<?php

declare(strict_types=1);

namespace FlizPay\Payment\Service\Payment;

use Magento\Quote\Api\CartRepositoryInterface;

class QuoteRestorer
{
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
    ) {}

    public function restore(?int $quoteId): void
    {
        if (!$quoteId) {
            return;
        }

        $quote = $this->quoteRepository->get($quoteId);
        $quote->setIsActive(true);
        $quote->setReservedOrderId("");
        $this->quoteRepository->save($quote);
    }
}
