<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog;

use Closure;
use stdClass;
use Yiisoft\Log\Message;

/**
 * Formats each {@see Message} as one line of NDJSON in an OpenTelemetry-style
 * dot-notation schema, ready for a log shipper (Vector / Fluent Bit) to tail
 * into OpenObserve — joinable on `trace.id` with the sibling services that emit
 * the same schema (including
 * {@link https://packagist.org/packages/glpzzz/yii2-otel-log glpzzz/yii2-otel-log}).
 *
 * Attach it to any target:
 * `(new FileTarget(...))->setFormat(new OtelFormatter('my-service'))`.
 *
 * Top-level keys are literal (not nested); `context` is the only nested object.
 * Log form submissions through {@see SubmissionLog}, which fills the PSR context
 * this formatter reads: `form`, `reason`, `fields`, `errors`, `errorMessage`,
 * `errorStackTrace`. The outcome is the log level; the reason is `error.kind`.
 *
 * @see docs/schema.md
 */
final class OtelFormatter
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /** @var list<string> */
    public const DEFAULT_MASK_KEYS = [
        'captcha', 'password', 'password_repeat', 'pass', 'token', 'authkey',
        'auth_key', 'secret', 'api_key', 'access_token', 'csrf', '_csrf',
        'credit_card', 'cvv',
    ];

    private ?string $version = null;
    private ?string $environment = null;

    /** @var list<string> */
    private array $maskKeys;

    /**
     * @param string        $serviceName         `service.name`. Falls back to the `SERVICE_NAME`
     *                                            env var, then `'unknown-service'`.
     * @param string|null   $rootPath            project root for `service.version` resolution
     *                                            (a deploy-written `VERSION` file, then `.git/HEAD`).
     *                                            Defaults to the current working directory.
     * @param Closure|null  $environmentResolver `fn(): string` for `service.environment`, tried after
     *                                            the `SERVICE_ENVIRONMENT` env var and before the
     *                                            `'production'` default — e.g. for WordPress
     *                                            `static fn () => (defined('WP_DEBUG') && WP_DEBUG) ? 'develop' : 'production'`.
     * @param list<string>|null $maskKeys        context keys whose values become `***` (case-insensitive,
     *                                            recursive). Defaults to {@see self::DEFAULT_MASK_KEYS}.
     */
    public function __construct(
        private readonly string $serviceName = '',
        private readonly ?string $rootPath = null,
        private readonly ?Closure $environmentResolver = null,
        ?array $maskKeys = null,
    ) {
        $this->maskKeys = $maskKeys ?? self::DEFAULT_MASK_KEYS;
    }

    public function __invoke(Message $message): string
    {
        $errorMessage = $this->nonEmptyString($message->context('errorMessage'));
        $errorStack = $this->nonEmptyString($message->context('errorStackTrace'));

        $entry = [
            'timestamp' => Env::isoTimestamp($message->time()),
            'log.level' => Env::mapLevel($message->level()),
            'service.name' => $this->serviceName(),
            'service.version' => $this->serviceVersion(),
            'service.environment' => $this->serviceEnvironment(),
            'account.id' => null,
            'trace.id' => Tracker::id(),
            'message' => $message->message(),
            'error.kind' => $this->nonEmptyString($message->context('reason')),
            'error.message' => $errorMessage,
            'error.stack_trace' => $errorStack,
            'http.request.ip' => $this->nonEmptyString($_SERVER['REMOTE_ADDR'] ?? null),
            'user.id' => null,
            'context' => new stdClass(),
        ];

        if ($errorMessage === null) {
            unset($entry['error.message']);
        }
        if ($errorStack === null) {
            unset($entry['error.stack_trace']);
        }

        $fields = $message->context('fields');
        $errors = $message->context('errors');
        $form = (string) $message->context('form', '');

        $context = $this->requestContext();
        if ($form !== '') {
            $context['form'] = $form;
        }
        $context['fields'] = is_array($fields) ? $fields : [];
        if (is_array($errors) && $errors !== []) {
            $context['errors'] = $errors;
        }
        $context = $this->mask($context);
        $entry['context'] = $context === [] ? new stdClass() : $context;

        $json = json_encode($entry, self::JSON_FLAGS);
        if (!is_string($json)) {
            $json = json_encode([
                'timestamp' => $entry['timestamp'],
                'log.level' => 'ERROR',
                'service.name' => $entry['service.name'],
                'trace.id' => $entry['trace.id'],
                'message' => 'otel-log: entry encode failed (' . json_last_error_msg() . ')',
            ], self::JSON_FLAGS);
        }

        return is_string($json) ? $json : '{"log.level":"ERROR","message":"otel-log encode failure"}';
    }

    private function serviceName(): string
    {
        if ($this->serviceName !== '') {
            return $this->serviceName;
        }

        $env = getenv('SERVICE_NAME');

        return is_string($env) && $env !== '' ? $env : 'unknown-service';
    }

    private function serviceVersion(): string
    {
        return $this->version ??= Env::serviceVersion($this->rootPath ?? (getcwd() ?: null));
    }

    private function serviceEnvironment(): string
    {
        return $this->environment ??= Env::serviceEnvironment($this->environmentResolver);
    }

    /**
     * Method / url / user-agent / referer of the current web request.
     *
     * @return array<string, string>
     */
    private function requestContext(): array
    {
        $context = [];
        $put = static function (string $key, mixed $value) use (&$context): void {
            if (is_string($value) && $value !== '') {
                $context[$key] = $value;
            }
        };

        $put('http.request.method', $_SERVER['REQUEST_METHOD'] ?? null);
        $put('http.request.url', $_SERVER['REQUEST_URI'] ?? null);
        $put('http.user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        $put('http.referer', $_SERVER['HTTP_REFERER'] ?? null);

        return $context;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function mask(array $data): array
    {
        $lowered = array_map('strtolower', $this->maskKeys);

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $lowered, true)) {
                $data[$key] = '***';
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }
}
