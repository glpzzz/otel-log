# glpzzz/otel-log

OpenTelemetry-aligned single-line JSON (NDJSON) logging for
[`yiisoft/log`](https://github.com/yiisoft/log), plus request-trace propagation,
for a log shipper (Vector / Fluent Bit) to tail into OpenObserve.

The `yiisoft/log` counterpart of
[`glpzzz/yii2-otel-log`](https://packagist.org/packages/glpzzz/yii2-otel-log):
a fleet of services can emit the same key set and forward the same `trace.id`,
so a call chain that spans several of them stays joinable.

## Install

```
composer require glpzzz/otel-log
```

## Wire it up

### 1. Attach the formatter to a log target

```php
use Glpzzz\OtelLog\OtelFormatter;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;

$logger = new Logger([
    (new FileTarget(logFile: __DIR__ . '/../requests.log', fileMode: 0600))
        ->setFormat(new OtelFormatter(
            serviceName: 'my-service',
            rootPath: dirname(__DIR__),           // for service.version (VERSION file / .git/HEAD)
        )),
]);
```

WordPress, where the environment follows `WP_DEBUG`:

```php
new OtelFormatter(
    serviceName: 'my-site',
    rootPath: ABSPATH,
    environmentResolver: static fn () => (defined('WP_DEBUG') && WP_DEBUG) ? 'develop' : 'production',
);
```

### 2. Boot the tracer at the entry point (optional but recommended)

Before the framework is built, so an inbound `X-Request-ID` is adopted for the
whole request:

```php
\Glpzzz\OtelLog\Tracker::boot();
```

Merge `Tracker::outgoingHeaders()` into any outbound HTTP call to continue the
chain. If you skip `boot()`, the formatter mints a trace id lazily.

### 3. Log submissions through `SubmissionLog`

```php
use Glpzzz\OtelLog\SubmissionLog;
use Glpzzz\OtelLog\Reason;

$log = new SubmissionLog($logger);

// wrong method
$log->rejected($formName, Reason::Method, []);

// honeypot tripped
$log->rejected($formName, Reason::Honeypot, $fields);

// hydration / validation threw
$log->failed($formName, Reason::Exception, $fields, $e);

// form invalid
$log->rejected($formName, Reason::Validation, $fields, $result->getErrorMessagesIndexedByProperty());

// mailer threw
$log->failed($formName, Reason::Mail, $fields, $e);

// email sent
$log->sent($formName, $fields);
```

`sent()` logs `INFO`, `rejected()` `WARN`, `failed()` `ERROR` — **the level is the
outcome**. `SubmissionLog` builds the `message` text and the `context` shape, and
flushes immediately, so those can't drift between services.

## Schema

See [`docs/schema.md`](docs/schema.md) for the full contract and a Vector config.

## Environment variables

| var | purpose |
|---|---|
| `SERVICE_NAME` | `service.name` (or pass `serviceName`) |
| `SERVICE_VERSION` | running commit; else a deploy step writes `git rev-parse HEAD` to `<root>/VERSION` |
| `SERVICE_ENVIRONMENT` | override the `environmentResolver` / `production` default |
| `X_REQUEST_ID` | inbound trace id for console / queue workers |

## Requirements

- PHP >= 8.2
- `yiisoft/log` ^2.2
- `yiisoft/log-target-file` ^3.1 (the target the formatter is attached to)
