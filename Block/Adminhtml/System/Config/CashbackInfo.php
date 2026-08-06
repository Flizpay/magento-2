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

namespace FlizPay\Payment\Block\Adminhtml\System\Config;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Cashback\PercentageFormatter;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Displays the provider-configured cashback rates.
 */
class CashbackInfo extends Field
{
    private const TEMPLATE = "FlizPay_Payment::system/config/cashback-info.phtml";

    /**
     * Initialize the cashback-info renderer.
     *
     * @param Context $context
     * @param ConfigInterface $config
     * @param PercentageFormatter $percentageFormatter
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ConfigInterface $config,
        private readonly PercentageFormatter $percentageFormatter,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the cashback information markup instead of a form input.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setTemplate(self::TEMPLATE);

        return $this->toHtml();
    }

    /**
     * Whether an active cashback campaign exists.
     *
     * @return bool
     */
    public function hasCashback(): bool
    {
        $data = $this->config->getCashbackData();

        return $data !== null &&
            ($data["first_purchase_amount"] > 0 ||
                $data["standard_amount"] > 0);
    }

    /**
     * Return the formatted first-purchase cashback rate, or empty string.
     *
     * @return string
     */
    public function getFirstPurchaseRate(): string
    {
        $data = $this->config->getCashbackData();

        if ($data === null || $data["first_purchase_amount"] <= 0) {
            return "";
        }

        return $this->percentageFormatter->format(
            $data["first_purchase_amount"],
        );
    }

    /**
     * Return the formatted standard cashback rate, or empty string.
     *
     * @return string
     */
    public function getStandardRate(): string
    {
        $data = $this->config->getCashbackData();

        if ($data === null || $data["standard_amount"] <= 0) {
            return "";
        }

        return $this->percentageFormatter->format($data["standard_amount"]);
    }
}
