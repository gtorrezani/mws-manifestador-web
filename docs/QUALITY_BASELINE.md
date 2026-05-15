# Quality Baseline

Execution date: 2026-05-15 13:11:13 -03:00

## Environment notes

- Repository: `mws-manifestador-web`
- Branch at execution start: `main`
- PHP reported by PHPUnit: `8.5.3`
- The worktree already contained unrelated local changes before this baseline was created.

## Commands executed

| Command | Result |
| --- | --- |
| `composer install` | Passed. Lock file was valid; nothing to install, update, or remove. |
| `npm ci` | Failed in PowerShell before npm executed: `npm.ps1` is blocked by local execution policy. |
| `npm.cmd ci` | Failed with `EPERM: operation not permitted, unlink 'C:\Git\mws-manifestador-web\node_modules\@esbuild\win32-x64\esbuild.exe'`. |
| `composer quality` | Failed. Pint passed; PHPStan failed with 3 errors. |
| `npm.cmd run quality` | Failed because `eslint` was not found after the failed `npm ci`. |
| `vendor\bin\phpunit --colors=always tests\Unit\Agent\AgentHmacContractTest.php` | Passed functionally: 1 test, 20 assertions. PHPUnit reported 2 deprecations under local PHP 8.5.3. |
| PowerShell parse check for `scripts\quality.ps1` | Passed. |
| `bash -n scripts/quality.sh` | Not run: `bash` is not installed or not available in `PATH` on this machine. |

## Failures found

- `npm ci` could not be executed through `npm.ps1` because PowerShell script execution is disabled on this machine.
- `npm.cmd ci` reached npm but could not replace `node_modules\@esbuild\win32-x64\esbuild.exe`, likely because the binary was locked by another process or local protection software.
- `composer quality` failed in PHPStan on pre-existing local changes:
  - `app\Models\User.php`: `HasFactory` generic type not specified.
  - `tests\Feature\Auth\AuthenticationTest.php`: possible null access on `$last_login_at`.
  - `tests\Feature\CompanyContextTest.php`: uninitialized `$user` property.
- `npm.cmd run quality` failed at `npm run lint` because local Node dependencies were unavailable after the failed install.

## Actions taken

- Recorded the npm PowerShell execution policy blocker and retried with `npm.cmd`.
- Did not delete `vendor` or `node_modules`.
- Ran the HMAC contract PHPUnit test directly to validate the Web/API side of the shared contract despite the broader PHPStan failure.
- Parsed `scripts\quality.ps1` to catch PowerShell syntax errors without re-triggering the blocked npm install path.

## Next technical risks

- Resolve the PHPStan errors from the existing local work before relying on `composer quality` as a green baseline.
- Release the lock on `node_modules\@esbuild\win32-x64\esbuild.exe` and rerun `npm ci` followed by `npm run quality`.
- Investigate PHPUnit deprecations under PHP 8.5.3, or run the suite under the project platform target PHP 8.3.
- Keep the HMAC fixture synchronized with the .NET agent fixture whenever the authentication contract changes.

## Rodada 2

Execution date: 2026-05-15 13:44:28 -03:00

### Commands executed

| Command | Result |
| --- | --- |
| `git branch --show-current` | Confirmed `codex/quality-baseline-hmac-contract`. |
| `git status -sb` | Confirmed many unrelated local changes were already present before this round. They were preserved. |
| `composer phpstan` | Initially failed with the 3 known PHPStan errors; passed after the focused fixes. |
| `composer pint-test` | Initially failed only on formatting in `app\Models\User.php`; passed after Pint formatting. |
| `vendor\bin\pint app\Models\User.php tests\Feature\Auth\AuthenticationTest.php tests\Feature\CompanyContextTest.php` | Passed and formatted `app\Models\User.php`. |
| `vendor\bin\phpunit --colors=always` | Passed functionally: 89 tests, 497 assertions. PHPUnit reported 2 deprecations under local PHP 8.5.3. |
| `composer quality` | Passed. Pint, PHPStan, and PHPUnit completed successfully. PHPUnit still reported 2 deprecations under PHP 8.5.3. |
| `Get-Process node,esbuild,vite` and `Get-CimInstance Win32_Process ...` | Found a Vite/esbuild process running from this repository and holding `node_modules\@esbuild\win32-x64\esbuild.exe`. |
| `Stop-Process -Id 7500,28700,23616` | Stopped the local Vite/esbuild/cmd processes tied to this repository. |
| `npm.cmd ci` | Passed after the local Vite/esbuild process was stopped. No `node_modules` backup rename was needed. |
| `npm.cmd run lint` | Passed. |
| `npm.cmd run format:check` | Passed. |
| `npm.cmd run typecheck` | Passed. |
| `npm.cmd run build` | Passed. |
| `npm.cmd run quality` | Passed. |

