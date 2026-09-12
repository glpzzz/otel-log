# NDJSON schema

One JSON object per line, in Elastic Common Schema (ECS) field names. Dot-notation
keys are **literal, not nested** — only `context` is a nested object, and it only
ever holds fixed, developer-controlled keys (`form`, `errors`). Anything whose
*keys* come from the request itself (the submitted form fields/body) is
`json_encode()`d into a single string field instead of spread out as an object,
so a submitted field named e.g. `service.name` can never inject or shadow a real
schema field once ingested by Elasticsearch/OpenObserve.

| key | value |
|---|---|
| `@timestamp` | ISO 8601 UTC, `Y-m-d\TH:i:s.v\Z` (millisecond precision) |
| `log.level` | `INFO` / `WARN` / `ERROR` / `DEBUG` — the **outcome** of the request |
| `service.name` | stable identifier for the emitting repo (`serviceName` arg, else `SERVICE_NAME` env, else `unknown-service`) |
| `service.version` | running commit — `SERVICE_VERSION` env, else the first line of `<root>/VERSION`, else `<root>/.git/HEAD`, else `unknown` |
| `service.environment` | `SERVICE_ENVIRONMENT` env, else the `environmentResolver` result, else `production` |
| `trace.id` | `TRACKING_REQUEST_UUID` — an adopted inbound `X-Request-ID` or a minted 32-char hex token |
| `message` | human summary, e.g. `ContactForm submission sent` |
| `error.kind` | the **reason** — why the request ended the way it did (`method` / `honeypot` / `validation` / `mail` / …). Absent on a plain success. |
| `error.message` | exception message; present only when the call carried a `Throwable` |
| `error.stack_trace` | `(string) $throwable` — full trace as one `\n`-escaped string; same condition |
| `client.ip` | `$_SERVER['REMOTE_ADDR']` |
| `user.id` | always `null` in this version |
| `http.request.method` | `$_SERVER['REQUEST_METHOD']`, when set |
| `url.full` / `url.path` / `url.query` | split from `$_SERVER['REQUEST_URI']` (and `HTTP_HOST`/`HTTPS` for `url.full`), when set |
| `user_agent.original` | `$_SERVER['HTTP_USER_AGENT']`, when set |
| `http.request.referrer` | `$_SERVER['HTTP_REFERER']`, when set |
| `http.request.body.content` | the submitted form fields, masked then `json_encode()`d to a **string** (never a nested object — field names are attacker-controlled). Present only when fields were submitted. |
| `code.stacktrace` | array of `"file:line"` frames, present only on `ERROR`/`WARN` when the PSR-3 context carried a `trace` (debug backtrace) |
| `context` | nested object — see below |

### `context`

- `form` — the form's short class name (`ContactForm`, `AutoInsuranceQuoteForm`, …). Absent for non-submission logs.
- `errors` — `{ property: [message, …] }` validation messages, masked then `json_encode()`d to a **string** (same reasoning as `http.request.body.content`: property names come from the submitted fields). Present only on a `validation` reject.

### The `error.kind` / reason vocabulary

Rejected (`WARN`): `method`, `malformed`, `honeypot`, `csrf`, `validation`.
Failed (`ERROR`): `mail`, `exception`.

See the `Glpzzz\OtelLog\Reason` enum — `Reason::from($kind)` and
`Reason::Validation->level()`.

### Masking and truncation

Keys in `DEFAULT_MASK_KEYS` (`captcha`, `password`, `password_repeat`, `pass`,
`token`, `authkey`, `auth_key`, `secret`, `api_key`, `access_token`, `csrf`,
`_csrf`, `credit_card`, `cvv`) have their value replaced with `***`,
case-insensitively and recursively, before `http.request.body.content` and
`context.errors` are encoded. Both are also depth/size-capped first
(`maxDepth` = 8, `maxItems` = 100 by default, both configurable via the
`OtelFormatter` constructor) — nesting beyond the cap collapses to
`{"_truncated":true}`, and arrays longer than the cap are cut with the same
marker.

## Sample lines

```json
{"@timestamp":"2026-01-02T15:46:41.511Z","log.level":"INFO","service.name":"my-service","service.version":"1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b","service.environment":"production","trace.id":"00109a982d1b3d7855e472e45caaa1db","message":"ContactForm submission sent","error.kind":null,"client.ip":"203.0.113.7","user.id":null,"http.request.method":"POST","url.full":"https://example.com/contact","url.path":"/contact","user_agent.original":"Mozilla/5.0","http.request.body.content":"{\"name\":\"Jo\",\"email\":\"jo@example.com\",\"captcha\":\"***\"}","context":{"form":"ContactForm"}}
{"@timestamp":"2026-01-02T15:47:02.114Z","log.level":"WARN","service.name":"my-service","service.version":"1a2b3c4…","service.environment":"production","trace.id":"a1b2c3…","message":"ContactForm submission rejected","error.kind":"validation","client.ip":"203.0.113.7","user.id":null,"http.request.method":"POST","http.request.body.content":"{\"name\":\"\",\"captcha\":\"***\"}","context":{"form":"ContactForm","errors":"{\"name\":[\"Name cannot be blank.\"]}"}}
{"@timestamp":"2026-01-02T15:48:20.902Z","log.level":"ERROR","service.name":"my-service","service.version":"1a2b3c4…","service.environment":"production","trace.id":"d4e5f6…","message":"ContactForm submission failed","error.kind":"mail","error.message":"Connection could not be established with host smtp:465","error.stack_trace":"Symfony\\Component\\Mailer\\Exception\\TransportException: …","client.ip":"203.0.113.7","user.id":null,"http.request.method":"POST","http.request.body.content":"{\"name\":\"Jo\",\"captcha\":\"***\"}","context":{"form":"ContactForm"}}
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
