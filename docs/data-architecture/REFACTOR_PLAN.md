# Database refactor plan

Date: 2026-05-15

This plan is intentionally incremental. It does not assume an empty production database.

## Decision required before implementation

Confirm which scenario is true:

1. No production data / no customer data exists yet.
2. Production or customer data exists, even if small.

The implementation strategy changes materially based on this answer.

## Strategy A: no production data

If there is no real production data, use a schema reset branch:

1. Rewrite the initial migrations into a clean canonical baseline.
2. Remove historical compatibility columns that were added only to bridge recent iterations.
3. Replace native database enums with string columns where cross-database evolution matters.
4. Add final FK, unique, and index definitions directly in create migrations.
5. Regenerate factories/tests against the clean schema.
6. Run full PHP and frontend gates.

Pros:

- cleanest long-term schema;
- fewer transitional aliases;
- simpler rollback before first production deploy.

Cons:

- unsafe if any real environment already ran current migrations;
- requires coordination with every developer database.

## Strategy B: production or real data exists

If any real data exists, do not rewrite old migrations. Use evolutive migrations:

1. Add new canonical columns nullable.
2. Backfill in batches.
3. Add model dual-read/write compatibility if needed.
4. Add constraints after data is clean.
5. Rename columns only when the application is ready.
6. Drop legacy columns in a later release.

Pros:

- safe for real data;
- deployable in smaller releases;
- allows verification between steps.

Cons:

- temporary duplicate columns;
- more code compatibility work;
- requires data preflight and rollback planning.

## Rename and change map

### Recommended now

These are high-value changes once implementation begins.

| Type | From | To | Reason | Migration | Code impact |
| --- | --- | --- | --- | --- | --- |
| Column | `agent_activations.requested_by` | `requested_by_user_id` | Explicit user FK | rename/add FK | Activation actions, factories, tests |
| Column | `agent_commands.created_by` | `created_by_user_id` | Explicit user FK | rename/add FK | Command creation actions, tests |
| Column | `recipient_manifestations.created_by` | `created_by_user_id` | Explicit user FK | rename/add FK | Manifestation actions/tests |
| Column | `xml_downloads.requested_by` | `requested_by_user_id` | Explicit user FK | rename/add FK | XML request flows/tests |
| Column | `sefaz_connectivity_tests.requested_by` | `requested_by_user_id` | Explicit user FK | rename/add FK | Certificate diagnostics tests |
| Constraint | child table `(tenant_id, company_id)` pairs | composite FK to `companies(id, tenant_id)` | DB-level tenant/company integrity | add unique + FKs | Factories/tests may reveal bad setup |
| Constraint | `system_settings` scope uniqueness | deterministic scoped uniqueness | Prevent duplicate global/tenant/company settings | add scope columns or partial/sentinel unique | Settings controller/tests |
| Column set | certificate duplicate identity columns | one canonical set | Remove integration drift | staged migration | Certificate backend/UI/tests |

### Recommended before production

| Type | From | To | Reason | Migration | Code impact |
| --- | --- | --- | --- | --- | --- |
| Column | `agent_certificates.subject_name` + `subject` | `subject` | One source of truth | backfill/drop old | Inventory/UI/tests |
| Column | `agent_certificates.issuer_name` + `issuer` | `issuer` | One source of truth | backfill/drop old | Inventory/UI/tests |
| Column | `agent_certificates.valid_from` + `not_before` | `valid_from_at` | Canonical datetime name | add/backfill/drop | Casts/UI/tests |
| Column | `agent_certificates.valid_until` + `not_after` | `valid_until_at` | Canonical datetime name | add/backfill/drop | Casts/UI/tests |
| Column | `agent_certificates.cnpj` + `document`/`document_type` | `document`, `document_type` | Support CPF/CNPJ without duplicate field | backfill/drop old | Certificate filters/tests |
| Column | `agent_certificates.store_scope` + `store_location` | `store_location` | Match Agent contract | backfill/drop old | Agent inventory/UI/tests |
| Column | `company_certificates.valid_from` | `valid_from_at` | Project timestamp convention | rename | Casts/UI/tests |
| Column | `company_certificates.valid_until` | `valid_until_at` | Project timestamp convention | rename | Casts/UI/tests |
| Type | native DB `enum()` columns | `string` + PHP enum casts | Cross-DB evolution | alter columns | Enum tests/migrations |

