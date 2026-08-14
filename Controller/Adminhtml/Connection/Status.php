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

namespace FlizPay\Payment\Controller\Adminhtml\Connection;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Returns connection state to the Admin polling client.
 */
class Status extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = "Magento_Config::config";

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ConfigInterface $config
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ConfigInterface $config,
    ) {
        parent::__construct($context);
    }

    /**
     * Return the current persisted connection state.
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonFactory->create()->setData([
            "status" => $this->config->getConnectionStatus(),
            "verifiedAt" => $this->config->getConnectionVerifiedAt(),
        ]);
    }
}
