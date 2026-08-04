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

namespace FlizPay\Payment\Model\Ui;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Cashback\CashbackDisplayBuilder;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;

/**
 * Supplies the non-sensitive checkout POST handoff configuration.
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly CashbackDisplayBuilder $cashbackDisplayBuilder,
    ) {}

    /**
     * Return checkout handoff configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig()
    {
        return [
            "payment" => [
                ConfigInterface::METHOD_CODE => [
                    "startUrl" => $this->urlBuilder->getUrl(
                        "flizpay/payment/start",
                    ),
                    "formKey" => $this->formKey->getFormKey(),
                    "cashback" => $this->cashbackDisplayBuilder
                        ->build()
                        ->toArray(),
                ],
            ],
        ];
    }
}
