# Database schema review

Date: 2026-05-15

Branch: `codex/data-architecture-review`

Scope: analysis only. No schema, migration, model, controller, test, or frontend behavior was changed in this round.

## Sources reviewed

- `database/migrations`
- `app/Models`
- `app/Enums`
- `database/factories`
- feature tests around company isolation, agent API, certificates, fiscal documents, and auth

## Executive summary

The schema is not beyond repair. It already follows several Laravel conventions: plural snake_case table names, `id` primary keys, `uuid` public identifiers on most domain tables, `*_id` foreign keys, timestamp columns, and Eloquent enum casts.

The main quality problems are not cosmetic. They are:

1. Tenant/company isolation is mostly a code convention, not a database invariant. Many tables store both `tenant_id` and `company_id`, but the database does not prove that the company belongs to the same tenant.
2. User/audit ownership fields are unsigned integers without foreign keys and inconsistent names: `requested_by`, `created_by`, `actor_user_id`.
3. Certificate tables carry duplicated concepts from multiple integration iterations: `subject_name`/`subject`, `issuer_name`/`issuer`, `valid_from`/`not_before`, `valid_until`/`not_after`, `cnpj`/`document`, `store_scope`/`store_location`.
4. Native `enum()` columns plus a MySQL-only enum alteration migration create cross-database maintenance risk.
5. Nullable columns inside unique constraints can weaken intended uniqueness in MySQL/PostgreSQL, especially for `system_settings` and `agent_certificates`.
6. Models are mostly consistent with the schema, but relationships for `tenant`, `company`, and user actor fields are incomplete across several tables.

## Migration inventory

| Migration | Operation | Tables |
| --- | --- | --- |
| `2026_05_14_000001_create_mws_manifestador_core_tables.php` | Creates the first domain schema | `tenants`, `companies`, `company_certificates`, `agents`, `agent_activations`, `agent_credentials`, `agent_heartbeats`, `agent_commands`, `agent_command_attempts`, `fiscal_documents`, `fiscal_document_summaries`, `fiscal_document_xmls`, `recipient_manifestations`, `manifestation_attempts`, `xml_downloads`, `sefaz_requests`, `sefaz_responses`, `audit_logs`, `system_settings` |
| `2026_05_14_010000_add_certificate_operations_tables.php` | Creates agent certificate inventory and links certificates to agents | `agent_certificates`, alters `company_certificates` |
| `2026_05_14_020000_align_agent_certificates_contract.php` | Adds agent inventory contract fields and backfills from older names | alters `agent_certificates` |
| `2026_05_14_030000_add_certificate_test_result_fields.php` | Adds certificate test payload/error fields | alters `agent_certificates`, `company_certificates` |
| `2026_05_14_040000_create_sefaz_connectivity_tests_table.php` | Creates SEFAZ connectivity test records | `sefaz_connectivity_tests` |
| `2026_05_14_050000_add_agent_operations_enum_values.php` | Alters MySQL enum values for agent operations | `agents`, `agent_heartbeats`, `agent_commands` |
| `2026_05_14_060000_add_agent_certificate_classification_fields.php` | Adds fiscal certificate classification fields | alters `agent_certificates` |
| `2026_05_14_060000_create_company_fiscal_states_table.php` | Creates per-company fiscal cursor/rate state | `company_fiscal_states` |
| `2026_05_15_000001_create_users_table.php` | Creates auth users by CPF | `users` |
| `2026_05_15_010000_add_distribution_rate_limit_fields_to_company_fiscal_states.php` | Adds distribution rate-limit state | alters `company_fiscal_states` |
| `2026_05_15_020000_create_company_user_table.php` | Creates user/company pivot | `company_user` |

## Consolidated table inventory

### Identity and tenancy

