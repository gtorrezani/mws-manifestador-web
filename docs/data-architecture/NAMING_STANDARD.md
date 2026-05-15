# Database naming standard

Date: 2026-05-15

This is the proposed canonical standard for `mws-manifestador-web`.

## Principles

1. Prefer Laravel conventions unless there is a domain reason not to.
2. Database names must be boring, explicit, and stable.
3. Public identifiers and internal surrogate keys are different concepts.
4. Multi-tenant isolation must be enforceable by the database where practical, not only by controller code.
5. JSON is for flexible or external payload snapshots, not for stable searchable attributes.
6. Do not adopt GeneXus-style prefixed names such as `User_Name` or `Company_Code` in this Laravel codebase. They work against Eloquent conventions and raise mapping cost without solving a current problem.

## Tables

Use plural snake_case table names for entities:

- `companies`
- `agent_commands`
- `fiscal_documents`
- `company_certificates`

Use singular_singular snake_case for pure many-to-many pivots:

- `company_user`

If a pivot gains attributes with domain meaning beyond the relationship itself, promote it to a named entity table:

- keep `company_user` for membership only;
- use a future `company_memberships` table only if roles, invitations, statuses, or audit state become first-class.

Avoid generic table names unless the scope columns are explicit and constrained. `system_settings` is acceptable only if scope semantics are tightened.

## Primary and public keys

- Primary key: `id` as unsigned big integer via Laravel `$table->id()`.
- Public key: `uuid` for resources exposed outside internal admin code.
- Do not expose integer `id` in public API contracts unless the route is strictly internal and authenticated.
- If `users` become public/admin resources, add `uuid` before exposing user references.

## Foreign keys

Use `<entity>_id`:

- `tenant_id`
- `company_id`
- `agent_id`
- `agent_command_id`
- `company_certificate_id`

Actor/user references must explicitly say they reference a user:

- `requested_by_user_id`
- `created_by_user_id`
- `actor_user_id`
- `locked_by_agent_id`
- `used_by_agent_id`

Avoid:

- `requested_by`
- `created_by`

Every FK column should have an actual foreign key unless there is a documented reason not to. If historical records must survive user deletion, use `nullOnDelete()` plus denormalized actor display metadata in an audit payload.

## Tenant and company scope

For company-owned tables, the preferred query shape is still:

```text
tenant_id, company_id
```

But the database must prevent inconsistent pairs. Use one of these strategies:

1. Composite FK strategy:
   - add a unique key on `companies(id, tenant_id)`;
   - add composite FKs from child tables `(company_id, tenant_id)` to `companies(id, tenant_id)`.
2. Derived tenant strategy:
   - remove duplicated `tenant_id` from tables where `company_id` is mandatory;
   - derive tenant through the company relation.

For this project, the composite FK strategy is safer incrementally because the code already queries by both `tenant_id` and `company_id`.

## Timestamps and dates

Use Laravel timestamps:

- `created_at`
- `updated_at`
- `deleted_at`

Use `_at` for event instants:

- `last_seen_at`
- `activated_at`
- `revoked_at`
- `requested_at`
- `completed_at`
- `failed_at`
- `received_at`
- `sent_at`

For certificate validity, choose one canonical pair and use it everywhere:

- recommended: `valid_from_at`, `valid_until_at`

If the raw agent payload uses `not_before`/`not_after`, map those at the boundary and store only the canonical columns unless raw payload retention is explicitly needed.

## Booleans

Use positive, readable names:

- `is_active`
- `is_expired`
- `is_valid`
- `is_fiscal_candidate`
- `has_private_key`
- `can_retry`
- `should_notify`

Avoid negated booleans such as `not_active` or ambiguous names such as `valid`.

## Status and enums

Use PHP backed enums in code. In the database, prefer string columns with documented allowed values:

```php
$table->string('status', 40)->default('pending');
```

Native database enum columns are acceptable only for very stable values, but they make cross-database evolution harder. For this project, future migrations should prefer string columns plus:

- PHP enum casts;
- validation rules;
- optional check constraints only when they can be maintained consistently across MySQL and PostgreSQL.

Status columns should be named by domain when multiple statuses exist in one row:

- `status` for the row lifecycle;
- `manifestation_status`;
- `xml_download_status`;
- `last_sefaz_status_code`;
- `last_distribution_status_code`.

## Fiscal documents

Canonical fiscal fields:

- `access_key` for NF-e chave de acesso, `char(44)`.
- `nsu` as string, not integer, because leading zeros matter.
- `issuer_cnpj`, `recipient_cnpj` as normalized `char(14)`.
- `uf` as `char(2)`.
- `total_amount` as `decimal(15, 2)`.
- XML content should live in storage, referenced by disk/path/hash columns, not in JSON or logs.

Avoid storing full fiscal XML in `metadata`, `payload`, `raw_payload`, or logs.

## Certificates

Canonical certificate fields:

- `thumbprint`
- `serial_number`
- `subject`
- `issuer`
- `common_name`
- `document`
- `document_type`
- `valid_from_at`
- `valid_until_at`
- `store_location`
- `store_name`
- `has_private_key`
- `is_certificate_authority`
- `is_fiscal_candidate`
- `is_icp_brasil`
- `is_usable_for_client_auth`
- `classification`
- `rejection_reasons`
- `warnings`

Avoid duplicate aliases:

- do not keep both `subject_name` and `subject`;
- do not keep both `issuer_name` and `issuer`;
- do not keep both `valid_from` and `not_before`;
- do not keep both `valid_until` and `not_after`;
- do not keep both `cnpj` and `document` unless `cnpj` is a generated/search helper with a documented reason;
- do not keep both `store_scope` and `store_location`.

Do not store:

- PFX/P12/PEM contents in database text/json fields;
- private keys;
- A3 PIN;
- A1 password in plaintext.

## JSON fields

Allowed JSON uses:

- sanitized external payload snapshots;
- non-query diagnostic details;
- structured metadata that changes often;
- command request/result payloads.

JSON field names should say what they contain:

- `metadata`
- `payload`
- `request_payload`
- `result_payload`
- `sanitized_payload`
- `summary_payload`

Rules:

- Never store secrets or full fiscal XML in JSON.
- Do not store frequently filtered attributes only in JSON.
- If a JSON key becomes part of a query or business invariant, promote it to a typed column.

## Index and constraint names

Prefer explicit names when default names become too long or unclear.

Format:

```text
<table>_<columns>_<kind>
```

Examples:

- `agents_tenant_installation_unique`
- `agent_commands_scope_status_idx`
- `fiscal_documents_scope_access_key_unique`

Avoid over-shortening table names unless required by database length limits. If abbreviating, use one abbreviation consistently:

- prefer `agent_certificates_...` if length allows;
- use `agent_certs_...` only when needed.

## Delete behavior

Default rules:

- parent tenant deletion may cascade in non-production/dev contexts, but production should restrict or archive before tenant deletion;
- company-owned operational data may cascade only if company deletion is a real destructive operation;
- audit logs should usually use `nullOnDelete()` for actors and referenced entities;
- user actor fields should use `nullOnDelete()` and preserve event metadata;
- sensitive credential rows should cascade with their owning agent.

## Migration style

Future migrations should:

- be small and reversible;
- avoid cosmetic column ordering via `after()` unless strictly necessary;
- include preflight checks before destructive changes;
- backfill before enforcing non-null constraints;
- split rename/backfill/drop into separate deployable steps when production data exists;
- include tests for model casts, relations, and cross-company isolation when constraints change.