### PHPStan fixes

- `app\Models\User.php`: added the Larastan generic trait annotation for `HasFactory<UserFactory>`.
- `tests\Feature\Auth\AuthenticationTest.php`: refreshed the user into a local variable and asserted it is not null before checking `last_login_at`.
- `tests\Feature\CompanyContextTest.php`: removed the uninitialized test property and created the authenticated user through a small helper inside each test.

### npm/esbuild status

The prior `EPERM` on `node_modules\@esbuild\win32-x64\esbuild.exe` was caused by a local Vite/esbuild process running from this repository. After stopping that process, `npm.cmd ci` passed. `node_modules` was not deleted or renamed.

### Remaining notes

- The quality baseline is now green for PHP and Node on this machine.
- PHPUnit still reports 2 deprecations when run under PHP 8.5.3. This is not a functional failure, but should be reviewed separately or compared against the project target PHP 8.3 runtime.
- Unrelated local changes remain in the worktree and were not included in this baseline round.

## Rodada 3

Execution date: 2026-05-15 13:50:40 -03:00

### Commands executed

| Command | Result |
| --- | --- |
| `git status -sb` | Confirmed branch `codex/quality-baseline-hmac-contract` with the pending local worktree changes listed below. |
| `git diff --stat` | Found 35 tracked modified files, 1629 insertions and 413 deletions, not counting untracked files. |
| `git diff --name-status` | Listed all tracked modified files. |
| `git diff -- app/Models/User.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/CompanyContextTest.php` | Confirmed the PHPStan fixes are embedded in broader auth/company-user changes relative to `HEAD`. |
| `git status --porcelain=v1 -uall` | Listed tracked and untracked pending files. |
| Focused `git diff` and `Get-Content` reads for auth, company context, certificate, migration, frontend, and test groups | Completed inventory without staging or editing files. |

### Worktree inventory

Category legend:

- A: technical quality/PHPStan-only change.
- B: authentication behavior.
- C: company-user/company context behavior.
- D: migration/schema or code directly coupled to pending schema.
- E: tests/fixtures.
- F: documentation/scripts.
- G: generated, temporary, or should not be committed.

Tracked modified files:

