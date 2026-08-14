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

namespace FlizPay\Payment\Model;

use FlizPay\Payment\Model\ResourceModel\PaymentAttempt as PaymentAttemptResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Payment attempt persistence model.
 */
class PaymentAttempt extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PaymentAttemptResource::class);
    }
}
