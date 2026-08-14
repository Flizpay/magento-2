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

namespace FlizPay\Payment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order;

/**
 * Initializes a persisted FlizPay order without contacting the provider.
 */
class InitializeCommand implements CommandInterface
{
    /**
     * Set Magento's initial order state.
     *
     * @param array $commandSubject
     * @return null
     * @phpstan-param array<string, mixed> $commandSubject
     */
    public function execute(array $commandSubject)
    {
        $stateObject = SubjectReader::readStateObject($commandSubject);
        $stateObject->setData("state", Order::STATE_PENDING_PAYMENT);
        $stateObject->setData("status", Order::STATE_PENDING_PAYMENT);
        $stateObject->setData("is_notified", false);

        return null;
    }
}
