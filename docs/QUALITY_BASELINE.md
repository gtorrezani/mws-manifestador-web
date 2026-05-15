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
