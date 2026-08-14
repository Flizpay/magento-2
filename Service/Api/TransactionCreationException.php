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

namespace FlizPay\Payment\Service\Api;

use Magento\Framework\Exception\LocalizedException;

class TransactionCreationException extends LocalizedException
{
    public const API_REJECTED = "api_rejected";
    public const API_AUTHENTICATION_FAILED = "api_authentication_failed";
    public const API_INVALID_RESPONSE = "api_invalid_response";
    public const API_TRANSPORT_ERROR = "api_transport_error";
    public const API_IDEMPOTENCY_CONFLICT = "api_idempotency_conflict";

    public function __construct(
        private readonly string $safeErrorCode,
        private readonly bool $definite,
    ) {
        parent::__construct(__("Unable to connect Magento to FLIZpay."));
    }

    public function getSafeErrorCode(): string
    {
        return $this->safeErrorCode;
    }

    public function isDefinite(): bool
    {
        return $this->definite;
    }
}