| File | Category | Notes |
| --- | --- | --- |
| `app/Actions/Certificates/LinkA3CertificateAction.php` | D | Depends on pending agent certificate classification/document schema. |
| `app/Actions/Certificates/RecordAgentCertificateInventoryAction.php` | D | Implements classification/document fields from pending certificate migration. |
| `app/Actions/Certificates/StoreA1CertificateAction.php` | D | Certificate validation behavior; should be reviewed with certificate tests. |
| `app/Http/Controllers/Web/CertificateController.php` | D | Uses pending certificate classification fields and new inventory payload flags. |
| `app/Http/Controllers/Web/CompanyController.php` | C | Scopes companies, agents, and certificates to authenticated user's companies. |
| `app/Http/Controllers/Web/CurrentCompanyController.php` | C | Switches current company through available companies. |
| `app/Http/Requests/Certificate/StoreA1CertificateRequest.php` | D | Certificate upload validation behavior. |
| `app/Http/Requests/CurrentCompany/UpdateCurrentCompanyRequest.php` | C | Requires selected company to exist in `company_user`. |
| `app/Models/AgentCertificate.php` | D | Casts pending certificate classification/schema fields. |
| `app/Models/Company.php` | C | Adds `users()` relation for `company_user`. |
| `app/Models/User.php` | B | Full auth/user model change plus the Larastan `HasFactory<UserFactory>` fix. |
| `app/Services/Certificates/A1CertificateInspector.php` | D | Adds fiscal certificate validation rules. |
| `app/Support/CompanyContext/CurrentCompanyContext.php` | C | Restricts available companies by authenticated user. |
| `bootstrap/app.php` | B | Adds auth redirect behavior. |
| `database/factories/AgentCertificateFactory.php` | D | Factory values for pending certificate classification/schema fields. |
| `database/seeders/DatabaseSeeder.php` | B | Seeds user/auth-related data. |
| `docs/agent-installation-and-operations.md` | F | Documentation update. |
| `resources/js/Components/Layout/AppLayout.vue` | B | Adds logout and auth-aware navigation shell. |
| `resources/js/Components/StatusBadge.vue` | D | UI labels used by certificate/status changes. |
| `resources/js/Pages/Agents/Diagnostics.vue` | C | Uses company tabs/company area layout. |
| `resources/js/Pages/Agents/Index.vue` | C | Uses company tabs/company area layout and agent install copy updates. |
| `resources/js/Pages/Certificates/Index.vue` | D | Large certificate UI rewrite coupled to pending certificate schema fields. |
| `resources/js/Pages/Companies/Index.vue` | C | Company page layout/action changes. |
| `resources/js/Pages/Dashboard/Index.vue` | C | Layout subtitle change after company/auth shell changes. |
| `resources/js/Pages/FiscalDocuments/Index.vue` | C | Layout subtitle change after company/auth shell changes. |
| `resources/js/Pages/History/Index.vue` | C | Layout subtitle change after company/auth shell changes. |
| `resources/js/Pages/Settings/Edit.vue` | C | Uses company tabs/company area layout. |
| `resources/js/types/models.ts` | D | Adds pending certificate classification/schema fields to frontend types. |
| `routes/web.php` | B | Adds login/logout routes and wraps app routes in `auth`. |
| `scripts/publish-local-agent-installer.ps1` | F | Script update unrelated to PHPStan baseline. |
| `tests/Feature/Agent/AgentApiV1Test.php` | E | Adjusts fixture expectations for certificate classification. |
| `tests/Feature/Agent/AgentOperationsWebTest.php` | E | Test auth/company setup for protected operational routes. |
| `tests/Feature/CompanyContextTest.php` | E | Company-user behavior tests plus the uninitialized-property PHPStan fix. |
| `tests/Feature/OperationalCompanyIsolationTest.php` | E | Test auth/company setup for protected operational routes. |
| `tests/Fixtures/list-certificates-result.json` | E | Fixture for certificate classification behavior. |

Untracked files:

| File | Category | Notes |
| --- | --- | --- |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | B | New login/logout controller. |
| `app/Http/Requests/Auth/LoginRequest.php` | B | CPF login, rate limiting, active/blocked checks, `last_login_at`. |
| `app/Rules/ValidCpf.php` | B | Auth validation support. |
| `app/Support/Cpf.php` | B | CPF normalization/validation support. |
| `config/auth.php` | B | Laravel auth guard/provider configuration. |
| `database/factories/UserFactory.php` | B | User factory required by auth tests and `HasFactory<UserFactory>`. |
| `database/migrations/2026_05_14_060000_add_agent_certificate_classification_fields.php` | D | Certificate classification schema. |
| `database/migrations/2026_05_15_000001_create_users_table.php` | D | User/auth schema. |
| `database/migrations/2026_05_15_020000_create_company_user_table.php` | D | Company-user pivot schema. |
| `resources/js/Components/CompanyTabs.vue` | C | Company area navigation component. |
| `resources/js/Pages/Auth/Login.vue` | B | Login UI. |
| `tests/Feature/Auth/AuthenticationTest.php` | E | Auth behavior tests plus the null-refresh PHPStan fix. |
| `tests/Feature/Certificate/StoreA1CertificateRequestTest.php` | E | Certificate request validation tests. |
| `tests/Unit/Support/CpfTest.php` | E | CPF support tests. |

Generated/temporary files:

- No generated, temporary, backup, `vendor`, `node_modules`, `public/build`, logs, secrets, certificates, or fiscal XML files appeared in `git status`.

### Dependency analysis

The PHPStan fixes cannot be isolated into a clean technical commit against `HEAD` without mixing feature scope:

- `app/Models/User.php`: the requested `@use HasFactory<UserFactory>` fix only makes sense after the pending auth/user model changes add `HasFactory`, `Notifiable`, CPF fields, login state fields, and `companies()`. Committing this file would also commit auth/company-user behavior and depends on untracked `database/factories/UserFactory.php`, `app/Support/Cpf.php`, and the pending users/company_user migrations.
- `tests/Feature/Auth/AuthenticationTest.php`: the null-refresh fix is inside an untracked auth feature test file. Committing it requires the auth controller, request, CPF rule/support, auth config, login page, routes, user factory, and users migration.
- `tests/Feature/CompanyContextTest.php`: the uninitialized-property fix is embedded in company-user behavior tests. Relative to `HEAD`, the file also adds authenticated users, `companies()` pivot attachments, and new company isolation cases. It depends on `app/Models\User.php`, `app/Models\Company.php`, `CurrentCompanyContext`, `company_user` migration, and auth setup.

Real dependencies:

- Auth foundation: `User.php`, `UserFactory.php`, users migration, `config/auth.php`, auth controller/request/rule/support, login route/UI, auth tests.
- Company-user context: company-user migration, `Company::users()`, `User::companies()`, current company resolver/context/controller/request, route auth wrapper, company context tests, operational route test setup.
- Certificate classification: agent certificate migration, model casts, inventory recorder, certificate controller/actions/inspector, factory, fixture, frontend certificate page/types, certificate tests.

Accidental or separable dependencies:

- Layout copy/subtitle changes can be separated from auth/company behavior if they do not rely on route/auth changes.
- `scripts/publish-local-agent-installer.ps1` and `docs/agent-installation-and-operations.md` are independent documentation/script work.
- Status label text updates can be separated unless kept with the certificate UI changes for review coherence.

### Commit decision

No `chore: commit web phpstan baseline fixes` commit was created in this round. A technical-only commit would either be empty relative to `HEAD` or would need to stage broader auth/company-user files and migrations, which would violate the requested scope separation.

### Suggested commit plan for remaining work

| Suggested commit | Files | Objective | Risk | Required tests | Schema dependency |
| --- | --- | --- | --- | --- | --- |
| `feat: add cpf authentication foundation` | `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Rules/ValidCpf.php`, `app/Support/Cpf.php`, `config/auth.php`, `database/factories/UserFactory.php`, `database/migrations/2026_05_15_000001_create_users_table.php`, `resources/js/Pages/Auth/Login.vue`, auth parts of `routes/web.php`, `bootstrap/app.php`, `app/Models/User.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Unit/Support/CpfTest.php` | Introduce CPF login/logout and user model support. | High: changes access control and login behavior. | `composer quality`, focused auth tests, `npm.cmd run quality`. | Yes: users table. |
| `feat: scope web operations by company user` | `database/migrations/2026_05_15_020000_create_company_user_table.php`, `app/Models/Company.php`, company-user parts of `app/Models/User.php`, `app/Support/CompanyContext/CurrentCompanyContext.php`, `app/Http/Controllers/Web/CompanyController.php`, `app/Http/Controllers/Web/CurrentCompanyController.php`, `app/Http/Requests/CurrentCompany/UpdateCurrentCompanyRequest.php`, `tests/Feature/CompanyContextTest.php`, related operational test setup | Restrict company selection and operational pages to companies linked to authenticated users. | High: authorization/isolation behavior. | `composer quality`, company context and operational isolation tests. | Yes: `company_user`. |
| `feat: classify fiscal certificates from agent inventory` | certificate migration, `AgentCertificate.php`, certificate actions/controller/inspector/request/factory, `tests/Fixtures/list-certificates-result.json`, agent/certificate tests | Store and filter fiscal certificate candidates safely. | High: certificate selection and fiscal readiness behavior. | `composer quality`, agent API tests, certificate request tests. | Yes: agent certificate fields. |
| `feat: refresh company certificate UI` | `resources/js/Pages/Certificates/Index.vue`, `resources/js/types/models.ts`, `resources/js/Components/StatusBadge.vue`, related `CertificateController.php` props | Present fiscal candidates, ignored certificates, and linked certificates. | Medium-high: large frontend change. | `npm.cmd run quality`, `composer quality` if controller props change. | Yes: certificate classification fields. |
| `feat: organize company area navigation` | `resources/js/Components/CompanyTabs.vue`, `resources/js/Components/Layout/AppLayout.vue`, agent/settings/company/dashboard/history/fiscal document page layout updates | Group settings/agents/certificates under company area. | Medium: navigation and UX behavior. | `npm.cmd run quality`, targeted smoke tests for routes. | No direct schema dependency, but depends on auth/company route shape. |
| `docs: update local agent installer docs` | `docs/agent-installation-and-operations.md`, `scripts/publish-local-agent-installer.ps1` | Document/update installer publishing flow. | Low-medium: operational script behavior. | PowerShell parse check and manual script review. | No. |