| Table | Columns and types | FKs and delete behavior | Indexes/uniques | Notes |
| --- | --- | --- | --- | --- |
| `tenants` | `id`, `uuid`, `name`, `slug`, `is_active`, timestamps, soft deletes | none | unique `uuid`, unique `slug` | Good Laravel naming. `uuid` is public candidate key. |
| `users` | `id`, `name`, `cpf char(11)`, `password`, `is_active`, `blocked_at`, `last_login_at`, `remember_token`, timestamps | none | unique `cpf` | No `uuid`, no soft deletes. Acceptable if users are not public API resources; otherwise add `uuid`. |
| `companies` | `id`, `uuid`, `tenant_id`, `legal_name`, `trade_name`, `cnpj char(14)`, `state_registration`, `uf char(2)`, `fiscal_environment enum`, `is_active`, timestamps, soft deletes | `tenant_id -> tenants`, cascade | unique `(tenant_id, cnpj)`, indexes `(tenant_id, is_active)`, `(tenant_id, uf)` | Good base table. Missing database-level proof for child rows that duplicate `tenant_id` and `company_id`. |
| `company_user` | `id`, `company_id`, `user_id`, timestamps | both cascade | unique `(company_id, user_id)`, index `(user_id, company_id)` | Laravel pivot name is correct. Because it has no extra attributes, `id` is optional and not required by Laravel. |

### Agent operations

| Table | Columns and types | FKs and delete behavior | Indexes/uniques | Notes |
| --- | --- | --- | --- | --- |
| `agents` | `id`, `uuid`, `tenant_id`, nullable `company_id`, `name`, `machine_name`, `installation_id`, `version`, `status enum`, `last_seen_at`, `activated_at`, `revoked_at`, timestamps, soft deletes | `tenant_id` cascade, `company_id` null on delete | unique `(tenant_id, installation_id)`, indexes `(tenant_id, company_id)`, `(tenant_id, status)`, `last_seen_at` | Nullable `company_id` is coherent for pending activation, but downstream commands require company scope. |
| `agent_activations` | `id`, `uuid`, `tenant_id`, nullable `company_id`, nullable `used_by_agent_id`, nullable `requested_by`, `code_hash`, `status enum`, `expires_at`, `used_at`, `metadata json`, timestamps | tenant cascade, company null, used agent null | unique `code_hash`, indexes `(tenant_id, company_id, status)`, `expires_at`, `requested_by` | `requested_by` should be `requested_by_user_id` with FK to `users`. |
| `agent_credentials` | `id`, `uuid`, `tenant_id`, `agent_id`, `credential_id`, secret hashes/encrypted payloads, rotation/expires/revoked timestamps, timestamps | tenant cascade, agent cascade | unique `agent_id`, unique `credential_id`, index `(tenant_id, revoked_at)` | Sensitive design is sound: plaintext secrets are not stored. |
| `agent_heartbeats` | `id`, `uuid`, `tenant_id`, `agent_id`, `status enum`, `version`, `machine_name`, `ip_address`, `payload json`, `received_at`, timestamps | tenant cascade, agent cascade | indexes `(tenant_id, agent_id, received_at)`, `(tenant_id, status)` | `payload` should stay sanitized. Consider retention/partitioning later. |
| `agent_commands` | `id`, `uuid`, `tenant_id`, `company_id`, nullable `agent_id`, `type enum`, `status enum`, `priority`, `payload json`, lock fields, counts, nullable `idempotency_key`, nullable `created_by`, completed/failed timestamps, timestamps | tenant/company cascade, agent null, locked agent null | unique `(tenant_id, idempotency_key)`, indexes scope/status, agent/status, polling, type | `created_by` should be `created_by_user_id` with FK. Native enum increases deployment friction. |
| `agent_command_attempts` | `id`, `uuid`, `tenant_id`, `agent_command_id`, nullable `agent_id`, `attempt_number`, `status enum`, started/finished timestamps, duration, errors, request/result JSON, timestamps | tenant cascade, command cascade, agent null | unique `(agent_command_id, attempt_number)`, index `(tenant_id, status)` | Good attempt table. If `tenant_id` is duplicated, enforce consistency with command tenant. |
| `agent_certificates` | `id`, `uuid`, `tenant_id`, `agent_id`, nullable `company_id`, certificate identity, duplicated subject/issuer/date/document/store fields, status/classification flags, metadata/raw/test JSON, timestamps, soft deletes | tenant/agent cascade, company null | multiple unique/indexes on thumbprint/store/company/status/classification | Highest naming debt. Duplicate columns came from incremental contract evolution and should be normalized. |

