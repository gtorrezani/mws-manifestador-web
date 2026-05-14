# Protocolo do Agente

## Resumo

O agente se comunica com a API central por HTTPS usando endpoints REST versionados. A ativacao usa codigo temporario. Depois da ativacao, todas as chamadas usam assinatura HMAC.

Base atual:

```text
/api/agent/v1
```

## Ativacao

Endpoint:

```http
POST /api/agent/v1/activate
```

Exemplo:

```json
{
  "activation_code": "123456",
  "installation_id": "install-001",
  "machine_name": "CLIENTE-FINANCEIRO",
  "version": "1.0.0",
  "certificate_inventory": [
    {
      "thumbprint": "ABCDEF123456",
      "subject_name": "CN=EMPRESA TESTE",
      "valid_until": "2026-12-31T23:59:59Z"
    }
  ]
}
```

Resposta:

```json
{
  "agent_id": "2c7ab9f9-7d78-42f2-9f43-3ef4f2bb3d36",
  "secret": "returned-only-once",
  "auth": {
    "algorithm": "HMAC-SHA256",
    "canonical_format": "METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY_SHA256",
    "timestamp_tolerance_seconds": 300
  },
  "polling_interval_seconds": 30
}
```

O segredo e retornado apenas na ativacao e deve ser armazenado com protecao local no Windows.

## Heartbeat

Endpoint:

```http
POST /api/agent/v1/heartbeat
```

Exemplo:

```json
{
  "status": "online",
  "version": "1.0.1",
  "machine_name": "CLIENTE-FINANCEIRO",
  "metrics": {
    "uptime_seconds": 3600
  }
}
```

A API registra versao, status, maquina e IP publico observado.

## Polling

Endpoint:

```http
POST /api/agent/v1/commands/poll
```

Exemplo:

```json
{
  "limit": 10,
  "capabilities": [
    "sync_fiscal_documents",
    "manifest_acknowledgement",
    "manifest_confirmation",
    "download_xml_by_access_key"
  ]
}
```

Resposta:

```json
{
  "server_time": "2026-05-14T12:00:00Z",
  "commands": [
    {
      "uuid": "7a54c715-4e6c-45d4-9b56-f5a35f4f6df2",
      "type": "manifest_acknowledgement",
      "priority": 20,
      "payload": {
        "access_key": "00000000000000000000000000000000000000000000",
        "cnpj": "00000000000000",
        "uf": "SP",
        "environment": "homologation",
        "certificate_thumbprint": "ABCDEF123456",
        "correlation_id": "corr-001"
      },
      "idempotency_key": "tenant:type:document:hash",
      "lock_expires_at": "2026-05-14T12:05:00Z",
      "attempts_count": 0,
      "max_attempts": 3
    }
  ]
}
```

## Lock

O poll seleciona comandos `pending` ou locks expirados, ordena por prioridade e data de criacao, aplica lock transacional e retorna os comandos. A duracao padrao do lock e configurada em `MWS_AGENT_COMMAND_LOCK_SECONDS`, atualmente 300 segundos.

Estados de comando implementados:

- `pending`
- `locked`
- `processing`
- `completed`
- `failed`
- `cancelled`
- `expired`

## Start

Endpoint:

```http
POST /api/agent/v1/commands/{commandUuid}/start
```

Marca comando bloqueado como `processing` e cria tentativa de execucao.

## Complete

Endpoint:

```http
POST /api/agent/v1/commands/{commandUuid}/complete
```

Exemplo:

```json
{
  "result": {
    "event_code": 210210
  },
  "sefaz": {
    "service": "NFeRecepcaoEvento4",
    "environment": "homologation",
    "correlation_id": "corr-001"
  },
  "protocol_number": "135240000000001",
  "sefaz_status_code": "135",
  "sefaz_message": "Evento registrado e vinculado a NF-e",
  "duration_ms": 820,
  "request_xml": {
    "storage_disk": "local",
    "storage_path": "agent-temp/corr-001-event-request.xml",
    "content_hash": "sha256-sanitized-example"
  },
  "response_xml": {
    "storage_disk": "local",
    "storage_path": "agent-temp/corr-001-event-response.xml",
    "content_hash": "sha256-sanitized-example"
  }
}
```

Se o comando ja estiver `completed`, a API responde de forma idempotente.

## Fail

Endpoint:

```http
POST /api/agent/v1/commands/{commandUuid}/fail
```

Exemplo:

```json
{
  "error_code": "SEFAZ_TIMEOUT",
  "error_message": "Timeout ao consultar SEFAZ.",
  "error_details": {
    "operation": "NFeRecepcaoEvento"
  },
  "duration_ms": 30000
}
```

Enquanto `attempts_count < max_attempts`, o comando volta para `pending`. Na falha final, vira `failed`.

## Autenticacao HMAC

Headers obrigatorios:

```text
X-MWS-Agent-Id
X-MWS-Timestamp
X-MWS-Nonce
X-MWS-Body-SHA256
X-MWS-Signature
```

String canonica:

```text
METHOD
PATH
TIMESTAMP
NONCE
BODY_SHA256
```

Exemplo conceitual:

```json
{
  "method": "POST",
  "path": "/api/agent/v1/heartbeat",
  "timestamp": 1778756400,
  "nonce": "d3ef4e89-6f89-4d1d-9865-1eddf0243f51",
  "body_sha256": "hex-sha256",
  "signature": "hex-hmac-sha256"
}
```

## Rotacao de Segredo

A autenticacao ja aceita segredo ativo e segredo pendente quando `pending_encrypted_secret_payload` estiver configurado e dentro da validade. Ao primeiro uso valido do segredo pendente, ele e promovido para segredo ativo.

TODO:

- Criar endpoint/acao administrativa para solicitar rotacao.
- Criar resposta ao agente indicando novo segredo pendente.

## Revogacao

Um agente revogado ou com credencial revogada recebe `403`. A revogacao impede novas chamadas autenticadas.

## TODO

- Definir contrato de payload por tipo de comando em JSON Schema.
- Definir politica final de backoff alem da regra atual baseada em tentativas.
- Definir endpoint administrativo para rotacao de segredo.
