<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog;

use Psr\Log\LogLevel;

/**
 * The `error.kind` vocabulary for form-submission logs, shared across services so
 * OpenObserve can group on it. Extend it here, not per site.
 */
enum Reason: string
{
    /** The request was not a POST. */
    case Method = 'method';

    /** The request body was not the expected shape. */
    case Malformed = 'malformed';

    /** A hidden honeypot field was filled (bot). */
    case Honeypot = 'honeypot';

    /** A CSRF / nonce check failed. */
    case Csrf = 'csrf';

    /** The form model failed validation (includes a blank or wrong captcha). */
    case Validation = 'validation';

    /** The mailer threw while sending. */
    case Mail = 'mail';

    /** Any other Throwable — hydration, an unexpected fault. */
    case Exception = 'exception';

    /**
     * The log level a submission ending for this reason is recorded at: `mail`
     * and `exception` are runtime faults (`ERROR`); the rest are the request
     * being turned away (`WARNING`).
     *
     * @return LogLevel::WARNING|LogLevel::ERROR
     */
    public function level(): string
    {
        return match ($this) {
            self::Mail, self::Exception => LogLevel::ERROR,
            default => LogLevel::WARNING,
        };
    }
}
