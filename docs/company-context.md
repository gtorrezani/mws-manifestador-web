# Company Context

## Overview

The Web/API uses a server-side current company context for operational screens. The selected company is stored in the Laravel session under `current_company_id` and resolved on every web request.

The frontend displays the current company and sends switch requests, but it is never the source of truth for filtering, command creation, or route model authorization.

## Tenant vs Company

- `tenant_id` identifies the commercial/account boundary.
- `company_id` identifies the fiscal operating company inside a tenant.
- Current implementation considers every active company available because real authentication and RBAC are not implemented yet.

TODO auth/rbac: restrict available companies by authenticated user, tenant membership, role, and explicit permissions.

## Resolution Flow

1. `ResolveCurrentCompany` runs in the web middleware group.
2. `CurrentCompanyContext` reads `current_company_id` from the session.
3. If the selected company is active, it becomes the current company.
4. If there is no selection, or the stored company is inactive/missing, the context clears the session and selects the first active company.
5. Operational routes use `company.selected`; if no active company exists, the request is redirected to the Companies screen.

## Switching Company

`POST /current-company` receives `company_id`, validates that the company exists and is active, and stores the selected id in session.

The response redirects back to the previous page. Controllers must reload data using the backend context, not request query parameters.

## Operational Routes

These routes are scoped by the selected company:

- Dashboard
- Agents
- Certificates
- Fiscal Documents
- History
- Settings

Controllers must use `CurrentCompanyContext` and apply `where company_id = current_company_id` through explicit scopes such as `forCompany()`.

## Administrative Exception

The Companies screen may list all active/inactive companies available to the tenant because it is an administrative screen. It still receives the current company in Inertia props for the top selector.

## Route Model Binding

Operational controllers must not trust route model binding alone. Any route receiving a model with `company_id` must validate the model against `CurrentCompanyContext`.

Current guarded models include:

- `Agent`
- `CompanyCertificate`
- `FiscalDocument`
- `AgentCertificate` through explicit link validation

## Agent API

`/api/agent/v1/*` is machine-to-machine traffic and does not use the web session. It must continue to resolve tenant/company from the authenticated agent, command, and HMAC credentials.

Do not apply `ResolveCurrentCompany`, `EnsureCurrentCompanySelected`, or Inertia middleware to Agent API routes.

## Security Notes

- Do not accept `company_id` from operational filters as an isolation boundary.
- Bulk operations must query documents by current company before processing ids.
- Command creation must use the selected company or a document already validated against the selected company.
- Settings are company-scoped unless explicitly implemented as global/admin settings.
