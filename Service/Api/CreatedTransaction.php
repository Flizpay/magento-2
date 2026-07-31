<?php
/**
 * FLIZpay Magento 2
 *
 * @package FlizPay_Payment
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 */

declare(strict_types=1);

namespace FlizPay\Payment\Service\Api;

/**
 * Validated transaction creation result.
 */
class CreatedTransaction
{
    private function __construct(
        private readonly string $transactionId,
        private readonly string $redirectUrl,
    ) {}

    /**
     * @param array<string, mixed> $response
     * @param list<string> $allowedRedirectHosts
     * @return self
     */
    public static function fromResponse(
        array $response,
        array $allowedRedirectHosts = ["secure.flizpay.de"],
    ): self {
        $transactionId = $response["transactionId"] ?? null;
        $redirectUrl = $response["redirectUrl"] ?? null;

        if (!is_string($transactionId) || trim($transactionId) === "") {
            throw new \UnexpectedValueException(
                "FLIZpay transaction ID is missing.",
            );
        }

        if (!is_string($redirectUrl) || trim($redirectUrl) === "") {
            throw new \UnexpectedValueException(
                "FLIZpay redirect URL is missing.",
            );
        }

        $redirectUrl = trim($redirectUrl);
        $parts = parse_url($redirectUrl);
        $host = is_array($parts)
            ? strtolower((string) ($parts["host"] ?? ""))
            : "";

        if (
            !is_array($parts) ||
            strtolower((string) ($parts["scheme"] ?? "")) !== "https" ||
            $host === "" ||
            !in_array($host, $allowedRedirectHosts, true) ||
            isset($parts["user"]) ||
            isset($parts["pass"])
        ) {
            throw new \UnexpectedValueException(
                "FLIZpay redirect URL is invalid.",
            );
        }

        return new self(trim($transactionId), $redirectUrl);
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }
}
