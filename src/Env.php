<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Pure resolvers for the `service.*` schema fields and for timestamp / level
 * formatting. No shell commands: the running commit comes from the
 * `SERVICE_VERSION` env var or a deploy-written `VERSION` file, then a read of
 * `.git/HEAD`.
 */
final class Env
{
    /** ISO 8601 UTC with milliseconds, e.g. `2026-09-07T13:45:12.482Z`. */
    public static function isoTimestamp(DateTimeInterface|float $time): string
    {
        $utc = new DateTimeZone('UTC');

        if ($time instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromInterface($time);
        } else {
            $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $time), $utc)
                ?: new DateTimeImmutable('now', $utc);
        }

        return $dt->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z');
    }

    /** PSR-3 level name to upper-case OTel-style name. */
    public static function mapLevel(string $psrLevel): string
    {
        return match (strtolower($psrLevel)) {
            'emergency', 'alert', 'critical', 'error' => 'ERROR',
            'warning' => 'WARN',
            'notice', 'info' => 'INFO',
            default => 'DEBUG',
        };
    }

    /**
     * `SERVICE_VERSION` env, else the first line of `$rootPath/VERSION` (written by the
     * deploy as `git rev-parse HEAD > VERSION`), else a read of `$rootPath/.git/HEAD`
     * (following a `ref:` into its loose or packed ref), else `'unknown'`.
     */
    public static function serviceVersion(?string $rootPath): string
    {
        $env = getenv('SERVICE_VERSION');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        if ($rootPath === null) {
            return 'unknown';
        }

        $root = rtrim($rootPath, '/');

        $versionFile = @file_get_contents($root . '/VERSION');
        if (is_string($versionFile)) {
            $first = trim((string) strtok($versionFile, "\n"));
            if ($first !== '') {
                return $first;
            }
        }

        return self::gitHead($root . '/.git') ?? 'unknown';
    }

    /**
     * `SERVICE_ENVIRONMENT` env, else `$resolver()` when it returns a non-empty
     * string, else `'production'`.
     */
    public static function serviceEnvironment(?Closure $resolver): string
    {
        $env = getenv('SERVICE_ENVIRONMENT');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        if ($resolver !== null) {
            $value = $resolver();
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'production';
    }

    private static function gitHead(string $gitDir): ?string
    {
        $head = @file_get_contents($gitDir . '/HEAD');
        if (!is_string($head)) {
            return null;
        }
        $head = trim($head);

        if (!str_starts_with($head, 'ref:')) {
            return preg_match('/^[0-9a-f]{40}$/', $head) === 1 ? $head : null;
        }

        $ref = trim(substr($head, 4));

        $loose = @file_get_contents($gitDir . '/' . $ref);
        if (is_string($loose) && trim($loose) !== '') {
            return trim($loose);
        }

        $packed = @file_get_contents($gitDir . '/packed-refs');
        if (is_string($packed)
            && preg_match('/^([0-9a-f]{40})\s+' . preg_quote($ref, '/') . '$/m', $packed, $m) === 1
        ) {
            return $m[1];
        }

        return null;
    }
}
