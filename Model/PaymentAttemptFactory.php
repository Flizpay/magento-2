<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
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
