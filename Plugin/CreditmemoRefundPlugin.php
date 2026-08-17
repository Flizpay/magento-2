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

namespace FlizPay\Payment\Plugin;

use FlizPay\Payment\Service\Refund\FullOfflineCreditmemoValidator;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Service\CreditmemoService;

/**
 * Enforces offline/full policy at the final admin and service boundary.
 */
class CreditmemoRefundPlugin
{
    public function __construct(
        private readonly FullOfflineCreditmemoValidator $validator,
    ) {}

    /** @return array{CreditmemoInterface, bool} */
    public function beforeRefund(
        CreditmemoService $subject,
        CreditmemoInterface $creditmemo,
        bool $offlineRequested = false,
    ): array {
        if ($creditmemo instanceof Creditmemo) {
            $this->validator->validateRefund($creditmemo, $offlineRequested);
        }

        return [$creditmemo, $offlineRequested];
    }
}
