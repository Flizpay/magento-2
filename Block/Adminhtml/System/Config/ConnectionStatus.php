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

namespace FlizPay\Payment\Block\Adminhtml\System\Config;

use FlizPay\Payment\Api\ConfigInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Displays and polls merchant connection state.
 */
class ConnectionStatus extends Field
{
    /**
     * Initialize the connection-status renderer.
     *
     * @param Context $context
     * @param ConfigInterface $config
     * @param Json $json
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ConfigInterface $config,
        private readonly Json $json,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render connection status and initialize polling.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $status = $this->config->getConnectionStatus();
        $verifiedAt = $this->config->getConnectionVerifiedAt();
        $initialization = $this->json->serialize([
            "#flizpay-connection-status" => [
                "FlizPay_Payment/js/connection-status" => [
                    "url" => $this->getUrl("flizpay/connection/status"),
                    "status" => $status,
                ],
            ],
        ]);

        return sprintf(
            '<strong id="flizpay-connection-status">%s</strong>' .
                '<div id="flizpay-connection-verified">%s</div>' .
                '<script type="text/x-magento-init">%s</script>',
            $this->escapeHtml($this->statusLabel($status)),
            $this->escapeHtml($this->verifiedLabel($verifiedAt)),
            $initialization,
        );
    }

    /**
     * Return a merchant-facing label for a connection state.
     *
     * @param string $status
     * @return string
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            ConfigInterface::CONNECTION_CONNECTING => (string) __("Connecting"),
            ConfigInterface::CONNECTION_CONNECTED => (string) __("Connected"),
            ConfigInterface::CONNECTION_FAILED => (string) __(
                "Connection failed",
            ),
            default => (string) __("Not connected"),
        };
    }

    /**
     * Format the last successful verification timestamp.
     *
     * @param string $verifiedAt
     * @return string
     */
    private function verifiedLabel(string $verifiedAt): string
    {
        return $verifiedAt === ""
            ? ""
            : (string) __("Last verified: %1 UTC", $verifiedAt);
    }
}
