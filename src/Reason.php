<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog;

/**
 * The `error.kind` vocabulary for form-submission logs, shared across services so
 * OpenObserve can group on it. Extend it here, not per site.
 *
 * `rejected` reasons (WARN): {@see METHOD}, {@see MALFORMED}, {@see HONEYPOT},
 * {@see CSRF}, {@see VALIDATION}. `failed` reasons (ERROR): {@see MAIL},
 * {@see EXCEPTION}.
 */
final class Reason
{
    /** The request was not a POST. */
    public const METHOD = 'method';

    /** The request body was not the expected shape. */
    public const MALFORMED = 'malformed';

    /** A hidden honeypot field was filled (bot). */
    public const HONEYPOT = 'honeypot';

    /** A CSRF / nonce check failed. */
    public const CSRF = 'csrf';

    /** The form model failed validation (includes a blank or wrong captcha). */
    public const VALIDATION = 'validation';

    /** The mailer threw while sending. */
    public const MAIL = 'mail';

    /** Any other Throwable — hydration, an unexpected fault. */
    public const EXCEPTION = 'exception';

    private function __construct()
    {
    }
}
