<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<string, mixed>}> */
    public array $records = [];

    /**
     * @param mixed                $level
     * @param string|Stringable    $message
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
