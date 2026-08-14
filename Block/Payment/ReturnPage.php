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

namespace FlizPay\Payment\Block\Payment;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Supplies safe browser-return URLs to storefront templates.
 */
class ReturnPage extends Template
{
    /**
     * @param Context $context
     * @param Json $json
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Json $json,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getStatusUrl(): string
    {
        return $this->getUrl("flizpay/payment/status", [
            "token" => (string) $this->getData("return_token"),
            "_secure" => true,
        ]);
    }

    public function getSuccessUrl(): string
    {
        return $this->getUrl("flizpay/payment/success", [
            "token" => (string) $this->getData("return_token"),
            "_secure" => true,
        ]);
    }

    public function getPollingConfiguration(): string
    {
        return $this->json->serialize([
            "FlizPay_Payment/js/payment-status" => [
                "statusUrl" => $this->getStatusUrl(),
                "successUrl" => $this->getSuccessUrl(),
                "interval" => 2000,
                "attempts" => 30,
            ],
        ]);
    }
}
