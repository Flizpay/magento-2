<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay (https://flizpay.de).
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH (https://flizpay.de)
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 * @link    https://flizpay.de
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Logging;

use FlizPay\Payment\Api\ConfigInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Module log channel writing to var/log/flizpay.log.
 *
 * Notices, warnings, and errors are always recorded so operational failures
 * stay diagnosable. Info and debug entries are recorded only while the
 * "Enable Debug Logging" setting is active.
 *
 * Callers must pass only safe scalar context: order increment IDs, attempt
 * IDs, exception classes, safe error codes, HTTP statuses, and API paths.
 * Credentials, signatures, request or response bodies, return URLs, and
 * customer data must never reach this logger.
 */
class PaymentLogger extends AbstractLogger
{
    private const GATED_LEVELS = [LogLevel::INFO, LogLevel::DEBUG];

    /**
     * @param LoggerInterface $logger
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConfigInterface $config,
    ) {}

    /**
     * Forward a record to the module channel, gating verbose levels.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function log(
        $level,
        string|\Stringable $message,
        array $context = [],
    ): void {
        if (
            in_array($level, self::GATED_LEVELS, true) &&
            !$this->config->isLoggingEnabled()
        ) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
