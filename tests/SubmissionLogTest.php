<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog\Tests;

use Glpzzz\OtelLog\Reason;
use Glpzzz\OtelLog\SubmissionLog;
use Glpzzz\OtelLog\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubmissionLogTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testSent(): void
    {
        (new SubmissionLog($this->logger))->sent('ContactForm', ['name' => 'Jo']);

        [$level, $message, $context] = $this->logger->records[0];
        self::assertSame('info', $level);
        self::assertSame('ContactForm submission sent', $message);
        self::assertSame('', $context['reason']);
        self::assertSame(['name' => 'Jo'], $context['fields']);
        self::assertNull($context['errors']);
        self::assertNull($context['errorMessage']);
        self::assertNull($context['errorStackTrace']);
    }

    public function testRejectedWithErrors(): void
    {
        (new SubmissionLog($this->logger))->rejected(
            'ContactForm',
            Reason::VALIDATION,
            ['name' => ''],
            ['name' => ['Name cannot be blank.']],
        );

        [$level, $message, $context] = $this->logger->records[0];
        self::assertSame('warning', $level);
        self::assertSame('ContactForm submission rejected', $message);
        self::assertSame('validation', $context['reason']);
        self::assertSame(['name' => ['Name cannot be blank.']], $context['errors']);
        self::assertNull($context['errorMessage']);
    }

    public function testFailedCarriesThrowable(): void
    {
        $e = new RuntimeException('SMTP refused');
        (new SubmissionLog($this->logger))->failed('ContactForm', Reason::MAIL, ['name' => 'Jo'], $e);

        [$level, $message, $context] = $this->logger->records[0];
        self::assertSame('error', $level);
        self::assertSame('ContactForm submission failed', $message);
        self::assertSame('mail', $context['reason']);
        self::assertSame('SMTP refused', $context['errorMessage']);
        self::assertStringContainsString('RuntimeException', $context['errorStackTrace']);
        self::assertNull($context['errors']);
    }
}
