<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 */

declare(strict_types=1);

namespace FlizPay\Payment\Observer;

use FlizPay\Payment\Service\Connection\ConnectionManager;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Starts merchant connection after payment configuration is saved.
 */
class ConnectAfterConfigSave implements ObserverInterface
{
    /**
     * @param ConnectionManager $connectionManager
     * @param ManagerInterface $messageManager
     * @param PaymentLogger $logger
     */
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly ManagerInterface $messageManager,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * Start connection setup after payment configuration is saved.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            if ($this->connectionManager->connectIfNeeded()) {
                $this->messageManager->addNoticeMessage(
                    __("FLIZpay is waiting for webhook verification."),
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                "FLIZpay connection failed after configuration save",
                [
                    "exception" => get_class($exception),
                    "message" => $exception->getMessage(),
                ],
            );
            $this->messageManager->addErrorMessage(
                __(
                    "Magento could not connect to FLIZpay. Verify the API key and try again.",
                ),
            );
        }
    }
}
