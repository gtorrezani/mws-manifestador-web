# Quality

## Local gates

Run before every commit:

```bash
scripts/quality.sh
```

On Windows PowerShell:

```powershell
scripts/quality.ps1
```

The scripts run dependency installation only when `vendor` or `node_modules` is missing or stale, then execute:

```bash
composer quality
npm run quality
```

Equivalent manual commands:

```bash
composer install
npm ci
composer pint-test
composer phpstan
composer test
npm run lint
npm run format:check
npm run typecheck
npm run build
```

## PR criteria

- All local gates must pass before review, or the PR must document an environment-only blocker with the exact failing command.
- New behavior requires focused tests at the closest layer: unit tests for pure logic, feature tests for HTTP/API behavior, and browser-level validation only for UI flows that need it.
- Public payloads, route names, table names, migrations, and fiscal behavior must not change without explicit regression coverage and migration notes.
- Refactoring must be incremental: keep changes small, behavior-preserving, and tied to the tests that protect the touched surface.

## Lint and format

- PHP format is enforced by Laravel Pint through `composer pint-test`.
- PHP static analysis is enforced by PHPStan/Larastan at the configured level; do not lower the level or add broad ignores to make a build pass.
- Frontend lint uses ESLint with `--max-warnings=0`.
- Frontend format uses Prettier through `npm run format:check`.
- TypeScript/Vue correctness is enforced by `vue-tsc --noEmit` and the Vite production build.
- Zero warning policy: warnings are build failures and must be fixed or explicitly justified in review.

## Secrets and fiscal data

- Never commit `.env` files, real certificates, private keys, PEM files, PFX/P12 files, certificate PINs, A1 passwords, HMAC secrets, access keys, real CNPJs, or real fiscal XML.
- Never log PINs, A1 passwords, HMAC secrets, PFX/P12 contents, private keys, full fiscal XML, or other sensitive fiscal payloads.
- Fixtures must be sanitized and minimal. Use fake values that cannot be confused with production fiscal data.

## Agent API HMAC contract

Agent API routes under `/api/agent/v1` use HMAC authentication except activation. The shared canonical string is:

```text
METHOD
PATH
TIMESTAMP
NONCE
BODY_SHA256
```

The required headers are:

```text
X-MWS-Agent-Id
X-MWS-Timestamp
X-MWS-Nonce
X-MWS-Body-SHA256
X-MWS-Signature
```

`App\Services\Agent\AgentHmacAuthenticator::sign` is the server-side contract implementation. The fixture `tests/Fixtures/agent-hmac-contract.json` must stay compatible with the .NET agent test fixture, and `tests/Unit/Agent/AgentHmacContractTest.php` verifies the body hash and signature.

Do not change header names, the canonical string order, body hashing, or signature algorithm unless both repositories are updated in the same change with contract tests and documentation.

## Protected areas

Changes in these areas require regression tests:

- Agent authentication, command polling, command locking, heartbeat, diagnostics, and log ingestion.
- Certificate storage, A1 inspection, A3 inventory, and credential handling.
- Company scoping, authorization, and user/company relationships.
- Fiscal manifestation status transitions, SEFAZ command outcomes, XML handling, and any persistence schema that stores fiscal data.