### Optional

| Type | From | To | Reason | Migration | Code impact |
| --- | --- | --- | --- | --- | --- |
| Table | `company_user` with `id` | pivot without `id` | Pure Laravel pivot style | reset only preferred | Minimal |
| Column | `users` without `uuid` | add `uuid` | Public/admin user references | additive | User factory/auth tests |
| Model | missing relations | explicit `tenant()`, `company()`, actor relations | Better query reuse/static analysis | no DB migration | Model tests only |
| Index names | abbreviated names | fuller names | Clarity | optional rename | Migrations only |

### Not worth the cost now

| Current name | Proposed alternative | Decision |
| --- | --- | --- |
| `recipient_manifestations` | `manifestations` | Keep. Current name distinguishes recipient-side manifestation from attempts and fiscal status. |
| `agent_commands` | `agent_jobs` | Keep. It matches the existing Agent/API vocabulary. |
| `tenants` | `accounts` | Keep. Multi-tenant architecture already uses tenant terminology. |
| `company_fiscal_states` | `company_distribution_states` | Defer. Current table may support more than distribution state. Rename only if scope narrows. |

## Incremental implementation blocks

### Block 1 - Safe constraints and actor FKs

Goal: improve integrity without changing domain names broadly.

Likely files:

- new migration for actor user FK columns;
- new migration for tenant/company composite integrity;
- affected models/actions/factories/tests.

Work:

1. Add nullable `*_user_id` columns beside legacy actor columns if production data exists.
2. Backfill from `requested_by`/`created_by`.
3. Add `foreignId(...)->nullable()->constrained('users')->nullOnDelete()`.
4. Add composite integrity for `(tenant_id, company_id)` where supported.
5. Add failing tests first for mismatched tenant/company writes where practical.

Risk: medium. It may reveal bad factories or tests that insert inconsistent tenant/company pairs.

Rollback: drop new FKs/columns. Do not drop old columns in this block.

Gates:

- `composer pint-test`
- `composer phpstan`
- `vendor/bin/phpunit --colors=always`
- `composer quality`

#### Implementation note - 2026-05-15

Implemented as commit target `database: add standard user actor foreign keys`.

New nullable columns:

- `agent_activations.requested_by_user_id`
- `agent_commands.created_by_user_id`
- `recipient_manifestations.created_by_user_id`
- `xml_downloads.requested_by_user_id`
- `sefaz_connectivity_tests.requested_by_user_id`

Legacy columns intentionally retained:

- `agent_activations.requested_by`
- `agent_commands.created_by`
- `recipient_manifestations.created_by`
- `xml_downloads.requested_by`
- `sefaz_connectivity_tests.requested_by`
- `audit_logs.actor_user_id`

Backfill strategy:

- new columns are backfilled from their matching legacy integer columns only when the legacy value exists in `users.id`;
- orphan legacy values are not copied to the new FK columns;
- no legacy data is deleted or overwritten.

Foreign keys:

- new standard actor columns receive nullable FKs to `users.id` with `nullOnDelete()`;
- `audit_logs.actor_user_id` receives the same FK only when existing data has no orphan values;
- if an environment contains orphan audit actor IDs, the migration skips that FK instead of forcing data loss.

Code compatibility:

- existing writes now dual-write legacy and standard actor columns where the application already knows the authenticated user ID;
- Eloquent models expose typed `belongsTo(User::class, '..._user_id')` relationships for the standard columns;
- no frontend contract was changed.

Remaining risk:

- legacy columns can still drift if a future write path updates only old columns or only new columns;
- a later cleanup block should remove legacy columns only after production data has been verified and all writes use standard columns;
- this block does not address tenant/company composite integrity, certificate naming, enum portability, or nullable-scope settings uniqueness.

### Block 2 - Settings scope uniqueness

