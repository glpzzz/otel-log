<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog\Tests;

use Glpzzz\OtelLog\Tracker;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class TrackerTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMintsHexWhenNoInboundId(): void
    {
        unset($_SERVER['HTTP_X_REQUEST_ID']);
        putenv('X_REQUEST_ID');

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Tracker::boot());
        self::assertSame(Tracker::boot(), Tracker::id(), 'first call wins');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdoptsSaneInboundHeader(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-0f9a8b7c';

        self::assertSame('req-0f9a8b7c', Tracker::boot());
        self::assertSame(['X-Request-ID' => 'req-0f9a8b7c'], Tracker::outgoingHeaders());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRejectsGarbageInboundHeader(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'no spaces allowed';

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Tracker::boot());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdoptsEnvIdForConsole(): void
    {
        unset($_SERVER['HTTP_X_REQUEST_ID']);
        putenv('X_REQUEST_ID=queue-worker-1234');

        self::assertSame('queue-worker-1234', Tracker::boot());
    }
}