### Certificates

| Table | Columns and types | FKs and delete behavior | Indexes/uniques | Notes |
| --- | --- | --- | --- | --- |
| `company_certificates` | `id`, `uuid`, `tenant_id`, `company_id`, `type enum`, `status enum`, certificate identity, validity, encrypted A1 storage/password payload, metadata, test fields, optional `agent_id`, optional `agent_certificate_id`, timestamps, soft deletes | tenant/company cascade, agent null, agent certificate null | indexes `(tenant_id, company_id, type)`, `(tenant_id, status)`, `thumbprint`, `(tenant_id, agent_id)`, `(tenant_id, agent_certificate_id)` | Naming is mostly coherent but should align certificate validity/date fields with `agent_certificates`. |
| `sefaz_connectivity_tests` | `id`, `uuid`, tenant/company/agent/certificate/command IDs, `mode`, `environment`, `uf`, endpoint/status/errors/duration, XML storage paths, `sanitized_payload json`, `requested_by`, requested/completed timestamps, timestamps | tenant/company cascade, optional refs null | indexes scope/status, certificate, command, requested_at, requested_by | `requested_by` should be `requested_by_user_id`; request/response storage disk is missing while paths exist. |

### Fiscal documents and manifestations

| Table | Columns and types | FKs and delete behavior | Indexes/uniques | Notes |
| --- | --- | --- | --- | --- |
| `fiscal_documents` | `id`, `uuid`, tenant/company, `access_key char(44)`, nullable `nsu`, schema/version, CNPJ/name fields, number/series, `issued_at`, `total_amount decimal(15,2)`, manifestation/xml statuses, last SEFAZ status/message, timestamps | tenant/company cascade | unique `(tenant_id, company_id, access_key)`, unique `(tenant_id, company_id, nsu)`, indexes scope, access_key, nsu, status columns | `nsu` nullable in unique allows multiple nulls, which is probably intended. Good fiscal identity base. |
| `fiscal_document_summaries` | `id`, `uuid`, tenant/company/document, storage disk/path/hash, `summary_payload json`, `received_at`, timestamps | tenant/company/document cascade | unique `fiscal_document_id`, index `(tenant_id, company_id)` | Duplicates tenant/company from document; needs consistency invariant. |
| `fiscal_document_xmls` | `id`, `uuid`, tenant/company/document, storage disk/path/hash, size, schema_version, source, downloaded_at, timestamps | tenant/company/document cascade | unique `(tenant_id, company_id, fiscal_document_id, content_hash)`, indexes scope/hash | Good storage pointer model. Add policy that full fiscal XML is never logged or placed in JSON payload columns. |
| `recipient_manifestations` | `id`, `uuid`, tenant/company/document, event/status enums, justification, protocol, SEFAZ status/message, occurred_at, `created_by`, timestamps | tenant/company/document cascade | indexes scope/status, document/event, protocol, created_by | `created_by` should be `created_by_user_id` with FK. |
| `manifestation_attempts` | `id`, `uuid`, tenant, recipient manifestation, optional command/agent, attempt/status enums, previous/new manifestation status, protocol/SEFAZ message, started/finished, timestamps | tenant/manifestation cascade, command/agent null | unique `(recipient_manifestation_id, attempt_number)`, index `(tenant_id, status)` | Lacks `company_id` but can derive through manifestation. If keeping tenant_id, enforce consistency. |
| `xml_downloads` | `id`, `uuid`, tenant/company/document, optional command/agent, status enum, nsu/protocol/SEFAZ status, `requested_by`, requested/completed timestamps, timestamps | tenant/company/document cascade, command/agent null | indexes scope/status and document/status, requested_by | `requested_by` should be `requested_by_user_id` with FK. |

