# NDJSON schema

One JSON object per line. Dot-notation keys are **literal, not nested** — only
`context` is a nested object.

| key | value |
|---|---|
| `timestamp` | ISO 8601 UTC, `Y-m-d\TH:i:s.v\Z` (millisecond precision) |
| `log.level` | `INFO` / `WARN` / `ERROR` / `DEBUG` — the **outcome** of the request |
| `service.name` | stable identifier for the emitting repo (`serviceName` arg, else `SERVICE_NAME` env, else `unknown-service`) |
| `service.version` | running commit — `SERVICE_VERSION` env, else the first line of `<root>/VERSION`, else `<root>/.git/HEAD`, else `unknown` |
| `service.environment` | `SERVICE_ENVIRONMENT` env, else the `environmentResolver` result, else `production` |
| `account.id` | tenant / client scope. Always `null` in this version. |
| `trace.id` | `TRACKING_REQUEST_UUID` — an adopted inbound `X-Request-ID` or a minted 32-char hex token |
| `message` | human summary, e.g. `ContactForm submission sent` |
| `error.kind` | the **reason** — why the request ended the way it did (`method` / `honeypot` / `validation` / `mail` / …). `null` on a plain success. |
| `error.message` | exception message; present only when the call carried a `Throwable` |
| `error.stack_trace` | `(string) $throwable` — full trace as one `\n`-escaped string; same condition |
| `http.request.ip` | `$_SERVER['REMOTE_ADDR']` |
| `user.id` | always `null` in this version |
| `context` | nested object — see below |

### `context`

- `form` — the form's short class name (`ContactForm`, `AutoInsuranceQuoteForm`, …). Absent for non-submission logs.
- `fields` — the submitted form fields, an object. Secret-like keys (`captcha`, `password`, `token`, `_csrf`, …) are masked to `***`, recursively.
- `errors` — `{ property: [message, …] }` validation messages. Present only on a `validation` reject.
- `http.request.method` / `http.request.url` / `http.user_agent` / `http.referer` — of the current web request, when set.

### The `error.kind` / reason vocabulary

Rejected (`WARN`): `method`, `malformed`, `honeypot`, `csrf`, `validation`.
Failed (`ERROR`): `mail`, `exception`.

See `Glpzzz\OtelLog\Reason`.

## Sample lines

```json
{"timestamp":"2026-01-02T15:46:41.511Z","log.level":"INFO","service.name":"my-service","service.version":"1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b","service.environment":"production","account.id":null,"trace.id":"00109a982d1b3d7855e472e45caaa1db","message":"ContactForm submission sent","error.kind":null,"http.request.ip":"203.0.113.7","user.id":null,"context":{"http.request.method":"POST","form":"ContactForm","fields":{"name":"Jo","email":"jo@example.com","captcha":"***"}}}
{"timestamp":"2026-01-02T15:47:02.114Z","log.level":"WARN","service.name":"my-service","service.version":"1a2b3c4…","service.environment":"production","account.id":null,"trace.id":"a1b2c3…","message":"ContactForm submission rejected","error.kind":"validation","http.request.ip":"203.0.113.7","user.id":null,"context":{"http.request.method":"POST","form":"ContactForm","fields":{"name":"","captcha":"***"},"errors":{"name":["Name cannot be blank."]}}}
{"timestamp":"2026-01-02T15:48:20.902Z","log.level":"ERROR","service.name":"my-service","service.version":"1a2b3c4…","service.environment":"production","account.id":null,"trace.id":"d4e5f6…","message":"ContactForm submission failed","error.kind":"mail","error.message":"Connection could not be established with host smtp:465","error.stack_trace":"Symfony\\Component\\Mailer\\Exception\\TransportException: …","http.request.ip":"203.0.113.7","user.id":null,"context":{"http.request.method":"POST","form":"ContactForm","fields":{"name":"Jo","captcha":"***"}}}
```

## Shipping into OpenObserve (Vector)

```toml
[sources.otel_app]
type = "file"
include = ["/var/www/*/requests.log"]
read_from = "end"

[transforms.otel_parse]
type = "remap"
inputs = ["otel_app"]
source = '. = parse_json!(.message)'

[sinks.openobserve]
type = "http"
inputs = ["otel_parse"]
uri = "https://openobserve.example.com/api/${OO_ORG}/${OO_STREAM}/_json"
encoding.codec = "json"
# route by service.name / service.environment as your OO org/stream layout needs
```
