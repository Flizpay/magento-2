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

/**
 * Immutable, non-sensitive cashback presentation data for checkout.
 */
class CashbackDisplay
{
    public function __construct(
        private readonly bool $available,
        private readonly ?string $type,
        private readonly ?string $formattedValue,
        private readonly string $title,
        private readonly ?string $description,
        private readonly bool $showLogo,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            "available" => $this->available,
            "type" => $this->type,
            "formattedValue" => $this->formattedValue,
            "title" => $this->title,
            "description" => $this->description,
            "showLogo" => $this->showLogo,
        ];
    }
}