### SEFAZ, audit, settings

| Table | Columns and types | FKs and delete behavior | Indexes/uniques | Notes |
| --- | --- | --- | --- | --- |
| `sefaz_requests` | `id`, `uuid`, tenant, nullable company, nullable command, service, environment enum, endpoint, soap_action, request XML storage disk/path/hash, correlation_id, sent_at, timestamps | tenant cascade, company/command null | indexes `(tenant_id, company_id, service)`, `correlation_id`, `sent_at` | Missing explicit `company()`/`tenant()` relations in model. |
| `sefaz_responses` | `id`, `uuid`, tenant, nullable company, `sefaz_request_id`, status enum, http/sefaz statuses, response XML storage disk/path/hash, received_at, duration, timestamps | tenant cascade, company null, request cascade | indexes scope/status and SEFAZ status code | If request is deleted, responses are deleted. That is coherent for operational logs but check retention requirements. |
| `audit_logs` | `id`, `uuid`, nullable tenant/company/agent, `actor_user_id`, event, morph target, IP, user agent, metadata JSON, occurred_at, timestamps | tenant/company/agent null | indexes scope/event, morph target, occurred_at, actor_user_id | `actor_user_id` is named well but has no FK. |
| `system_settings` | `id`, `uuid`, nullable tenant/company, `key`, `value json`, `is_encrypted`, description, timestamps, soft deletes | tenant/company cascade | unique `(tenant_id, company_id, key)`, index `(tenant_id, company_id)` | Nullable columns inside unique allow duplicate global keys in MySQL/PostgreSQL. This is a real integrity risk. |
| `company_fiscal_states` | `id`, `uuid`, tenant/company, `environment`, `uf`, `service`, NSU fields, status/message, success/error timestamps, distribution rate-limit fields, failure count, metadata JSON, timestamps | tenant/company cascade | unique `(tenant_id, company_id, environment, uf, service)`, indexes scope/service, next distribution, block | Good operational cursor table. `environment` should align to `fiscal_environment` enum/string convention. |

## Eloquent model inventory

All domain models rely on Laravel's implicit table names; no model reviewed declares `$table`. This is good if table names remain plural snake_case.

Most models use `protected $guarded = ['id']`. `User` is the exception and uses `$fillable`. This is acceptable but should become a conscious project standard: either keep guarded for internal command-style aggregate writes, or move sensitive models to explicit fillable. Do not mix casually.

### Relationships present

- `Tenant -> companies`
- `Company -> tenant`, `users`, `agents`, `fiscalDocuments`, `certificates`
- `User -> companies`
- `Agent -> company`, `credential`, `heartbeats`, `certificates`
- `AgentActivation -> usedByAgent`, `company`
- `AgentCredential -> agent`
- `AgentHeartbeat -> agent`
- `AgentCommand -> company`, `tenant`, `agent`, `attempts`
- `AgentCommandAttempt -> command`
- `AgentCertificate -> tenant`, `agent`, `company`, `companyCertificates`
- `CompanyCertificate -> company`, `agent`, `agentCertificate`
- `FiscalDocument -> company`, `tenant`, `summary`, `xmls`, `manifestations`
- `FiscalDocumentSummary -> fiscalDocument`
- `FiscalDocumentXml -> fiscalDocument`
- `RecipientManifestation -> fiscalDocument`, `attempts`
- `ManifestationAttempt -> manifestation`
- `SefazRequest -> responses`
- `SefazResponse -> request`
- `SefazConnectivityTest -> company`, `agent`, `companyCertificate`, `agentCommand`
- `XmlDownload -> fiscalDocument`
- `AuditLog -> auditable`

