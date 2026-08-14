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
