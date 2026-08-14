<?php

declare(strict_types=1);

namespace FlizPay\Payment\Test\Unit\Service\Logging;

use FlizPay\Payment\Api\ConfigInterface;
use FlizPay\Payment\Service\Logging\PaymentLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class PaymentLoggerTest extends TestCase
{
    public function testErrorIsAlwaysLogged(): void
    {
        $inner = $this->createMock(LoggerInterface::class);
        $inner
            ->expects(self::once())
            ->method("log")
            ->with(LogLevel::ERROR, "failed", ["exception" => "RuntimeException"]);

        $logger = new PaymentLogger($inner, $this->createConfig(false));
        $logger->error("failed", ["exception" => "RuntimeException"]);
    }

    public function testWarningIsAlwaysLogged(): void
    {
        $inner = $this->createMock(LoggerInterface::class);
        $inner
            ->expects(self::once())
            ->method("log")
            ->with(LogLevel::WARNING, "rejected", []);

        $logger = new PaymentLogger($inner, $this->createConfig(false));
        $logger->warning("rejected");
    }

    public function testDebugIsSuppressedWhileLoggingDisabled(): void
    {
        $inner = $this->createMock(LoggerInterface::class);
        $inner->expects(self::never())->method("log");

        $logger = new PaymentLogger($inner, $this->createConfig(false));
        $logger->debug("verbose");
        $logger->info("verbose");
    }

    public function testDebugIsLoggedWhileLoggingEnabled(): void
    {
        $inner = $this->createMock(LoggerInterface::class);
        $inner
            ->expects(self::exactly(2))
            ->method("log")
            ->willReturnCallback(static function (
                string $level,
                string $message,
            ): void {
                self::assertContains($level, [
                    LogLevel::DEBUG,
                    LogLevel::INFO,
                ]);
                self::assertSame("verbose", $message);
            });

        $logger = new PaymentLogger($inner, $this->createConfig(true));
        $logger->debug("verbose");
        $logger->info("verbose");
    }

    private function createConfig(bool $loggingEnabled): ConfigInterface
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method("isLoggingEnabled")->willReturn($loggingEnabled);

        return $config;
    }
}