### Pending risks

- The pending worktree includes broad functional auth, authorization, certificate, schema, and frontend changes. It should not be committed as one patch.
- Some Portuguese strings in pending untracked frontend/auth files appear mojibake-encoded and should be corrected before committing those functional changes.
- Each migration should be reviewed with rollback behavior and compatibility against existing data before merge.
- The HMAC contract files were not touched in this inventory round.

## Rodada 4

Execution date: 2026-05-15 14:01:41 -03:00

### Scope decision

Created the CPF authentication foundation as a small auth-only slice. The staged scope intentionally includes login/logout routes but does not wrap the existing application routes in `auth` yet, because doing that cleanly requires broader updates to operational route tests and should be handled with the company-user authorization block.

Included files:

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Models/User.php`
- `app/Rules/ValidCpf.php`
- `app/Support/Cpf.php`
- `config/auth.php`
- `database/factories/UserFactory.php`
- `database/migrations/2026_05_15_000001_create_users_table.php`
- `resources/js/Pages/Auth/Login.vue`
- `routes/web.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Unit/Support/CpfTest.php`

Deliberately left out:

- `database/migrations/2026_05_15_020000_create_company_user_table.php`
- `app/Support/CompanyContext/CurrentCompanyContext.php`
- company-user relation hunks from `app/Models/User.php`
- company-user/controller/request changes
- certificate migrations, certificate actions/controllers/frontend, and certificate fixtures
- `resources/js/Components/CompanyTabs.vue`
- broad layout/navigation changes and local seeder changes that depend on company-user

### Commands executed

| Command | Result |
| --- | --- |
| `git status -sb` | Confirmed branch `codex/quality-baseline-hmac-contract` and pending mixed worktree. |
| `git diff --name-status` | Confirmed mixed auth, company-user, certificate, docs, script, test, and frontend changes before staging. |
| Auth/CPF file review through `Get-Content` and focused diffs | Confirmed CPF normalization/validation, login rate limiting, blocked/inactive handling, session regeneration, logout invalidation, auth config, user factory, and users migration. |
| `vendor\bin\phpunit --colors=always tests\Unit\Support\CpfTest.php tests\Feature\Auth\AuthenticationTest.php` | Passed functionally: 17 tests, 53 assertions. PHPUnit reported 2 deprecations under local PHP 8.5.3. |
| `composer pint-test` | Passed. |
| `composer phpstan` | Passed. |
| `vendor\bin\phpunit --colors=always` | Passed functionally: 89 tests, 497 assertions. PHPUnit reported 2 deprecations under local PHP 8.5.3. |
| `composer quality` | Passed. |
| `npm.cmd run lint` | Passed. |
| `npm.cmd run format:check` | Passed. |
| `npm.cmd run typecheck` | Passed. |
| `npm.cmd run build` | Passed. |
| `npm.cmd run quality` | Passed. |
| `git diff --cached --check` | Passed after restaging selective blobs without BOM/trailing whitespace. |

### Notes

- `Cpf` is stateless, normalizes masks to digits, rejects invalid length and repeated digits, and validates both check digits.
- `ValidCpf` delegates to `Cpf` and returns a Portuguese validation message.
- `LoginRequest` authenticates by normalized CPF, rate limits failed attempts, keeps generic invalid-credential messaging, blocks inactive/blocked users, and updates `last_login_at` only after a successful login.
- `AuthenticatedSessionController` renders the login page, authenticates, regenerates the session, logs out, invalidates the session, and regenerates the CSRF token.
- `User.php` was staged without `companies()` so this commit does not introduce company-user behavior.

### Remaining risks and next blocks

- Application-wide route protection and company-user authorization remain pending and should be committed as a separate company-user scope.
- Local worktree still contains broad pending company-user, certificate classification, certificate UI, docs, scripts, and test changes outside this commit.
- PHPUnit deprecations under PHP 8.5.3 remain non-fatal and should be reviewed separately or compared against the target PHP 8.3 runtime.
