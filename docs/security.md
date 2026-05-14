# Seguranca

## Ameacas Principais

- replay de requisicoes do agente;
- roubo de segredo HMAC;
- uso de agente revogado;
- exposicao de PIN A3 ou senha A1;
- vazamento de XML fiscal em logs;
- comandos duplicados ou executados em paralelo;
- upload de diagnosticos contendo dados sensiveis;
- comprometimento de storage de XMLs.

## Segredos

Segredos de agente sao gerados na ativacao e retornados uma unica vez. No servidor, o payload do segredo e criptografado com o mecanismo de criptografia do Laravel. No agente, o armazenamento deve usar DPAPI ou mecanismo equivalente.

Nao permitido:

- commitar `.env`;
- commitar certificados, PFX/P12, chaves privadas ou PIN;
- logar HMAC secret;
- trafegar senha A1 em texto puro.

## Certificado A1

Estado atual: suporte preparado, mas cadastro e fluxo final ainda sao TODO.

Regras obrigatorias:

- senha nunca em texto puro;
- se armazenado no agente, usar protecao segura do Windows;
- separar `CertificateReference` de `CertificateSecret`;
- nao registrar senha, PFX ou segredo em logs.

## Certificado A3

Estado atual: agente lista e valida certificados do Windows Certificate Store.

Regras:

- PIN nao e armazenado;
- PIN nao trafega para a API;
- PIN e solicitado pelo provedor/driver do token quando necessario;
- ausencia de token, certificado removido e erro de provider devem virar erro tecnico claro.

## Logs

Logs devem ser estruturados e sanitizados.

Nao logar:

- XML completo por padrao;
- PIN A3;
- senha A1;
- segredo HMAC;
- PFX/P12;
- chave privada;
- payloads fiscais completos sem sanitizacao.

O agente possui diagnostico XML sanitizado. Modo diagnostico com XML deve ser restrito e auditado.

## Auditoria

Eventos relevantes:

- ativacao de agente;
- heartbeat;
- comandos criados;
- start/complete/fail;
- manifestacoes e tentativas;
- status/protocolo/mensagem SEFAZ;
- revogacao de agente;
- diagnosticos recebidos.

TODO:

- Normalizar taxonomia final de eventos de auditoria.
- Definir retencao por tenant.

## HMAC

Apos ativacao, as chamadas usam HMAC-SHA256 com headers:

```json
{
  "X-MWS-Agent-Id": "agent-uuid",
  "X-MWS-Timestamp": "1778756400",
  "X-MWS-Nonce": "nonce-uuid",
  "X-MWS-Body-SHA256": "body-hash",
  "X-MWS-Signature": "hmac-signature"
}
```

Canonical string:

```text
METHOD
PATH
TIMESTAMP
NONCE
BODY_SHA256
```

## Replay Attack

Mitigacoes implementadas:

- timestamp com tolerancia configuravel, padrao 300 segundos;
- nonce unico por agente dentro da janela de tolerancia;
- hash do corpo validado antes da assinatura;
- segredo ativo ou pendente precisa assinar a string canonica.

## Nonce

Nonce e salvo em cache com chave por agente. Reuso dentro da janela gera `401`. Redis e recomendado em producao para cache compartilhado entre instancias.

## Rate Limit

TODO:

- Aplicar rate limit especifico para endpoints do agente por `agent_id` e IP observado.
- Aplicar rate limit restritivo para ativacao por codigo temporario.
- Definir bloqueio progressivo para excesso de HMAC invalido.

## Checklist

- Usar HTTPS em producao.
- Rotacionar APP_KEY com procedimento controlado.
- Usar Redis compartilhado para nonce.
- Configurar storage privado para XMLs.
- Revisar logs antes de habilitar modo diagnostico.
