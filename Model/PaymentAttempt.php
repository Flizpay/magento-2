<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay (https://flizpay.de).
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH (https://flizpay.de)
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
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
