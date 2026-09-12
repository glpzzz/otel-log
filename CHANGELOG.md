# Changelog

## 0.2.0 — schema alignment with `glpzzz/yii2-otel-log` (ECS)

Breaking change to the emitted NDJSON schema — no compatibility aliases for the
old field names. See `docs/schema.md` for the full current schema.

- `timestamp` → `@timestamp`
- `account.id` (always `null`) removed
- `http.request.ip` → `client.ip`
- `context.http.request.url` (nested, unsplit) → top-level `url.full` / `url.path` / `url.query`
- `context.http.user_agent` (nested) → top-level `user_agent.original`
- `context.http.referer` (nested, US spelling) → top-level `http.request.referrer` (ECS spelling)
- Added `code.stacktrace`: array of `"file:line"` frames, `ERROR`/`WARN` only, when the log context carries a debug `trace`
- Added `http.request.body.content`: the submitted form fields, masked
- Moved `context.fields` into `http.request.body.content`
- **Security/hygiene:** `http.request.body.content` and `context.errors` are now
  `json_encode()`d to a **string**, not emitted as a nested object — their keys
  (submitted field names) are attacker-controlled, and a submitted field named
  after a real schema key (e.g. `service.name`) could otherwise pollute or
  shadow it once ingested by Elasticsearch/OpenObserve
- Added `maxDepth` / `maxItems` constructor options, truncating `http.request.body.content` and `context.errors` before they're encoded (defaults: 8 / 100, matching `glpzzz/yii2-otel-log`)
- `error.kind` and `user.id` (still always `null`) are unchanged — kept as `otel-log`-specific fields with no ECS equivalent

## 0.1.0

Initial release.
