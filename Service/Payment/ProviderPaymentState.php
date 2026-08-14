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

namespace FlizPay\Payment\Service\Payment;

class ProviderPaymentState
{
    public const PENDING = "pending";
    public const PROCESSING = "processing";
    public const COMPLETED = "completed";
    public const FAILED = "failed";
    public const CANCELED = "canceled";

    private const SUPPORTED = [
        self::PENDING,
        self::PROCESSING,
        self::COMPLETED,
        self::FAILED,
        self::CANCELED,
    ];

    public static function normalize(string $status): string
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::SUPPORTED, true)) {
            throw new \InvalidArgumentException("Unsupported webhook status.");
        }

        return $status;
    }

    public static function isTerminal(string $status): bool
    {
        return in_array(
            $status,
            [self::COMPLETED, self::FAILED, self::CANCELED],
            true,
        );
    }

    public static function isFailure(string $status): bool
    {
        return in_array($status, [self::FAILED, self::CANCELED], true);
    }
}