### Relationship gaps

- Many tables with `tenant_id` do not expose a `tenant()` relation.
- `SefazRequest`, `SefazResponse`, `SystemSetting`, `AuditLog`, `XmlDownload`, `FiscalDocumentSummary`, `FiscalDocumentXml`, and `ManifestationAttempt` have useful FK columns that are not represented as explicit relations.
- User actor columns are not modeled consistently because the columns themselves are inconsistent: `requested_by`, `created_by`, `actor_user_id`.
- `BelongsToCompany::scopeForCompany()` scopes by `company_id` only. Because `company_id` is globally unique this works in practice, but it does not protect against rows with mismatched `tenant_id`.

### Cast alignment

Good alignment:

- Enums are cast on `Agent`, `AgentActivation`, `AgentCommand`, `AgentCommandAttempt`, `Company`, `CompanyCertificate`, `FiscalDocument`, `ManifestationAttempt`, `RecipientManifestation`, `SefazRequest`, `SefazResponse`, `XmlDownload`.
- JSON fields are cast to arrays on models that use them.
- Monetary `total_amount` is cast as `decimal:2`.
- Most timestamp columns used by application code are cast to `immutable_datetime`.

Gaps:

- Several timestamp columns do not have model casts because their models do not expose behavior yet or were added incrementally.
- `SefazConnectivityTest.status`, `mode`, and `environment` are strings in DB and model. They may deserve enums if the domain is stable.
- `AgentCertificate.classification` and `document_type` are strings. They should become application enums before enforcing database checks.

## Naming diagnosis

### Tables

Strong:

- Most tables use plural snake_case and match Eloquent.
- `company_user` follows Laravel pivot naming for a pure many-to-many relation.
- Technical tables such as `agent_commands`, `agent_command_attempts`, `sefaz_requests`, and `sefaz_responses` are descriptive.

Weak:

- `recipient_manifestations` is precise but slightly domain-heavy. It is acceptable and should not be renamed unless the ubiquitous language changes.
- `company_fiscal_states` is broad; it stores distribution cursor, status, and rate-limit state. The name is acceptable but the table may become overloaded.
- `system_settings` is broad and nullable-scoped. Its uniqueness model needs tightening before production.

### Columns

Strong:

- `id`, `uuid`, `tenant_id`, `company_id`, `*_id`, `created_at`, `updated_at`, `deleted_at` are mostly consistent.
- Most event dates use `_at`.
- Boolean names mostly use `is_`/`has_`.
- Fiscal documents use `access_key`, `nsu`, CNPJ as normalized fixed-length strings.

Weak:

- Certificate date columns use both `valid_from`/`valid_until` and `not_before`/`not_after`, without `_at`.
- Certificate identity columns use both `subject_name`/`issuer_name` and `subject`/`issuer`.
- Certificate document columns use both `cnpj` and generic `document`/`document_type`.
- Store columns use both `store_scope`, `store_location`, and `store_name`.
- Actor columns use `requested_by`, `created_by`, and `actor_user_id`. The first two do not say that they reference users.
- `last_message`, `last_distribution_message`, `sefaz_message`, `error_message`, and `validation_message` are all plausible, but should have clearer domain prefixes when multiple message sources exist in one table.
- `payload`, `metadata`, `raw_payload`, `sanitized_payload`, `last_test_payload`, `request_payload`, `result_payload`, `summary_payload` need a project-level JSON policy.

## Problems by severity

