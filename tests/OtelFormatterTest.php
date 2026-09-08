<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog\Tests;

use Glpzzz\OtelLog\OtelFormatter;
use PHPUnit\Framework\TestCase;
use Yiisoft\Log\Message;

final class OtelFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_REQUEST_ID']);
        putenv('SERVICE_NAME');
        putenv('SERVICE_ENVIRONMENT');
        putenv('SERVICE_VERSION=testsha');
    }

    protected function tearDown(): void
    {
        putenv('SERVICE_VERSION');
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function line(array $context, string $level = 'warning', string $text = 'm'): array
    {
        $context['time'] ??= 1_767_326_645.5;
        $json = (new OtelFormatter('svc-a'))(new Message($level, $text, $context));

        self::assertJson($json);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    public function testValidationRejection(): void
    {
        $o = $this->line([
            'reason' => 'validation',
            'form' => 'ContactForm',
            'fields' => ['name' => '', 'captcha' => 'ZZZ'],
            'errors' => ['name' => ['Name cannot be blank.']],
        ], 'warning', 'ContactForm submission rejected');

        self::assertSame('WARN', $o['log.level']);
        self::assertSame('svc-a', $o['service.name']);
        self::assertSame('testsha', $o['service.version']);
        self::assertSame('production', $o['service.environment']);
        self::assertSame('validation', $o['error.kind']);
        self::assertSame('ContactForm submission rejected', $o['message']);
        self::assertSame('ContactForm', $o['context']['form']);
        self::assertArrayNotHasKey('reason', $o['context'], 'reason lives in error.kind, not context');
        self::assertSame('***', $o['context']['fields']['captcha']);
        self::assertSame(['name' => ['Name cannot be blank.']], $o['context']['errors']);
        self::assertArrayNotHasKey('error.message', $o);
        self::assertMatchesRegularExpression('/^[0-9A-Za-z._-]{8,128}$/', $o['trace.id']);
    }

    public function testFailureCarriesExceptionFields(): void
    {
        $o = $this->line([
            'reason' => 'mail',
            'form' => 'ContactForm',
            'fields' => ['name' => 'Jo'],
            'errorMessage' => 'SMTP refused',
            'errorStackTrace' => "Exception: SMTP refused\n#0 {main}",
        ], 'error');

        self::assertSame('ERROR', $o['log.level']);
        self::assertSame('mail', $o['error.kind']);
        self::assertSame('SMTP refused', $o['error.message']);
        self::assertStringContainsString('#0 {main}', $o['error.stack_trace']);
    }

    public function testSuccessHasNoReasonOrErrors(): void
    {
        $o = $this->line([
            'reason' => '',
            'form' => 'QuoteForm',
            'fields' => ['name' => 'Jo'],
        ], 'info', 'QuoteForm submission sent');

        self::assertSame('INFO', $o['log.level']);
        self::assertNull($o['error.kind']);
        self::assertArrayNotHasKey('reason', $o['context']);
        self::assertArrayNotHasKey('errors', $o['context']);
        self::assertArrayNotHasKey('error.message', $o);
    }

    public function testServiceNameFromEnv(): void
    {
        putenv('SERVICE_NAME=from-env');
        $json = (new OtelFormatter())(new Message('info', 'x', ['time' => 1.0]));

        self::assertSame('from-env', json_decode($json, true)['service.name']);
    }

    public function testPlainLogWithoutSubmissionContext(): void
    {
        $json = (new OtelFormatter('svc-a'))(new Message('info', 'just a message', ['time' => 1.0]));
        $o = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('just a message', $o['message']);
        self::assertNull($o['error.kind']);
        self::assertArrayNotHasKey('form', $o['context']);
        self::assertSame([], $o['context']['fields']);
    }
}
