# Local Run

## Prerequisites

- PHP compatible with the project Composer platform.
- Composer.
- Node.js and npm.
- Docker Desktop with the Docker CLI available in `PATH`.
- PowerShell.

## Ports

- Laravel Web/API: `http://127.0.0.1:8020`
- Vite: `http://127.0.0.1:8021`
- MySQL: host `127.0.0.1`, port `3307`, container port `3306`
- Redis: host `127.0.0.1`, port `6380`, container port `6379`
- Agent local diagnostics, if enabled: `http://127.0.0.1:8022`

## Environment

The local `.env` is ignored by Git. Use `.env.example` as the safe template. The default local database credentials are:

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=mws_manifestador
DB_USERNAME=root
DB_PASSWORD=secret
```

Redis is configured through:

```text
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6380
```

## Start MySQL and Redis

```powershell
.\scripts\local-up.ps1
```

If PowerShell blocks local scripts, run with execution policy bypass for this process:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\local-up.ps1
```

To skip migrations:

```powershell
.\scripts\local-up.ps1 -SkipMigrate
```

Manual equivalent:

```powershell
docker compose -f docker-compose.local.yml up -d
composer install
php artisan key:generate --force
php artisan migrate:fresh --seed
```

## Start Laravel and Vite

Use two terminals:

```powershell
.\scripts\local-web.ps1
```

```powershell
.\scripts\local-vite.ps1
```

Open `http://127.0.0.1:8020`.

## Quality Gates

```powershell
composer pint-test
composer phpstan
composer test
npm ci
npm run quality
```

On Windows PowerShell, if script execution policy blocks `npm.ps1`, use `npm.cmd`:

```powershell
npm.cmd ci
npm.cmd run quality
```

## Agent Activation Code

The current web UI exposes agent activation from the Agents screen. For local validation:

1. Start MySQL, Redis, Laravel and Vite.
2. Open `http://127.0.0.1:8020/agents`.
3. Generate an activation code for an active company.
4. Put the code only in the local, ignored Agent configuration or pass it via environment variable.

Do not commit activation codes, agent secrets, certificate passwords, A1 PFX files or A3 PINs.

## Certificates

Open `http://127.0.0.1:8020/certificates` to operate certificate setup from the web UI.

- Use `Listar certificados` for an online agent to request the Windows Certificate Store inventory.
- Use `Vincular A3` to link an inventoried agent certificate to a company.
- Use `Cadastrar A1` to upload a PFX/P12 for cloud-side support. The PFX and password are stored encrypted by Laravel and must never be committed.
- Use `Testar` to create an agent command for A3 validation or to validate stored A1 metadata locally.

A3 PIN is never stored or requested by the Web/API. When the provider/token needs the PIN, Windows prompts from the local agent process.

### Manual list certificates flow

1. Start Laravel and Vite.
2. Start the local Agent in console mode.
3. Select the target company in the top bar.
4. Open `http://127.0.0.1:8020/certificates`.
5. Click `Listar certificados do agente ...` for an online agent.
6. Wait for the Agent polling cycle to execute `list_certificates`.
7. Refresh the Certificates screen and confirm the A3 inventory table shows subject, issuer, thumbprint, validity, store location, private key availability, detected CNPJ, validation status, and last seen time.

The command payload is intentionally empty. The Agent returns certificate metadata only; private keys, certificate passwords and A3 PINs must never be sent or stored.

### Manual test certificate flow

1. Start Laravel and Vite.
2. Start the local Agent in console mode.
3. Select the target company in the top bar.
4. Open `http://127.0.0.1:8020/certificates`.
5. Click `Listar certificados do agente ...` if the inventory is empty.
6. Choose an inventoried A3 certificate with thumbprint and private key.
7. Click `Testar`.
8. Wait for the Agent polling cycle to execute `test_certificate`.
9. Refresh the Certificates screen and confirm the last test status, message and timestamp were updated.

The `test_certificate` command payload contains only `thumbprint`, `store_location`, `correlation_id` and the source certificate UUID. It must never include PIN, password, private key material or PFX content.

### Manual test SEFAZ connectivity flow

1. Start Laravel, Vite and the local Agent.
2. Select the target company in the top bar.
3. Open `http://127.0.0.1:8020/certificates`.
4. Ensure there is an A3 company certificate linked to an online agent and tested as valid.
5. Click `Testar SEFAZ` to run `configuration_only`.
6. Wait for the Agent polling cycle.
7. Refresh the Certificates screen and confirm the Conectividade SEFAZ history shows status, endpoint, message, duration and timestamp.

`configuration_only` validates local payload, company UF/environment, endpoint resolution and certificate access. It does not call SEFAZ. `Homologação real` is intentionally explicit and currently returns a clear not-configured failure from the Agent until a non-mutating official SEFAZ probe is approved.

### Manual fiscal document sync flow

1. Start Laravel, Vite and the local Agent.
2. Select a company in homologation in the top bar.
3. Open `http://127.0.0.1:8020/certificates`.
4. Ensure there is an A3 company certificate linked to the company, attached to an online agent, and tested as valid.
5. Open `http://127.0.0.1:8020/fiscal-documents`.
6. Click `Consultar SEFAZ`.
7. Wait for the Agent polling cycle to execute `sync_fiscal_documents`.
8. Refresh the Fiscal Documents screen and confirm the NSU panel, command history and document list.

The first supported live fiscal call is `NFeDistribuicaoDFe` in homologation. The command payload contains CNPJ, UF, environment, certificate thumbprint, store location, last NSU and correlation ID. It must never contain PIN, password, PFX content or private key material.

Expected outcomes:

- `cStat=137`: command completes, NSU state is updated, and the UI shows `Nenhum documento localizado`.
- `cStat=138`: command completes, NSU state is updated, and returned `resNFe`/`procNFe` data is persisted without duplicating access keys.
- `cStat=656`: command fails with `SEFAZ_DISTRIBUTION_CONSUMPTION_DENIED`; last NSU is not advanced.
- Production distribution is blocked unless the Agent is explicitly configured with `Sefaz:AllowProductionDistribution=true`.

TODO: validate the distribution flow with an official homologation certificate/company pair and keep the returned `cStat`, endpoint and SOAP version in the homologation evidence log before declaring the integration production-ready.

## Stop Services

Stop Laravel/Vite with `Ctrl+C`.

Stop containers:

```powershell
docker compose -f docker-compose.local.yml down
```

Remove local database volume only when intentional:

```powershell
docker compose -f docker-compose.local.yml down -v
```

## Common Issues

- If Docker is not recognized, install Docker Desktop or add `docker.exe` to `PATH`.
- If MySQL port `3307` is busy, stop the conflicting process before changing project defaults.
- If Vite assets do not load, confirm Vite is running on `http://127.0.0.1:8021`.
- If Laravel cannot connect to Redis, confirm container `mws_manifestador_redis` is healthy.
