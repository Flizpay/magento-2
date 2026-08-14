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

namespace FlizPay\Payment\Controller\Webhook;

use FlizPay\Payment\Service\Connection\ConnectionManager;
use FlizPay\Payment\Service\Connection\ConnectionConfigWriter;
use FlizPay\Payment\Service\Webhook\WebhookAuthenticator;
use FlizPay\Payment\Service\Webhook\WebhookPayload;
use FlizPay\Payment\Service\Webhook\WebhookProcessor;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

/**
 * Receives signed callbacks at the webhook URL shared with other plugins.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param Http $request
     * @param JsonFactory $jsonFactory
     * @param JsonSerializer $jsonSerializer
     * @param WebhookAuthenticator $authenticator
     * @param ConnectionManager $connectionManager
     * @param ConnectionConfigWriter $connectionConfigWriter
     * @param WebhookProcessor $webhookProcessor
     */
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $jsonFactory,
        private readonly JsonSerializer $jsonSerializer,
        private readonly WebhookAuthenticator $authenticator,
        private readonly ConnectionManager $connectionManager,
        private readonly ConnectionConfigWriter $connectionConfigWriter,
        private readonly WebhookProcessor $webhookProcessor,
    ) {}

    /**
     * Authenticate and acknowledge the merchant connection test.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $rawBody = $this->request->getContent();
        $signature = (string) $this->request->getHeader("X-FLIZ-SIGNATURE");

        if (!$this->authenticator->authenticate($rawBody, $signature)) {
            return $result
                ->setHttpResponseCode(401)
                ->setData(["error" => "Invalid signature"]);
        }

        try {
            $payload = $this->jsonSerializer->unserialize($rawBody);

            if (!is_array($payload)) {
                return $result
                    ->setHttpResponseCode(400)
                    ->setData(["error" => "Invalid webhook"]);
            }

            if (($payload["test"] ?? null) === true) {
                $this->connectionManager->confirmWebhookConnection();

                return $result->setData(["data" => ["alive" => true]]);
            }

            if (isset($payload["updateCashbackInfo"])) {
                $this->connectionConfigWriter->replaceCashbackData([
                    "first_purchase_amount" =>
                        (float) ($payload["firstPurchaseAmount"] ?? 0),
                    "standard_amount" => (float) ($payload["amount"] ?? 0),
                ]);

                return $result->setData([
                    "success" => true,
                    "message" => "Cashback information updated",
                ]);
            }

            $this->webhookProcessor->process(
                WebhookPayload::fromArray($payload),
            );

            return $result->setData(["data" => ["received" => true]]);
        } catch (\InvalidArgumentException) {
            return $result
                ->setHttpResponseCode(400)
                ->setData(["error" => "Unsupported webhook"]);
        } catch (\Throwable) {
            return $result
                ->setHttpResponseCode(500)
                ->setData(["error" => "Webhook processing failed"]);
        }
    }

    /**
     * HMAC authentication replaces form-key validation for this route.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Do not create a form-key exception for provider callbacks.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request,
    ): ?InvalidRequestException {
        return null;
    }
}
