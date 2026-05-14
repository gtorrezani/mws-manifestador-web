# Integracao SEFAZ

## Servicos Usados

Camada implementada no agente .NET:

- `NFeDistribuicaoDFe`: distribuicao de documentos fiscais de interesse.
- `NFeRecepcaoEvento`: envio dos eventos de Manifestacao do Destinatario.

## Fluxo Geral

```mermaid
sequenceDiagram
    participant API as Laravel API
    participant Agent as Agent .NET
    participant Cert as Certificado Windows
    participant SEFAZ as SEFAZ

    Agent->>API: poll commands
    API-->>Agent: command payload
    Agent->>Cert: find certificate by thumbprint
    Agent->>Agent: build XML
    Agent->>Agent: sign XML when required
    Agent->>Agent: validate XSD
    Agent->>SEFAZ: SOAP over HTTPS with client certificate
    SEFAZ-->>Agent: SOAP response
    Agent->>API: complete/fail with status, protocol and XML references
```

## SOAP

O agente monta envelope SOAP usando endpoint resolvido por servico, UF e ambiente. A chamada e feita por `HttpClient` com certificado cliente quando exigido.

TODO:

- Revisar cabecalhos SOAP por UF/autorizador conforme schemas oficiais atualizados.
- Completar matriz oficial de endpoints para todas as UFs suportadas.

## XML Signature

Eventos de manifestacao sao assinados com XMLDSig no agente antes do envio. O signer recebe o XML, o certificado `X509Certificate2` e o atributo de referencia `Id`.

Regras:

- nao usar ACBr;
- nao logar XML completo por padrao;
- preservar XML de envio e retorno por referencia de storage;
- falha de assinatura deve retornar erro tecnico, nao sucesso fiscal.

## Schemas

O agente possui validador XSD (`NfeXmlSchemaValidator`) e usa diretorio configurado para schemas oficiais.

TODO:

- Versionar pacote de XSD oficial utilizado em producao.
- Criar rotina de atualizacao controlada dos schemas.

## Distribuicao DFe

Comando:

```json
{
  "type": "sync_fiscal_documents",
  "payload": {
    "uf": "SP",
    "environment": "homologation",
    "cnpj": "00000000000000",
    "last_nsu": "000000000000000",
    "certificate_thumbprint": "ABCDEF123456",
    "correlation_id": "corr-dist-001"
  }
}
```

O retorno e parseado para `last_nsu`, `max_nsu` e documentos distribuidos. A camada inclui descompactacao GZip/Base64 para `docZip`.

## Recepcao de Evento

Comandos:

- `manifest_acknowledgement`
- `manifest_confirmation`
- `manifest_unknown`
- `manifest_not_performed`

Exemplo:

```json
{
  "type": "manifest_not_performed",
  "payload": {
    "uf": "SP",
    "environment": "homologation",
    "cnpj": "00000000000000",
    "access_key": "00000000000000000000000000000000000000000000",
    "justification": "Operacao nao realizada por motivo operacional documentado.",
    "certificate_thumbprint": "ABCDEF123456",
    "correlation_id": "corr-event-001"
  }
}
```

## Eventos

| Evento | Codigo | Conclusivo | Observacao |
| --- | ---: | --- | --- |
| Ciencia da Operacao | 210210 | Nao | Aceite move documento para `pending_final_manifestation`. |
| Confirmacao da Operacao | 210200 | Sim | Exige confirmacao explicita ou regra administrativa. |
| Desconhecimento da Operacao | 210220 | Sim | Exige confirmacao explicita ou regra administrativa. |
| Operacao Nao Realizada | 210240 | Sim | Exige justificativa. |

## Ambientes

Ambientes modelados no agente:

- `production`
- `homologation`

O endpoint resolver resolve `NFeDistribuicaoDFe` via Ambiente Nacional e `NFeRecepcaoEvento` por UF, com fallback para Ambiente Nacional quando configurado.

## Tratamento de Erros

- Erro tecnico no agente retorna `fail` com `error_code` e `error_message`.
- Rejeicao SEFAZ reportada em `complete` nao e tratada como sucesso fiscal pela API; a maquina de estados marca `rejected`.
- Status aceitos atualmente como evento registrado: `135`, `136`, `155`.
- Timeout, erro de certificado, erro de assinatura ou erro de schema nao devem alterar manifestacao como sucesso.

## TODO

- Persistir XML completo baixado no storage central apos retorno do agente.
- Completar tratamento por `cStat` especifico, incluindo duplicidade de evento e eventos ja vinculados.
- Completar controle operacional de NSU por empresa.
- Validar lote de eventos conforme limite oficial vigente.
