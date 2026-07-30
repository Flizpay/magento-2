<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Webhook;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Webhook\WebhookAuthenticator;
use PHPUnit\Framework\TestCase;

class WebhookAuthenticatorTest extends TestCase
{
    public function testAuthenticatesExactRawBody(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method("getWebhookSecret")->willReturn("secret");
        $authenticator = new WebhookAuthenticator($config);
        $body = '{"status":"completed"}';

        self::assertTrue(
            $authenticator->authenticate(
                $body,
                hash_hmac("sha256", $body, "secret"),
            ),
        );
        self::assertFalse(
            $authenticator->authenticate(
                $body . "\n",
                hash_hmac("sha256", $body, "secret"),
            ),
        );
    }
}
