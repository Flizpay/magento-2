<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Creates payment-attempt models without relying on generated code in analysis.
 */
class PaymentAttemptFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly string $instanceName = PaymentAttempt::class,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data = []): PaymentAttempt
    {
        return $this->objectManager->create($this->instanceName, $data);
    }
}
