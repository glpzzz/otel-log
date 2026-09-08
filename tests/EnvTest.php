<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Glpzzz\OtelLog\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SERVICE_VERSION');
        putenv('SERVICE_ENVIRONMENT');
    }

    public function testIsoTimestampFromDateTime(): void
    {
        $dt = new DateTimeImmutable('2026-09-07 13:45:12.482000', new DateTimeZone('America/Cancun'));

        self::assertSame('2026-09-07T18:45:12.482Z', Env::isoTimestamp($dt));
    }

    public function testIsoTimestampFromFloat(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            Env::isoTimestamp(1_767_326_645.678),
        );
    }

    public function testMapLevel(): void
    {
        self::assertSame('ERROR', Env::mapLevel('error'));
        self::assertSame('ERROR', Env::mapLevel('critical'));
        self::assertSame('WARN', Env::mapLevel('warning'));
        self::assertSame('INFO', Env::mapLevel('info'));
        self::assertSame('INFO', Env::mapLevel('notice'));
        self::assertSame('DEBUG', Env::mapLevel('debug'));
        self::assertSame('DEBUG', Env::mapLevel('anything-else'));
    }

    public function testServiceVersionPrefersEnv(): void
    {
        putenv('SERVICE_VERSION=deadbeef');

        self::assertSame('deadbeef', Env::serviceVersion(sys_get_temp_dir()));
    }

    public function testServiceVersionReadsVersionFile(): void
    {
        $root = $this->tempDir();
        file_put_contents($root . '/VERSION', "abc123\nsecond line\n");

        self::assertSame('abc123', Env::serviceVersion($root));
    }

    public function testServiceVersionReadsLooseGitRef(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/.git/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/.git/refs/heads/main', str_repeat('a', 40) . "\n");

        self::assertSame(str_repeat('a', 40), Env::serviceVersion($root));
    }

    public function testServiceVersionFallsBackToUnknown(): void
    {
        self::assertSame('unknown', Env::serviceVersion($this->tempDir()));
        self::assertSame('unknown', Env::serviceVersion(null));
    }

    public function testServiceEnvironment(): void
    {
        self::assertSame('production', Env::serviceEnvironment(null));
        self::assertSame('develop', Env::serviceEnvironment(static fn () => 'develop'));

        putenv('SERVICE_ENVIRONMENT=staging');
        self::assertSame('staging', Env::serviceEnvironment(static fn () => 'develop'));
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/otel-log-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