Goal: make `system_settings` uniqueness deterministic.

Options:

- Add explicit `scope_type` and `scope_id` columns.
- Or add generated normalized columns for tenant/company null sentinels.
- Or split global, tenant, and company settings if semantics diverge.

Risk: medium. Settings access code must be updated carefully.

Rollback: keep old unique until new access path is proven; remove new columns only after no writes use them.

### Block 3 - Certificate canonicalization

Goal: remove duplicate agent certificate fields safely.

Likely files:

- migration adding canonical fields if needed;
- `AgentCertificate` casts;
- `RecordAgentCertificateInventoryAction`;
- `LinkA3CertificateAction`;
- `CertificateController`;
- TypeScript model types and certificate UI;
- agent API tests and operational isolation tests.

Suggested canonical columns:

- `subject`
- `issuer`
- `common_name`
- `document`
- `document_type`
- `valid_from_at`
- `valid_until_at`
- `store_location`
- `store_name`
- classification booleans and arrays already present.

Production-safe sequence:

1. Add canonical columns nullable.
2. Backfill from old columns.
3. Dual-write in inventory action.
4. Switch reads to canonical columns.
5. Assert parity in tests.
6. Drop legacy columns in a later release.

Risk: high because Agent/Web contract and UI depend on these fields.

### Block 4 - Enum portability

Goal: reduce MySQL/PostgreSQL divergence.

Work:

1. For future status/type changes, stop adding native enum values.
2. Convert volatile enum columns to strings in one controlled migration.
3. Keep PHP backed enum casts and validation rules.
4. Consider check constraints only after choosing database-specific support strategy.

Risk: medium. Must preserve existing values exactly.

### Block 5 - Fiscal document and storage consistency

Goal: standardize storage pointer fields and fiscal status naming.

Work:

- Add missing disk fields to `sefaz_connectivity_tests` if storage disk can vary.
- Confirm all XML storage references use disk/path/hash.
- Confirm no fiscal XML is stored in JSON payload columns.
- Add tests for storage references and sanitization.

Risk: low/medium.

### Block 6 - Model relationship cleanup

Goal: improve Eloquent expressiveness without schema changes.

Work:

- Add missing `tenant()`, `company()`, `agent()`, and actor user relationships.
- Add PHPDoc generics.
- Add focused model relation tests only where useful.

Risk: low.

### Block 7 - Final naming cleanup

Goal: optional cleanup after real invariants are in place.

Work:

- Rename indexes/constraints only if needed.
- Decide whether `company_user.id` should stay.
- Decide whether `users.uuid` is needed.
- Update documentation diagrams.

Risk: low for docs, medium for DB renames.

## Required preflight checks before destructive changes

Run SQL/data checks before any rename/drop migration in production:

```sql
-- tenant/company mismatch pattern, repeated per table
select child.id
from agent_commands child
join companies c on c.id = child.company_id
where child.tenant_id <> c.tenant_id;

-- duplicate system settings under nullable scope
select tenant_id, company_id, `key`, count(*)
from system_settings
group by tenant_id, company_id, `key`
having count(*) > 1;

-- duplicate agent certificates after normalized key
select tenant_id, company_id, agent_id, thumbprint, store_location, count(*)
from agent_certificates
group by tenant_id, company_id, agent_id, thumbprint, store_location
having count(*) > 1;
```

Adjust SQL quoting for PostgreSQL.

## Documentation and test expectations

Every implementation block must update:

- migration tests or feature tests where behavior changes;
- factories that set tenant/company/user IDs;
- Eloquent casts/relations;
- frontend TypeScript types when serialized props change;
- `docs/data-architecture/*` if the canonical standard changes.

Do not reduce PHPStan, Pint, PHPUnit, ESLint, Prettier, TypeScript, or Vite gates to make schema work pass.

## Recommended first implementation prompt

Start with Block 1:

```text
Create a small migration and test slice that adds explicit user actor foreign keys and preflight checks for tenant/company consistency, without dropping legacy columns yet.
```

Before implementation, answer:

```text
Is there any production/customer data that has already run the current migrations?
```
