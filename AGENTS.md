# AGENTS.md

This repo is the SignatureAPI Laravel starter: a Laravel 12 app that sends one
agreement for e-signature and tracks it by webhook. It is a template. Keep it
small and idiomatic.

## Run and test

- `composer install`, `cp .env.example .env`, `php artisan key:generate`,
  `php artisan migrate`, then `php artisan serve --port=3000`.
- `npx --yes signatureapi init` writes `SIGNATUREAPI_KEY` to `.env`.
  `npx --yes signatureapi listen --forward-to http://127.0.0.1:3000/webhooks/signatureapi`
  writes `SIGNATUREAPI_WEBHOOK_SECRET`.
- `php artisan test` runs the PHPUnit tests (`Http::fake()`, no network, no key).
- `vendor/bin/pint` formats the code; CI runs `vendor/bin/pint --test`.
- `e2e/` is a real test-mode Playwright run. It needs a key and
  `npx --yes signatureapi listen`, so it is not part of CI. See `e2e/README.md`.

## Where things live

- `app/Services/SignatureApi.php`: the SignatureAPI client (`uploadDocument`,
  `createEnvelope`, `listDeliverables`), using Laravel's `Http` client. All API
  calls go here. Bound in `app/Providers/AppServiceProvider.php` from
  `config/services.php`.
- `app/Http/Controllers/SignatureApiWebhookController.php`: webhook
  verification and the event → status map.
- `app/Http/Controllers/AgreementController.php`: the page, the form and the
  signed PDF redirect. Routes are in `routes/web.php`.
- `app/Models/Agreement.php` and `database/migrations/`: the `Agreement` model
  (SQLite, `database/database.sqlite`).
- `resources/views/agreements/index.blade.php`: the page. Its DOM matches the
  e2e test; keep labels and `data-*` attributes.
- `tests/Feature/`: the tests.

## SignatureAPI rules

- The OpenAPI spec at https://spec.signatureapi.com/openapi.yaml is the
  source of truth for paths, fields, enums and event types. Check it before
  changing a request body or handler, not memory.
- Webhooks are verified with the `standard-webhooks/standard-webhooks` library
  against the **raw body bytes** (`$request->getContent()`). Never verify a
  re-encoded body, and never write the HMAC by hand. A failed check returns
  401 and does nothing else. The route is exempt from CSRF in `bootstrap/app.php`.
- Final statuses (`completed`, `rejected`, `failed`, `canceled`) never change.
- Use test keys only (`key_test_...`). Never put a key in code, logs or commits.
  Keys and the webhook secret live in `.env` (gitignored).

## Agent skills

The SignatureAPI skills are vendored in `.agents/skills/` (Codex, Cursor,
Copilot and others) and installed for Claude Code through the plugin pinned in
`.claude/settings.json`. `scripts/update-agent-skills.sh <sha>` bumps both.
