# Quality Gates

## Laravel Web/API

Run locally:

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

Fast fix pass:

```bash
composer fix
npm run lint:fix
npm run format
```

Rules:

- Build must not pass with PHP, TypeScript, ESLint, PHPUnit, or PHPStan warnings.
- Fiscal states must use `ManifestationStatus`, `ManifestationEventType`, and related enums.
- Manifestation transitions require unit tests and feature tests when commands or SEFAZ outcomes are affected.
- Agent API changes require tests for HMAC authentication, idempotency, and command locks.
- Test fixtures must be sanitized. Do not commit real NF-e XMLs, access keys, CNPJs, certificates, PFX/P12 files, private keys, PINs, or passwords.
- Controllers must stay thin. Move orchestration to actions and fiscal decisions to validators/guards/services.