| Severity | File/table/column | Problem | Impact | Proposal | Risk | Needs migration? | Code/test impact |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Critical | Most company-scoped tables with `tenant_id` + `company_id` | No DB invariant proves `company_id` belongs to `tenant_id` | Cross-tenant data can exist if code/factory/import path writes inconsistent IDs | Add composite constraints or remove duplicated tenant_id where derivable | Medium/high on existing data | Yes | Models, factories, tests, queries |
| Critical | `system_settings (tenant_id, company_id, key)` | Unique over nullable columns permits duplicate global or tenant-wide keys | Settings can silently fork | Add normalized `scope_type/scope_id`, sentinel columns, or database-specific partial uniques | Medium | Yes | Settings controller/tests |
| High | `agent_certificates` | Duplicated certificate identity/date/document/store columns | Confusing source of truth, bugs in UI/API mapping | Choose canonical columns and migrate/backfill/drop aliases later | High | Yes | Agent inventory action, UI types, tests |
| High | `requested_by`, `created_by` columns | Unsuffixed user references and no FK | Broken auditability and orphan references | Rename to `*_user_id` and add null-on-delete FK to `users` | Medium | Yes | Actions/controllers/factories/tests |
| High | Native `enum()` migrations | MySQL enum alteration is raw SQL; PostgreSQL path is not equivalent | Cross-database behavior diverges during enum evolution | Prefer string columns plus PHP enums and optional check constraints | Medium | Yes for future changes | Enums, migrations, tests |
| High | `agent_certificates` unique keys with nullable `company_id`/`store_location` | Nullable unique components may allow duplicate inventory rows | Inventory idempotency can fail under null values | Make canonical key non-null where possible or use generated normalized key | Medium | Yes | Inventory action/tests |
| Medium | `company_user` | Pure pivot has surrogate `id` | Not harmful, but inconsistent with pure Laravel pivot convention | Keep if production exists; omit only in schema-reset strategy | Low | Optional | Minimal |
| Medium | `users` | No `uuid` | Future public user references would expose integer IDs | Add only if users become public/admin resource | Low | Optional | Models/API/tests |
| Medium | `sefaz_connectivity_tests` XML storage fields | path columns without disk columns while other tables use disk/path | Storage abstraction inconsistency | Add `request_xml_storage_disk`/`response_xml_storage_disk` if storage can vary | Low/medium | Yes | Certificate diagnostics tests |
| Medium | Models with tenant/company FKs | Missing explicit relations | Harder query reuse and Larastan insight | Add relations gradually | Low | No | Model tests/static analysis |
| Medium | `company_fiscal_states.environment` | String not enum-cast like `companies.fiscal_environment` | Drift in environment values | Cast/use `FiscalEnvironment` and consider check constraint | Low | Maybe | Model/factory/tests |
| Low | Index names | Mixed abbreviations: `certs`, `fiscal_docs`, `scope` | Minor cognitive overhead | Standardize new index names; rename only before production/reset | Low | Optional | Migrations only |
| Low | Timestamp naming | `valid_from`, `valid_until`, `not_before`, `not_after` lack `_at` | Minor convention drift but affects clarity | Use `_at` for datetime fields in future schema | Medium if renamed | Yes | Models/UI/tests |

## Compatibility notes

- `after()` column placement is used in several alter migrations. Treat it as cosmetic and MySQL-oriented. Future migrations should not rely on column order.
- Laravel `enum()` is convenient but not ideal for cross-database evolution. PHP enums with string columns are safer.
- JSON columns are supported by MySQL/PostgreSQL, but indexing JSON differs. Do not put frequently queried stable attributes only in JSON.
- Several unique constraints include nullable columns. MySQL and PostgreSQL both allow multiple rows where a unique component is null. This is useful for nullable `nsu`, but risky for settings and certificate inventory keys.

## Recommendation

Do not start with renames. Start with invariants:

1. Decide whether production data exists.
2. If no real data exists, prefer a schema reset branch that rewrites the early migrations into a clean baseline.
3. If real data exists, use additive/rename migrations with preflight data checks and backfills.
4. First implementation block should address safe constraints/indexes and user FK naming, not cosmetic table renames.
