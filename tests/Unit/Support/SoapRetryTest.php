<?php

namespace Tests\Unit\Support;

use Illuminate\Support\Sleep;
use Laragear\Dte\Support\SoapRetry;
use RuntimeException;
use SoapFault;
use Tests\TestCase;

class SoapRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    public function test_call_executes_callback_and_returns_result(): void
    {
        $result = SoapRetry::call(static fn(): string => 'expected');

        static::assertSame('expected', $result);
    }

    public function test_call_retries_on_soap_fault_then_succeeds(): void
    {
        $attempts = 0;

        $result = SoapRetry::call(function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new SoapFault('Server', 'Connection failed');
            }

            return 'recovered';
        });

        static::assertSame('recovered', $result);
        static::assertSame(2, $attempts);
    }

    public function test_call_does_not_retry_on_runtime_exception(): void
    {
        $attempts = 0;

        try {
            SoapRetry::call(function () use (&$attempts): void {
                $attempts++;

                throw new RuntimeException('Application error');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        static::assertSame(1, $attempts);
    }

    public function test_call_exhausts_retries_and_throws_soap_fault(): void
    {
        $attempts = 0;

        $this->expectException(SoapFault::class);

        SoapRetry::call(function () use (&$attempts): void {
            $attempts++;

            throw new SoapFault('Server', 'Persistent failure');
        });

        static::assertSame(6, $attempts);
    }

    public function test_call_sleeps_with_progressive_backoff(): void
    {
        $attempts = 0;

        try {
            SoapRetry::call(function () use (&$attempts): void {
                $attempts++;

                throw new SoapFault('Server', 'Failure');
            });
        } catch (SoapFault) {
            // Expected after exhausting retries.
        }

        // 6 attempts = 1 initial + 5 retries, each retry sleeps once = 5 sleeps.
        Sleep::assertSleptTimes(5);
    }
}
