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

namespace FlizPay\Payment\Service\Cashback;

use Magento\Framework\Locale\ResolverInterface;
use NumberFormatter;

/**
 * Formats cashback percentages for the active locale.
 */
class PercentageFormatter
{
    /**
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private readonly ResolverInterface $localeResolver,
    ) {}

    /**
     * Format a percentage value with at most one fraction digit.
     *
     * @param float $value
     * @return string
     */
    public function format(float $value): string
    {
        $formatter = new NumberFormatter(
            $this->localeResolver->getLocale(),
            NumberFormatter::DECIMAL,
        );
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
        $formattedValue = $formatter->format($value);

        return is_string($formattedValue) ? $formattedValue : (string) $value;
    }
}
