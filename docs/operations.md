# Operacao

## Instalacao do Agente

O agente e um .NET Worker Service para Windows Service.

Fluxo esperado:

1. Instalar o pacote do agente no Windows do cliente.
2. Configurar URL da API central.
3. Executar ativacao com codigo temporario gerado na Web.
4. Validar heartbeat na tela de Agentes.
5. Executar diagnostico de certificado e conectividade SEFAZ.

TODO:

- Definir instalador MSI/PowerShell final.
- Definir politica de auto-update.

## Ativacao

Na Web, gerar codigo de ativacao para uma empresa. No agente, informar o codigo e a URL da API.

Exemplo de payload enviado pelo agente:

```json
{
  "activation_code": "123456",
  "installation_id": "install-001",
  "machine_name": "CLIENTE-FINANCEIRO",
  "version": "1.0.0"
}
```

Depois da ativacao, o agente recebe `agent_id` e `secret`. O secret deve ser guardado localmente com protecao do Windows.

## Diagnostico

Diagnosticos devem verificar:

- acesso ao Windows Certificate Store;
- certificado selecionado por empresa;
- validade do certificado;
- presenca de chave privada;
- conectividade HTTPS com API central;
- conectividade com endpoint SEFAZ;
- permissao de escrita em diretorio temporario local.

Exemplo:

```json
{
  "status": "degraded",
  "checks": [
    {
      "name": "certificate_store",
      "status": "healthy"
    },
    {
      "name": "sefaz_connectivity",
      "status": "degraded",
      "message": "Timeout ao conectar no endpoint configurado."
    }
  ]
}
```

## Atualizacao

Estado atual: versao do agente e reportada no heartbeat.

TODO:

- Definir canal de atualizacao.
- Definir versao minima suportada.
- Bloquear comandos incompativeis por capability/version.

## Logs

Logs locais devem ser estruturados e nao conter dados sensiveis. Upload para API usa `/api/agent/v1/logs`.

Exemplo:

```json
{
  "entries": [
    {
      "level": "warning",
      "message": "Certificate token unavailable.",
      "context": {
        "company_id": 10,
        "correlation_id": "corr-001"
      },
      "occurred_at": "2026-05-14T12:00:00Z"
    }
  ]
}
```

## Troubleshooting

### Agente offline

Verificar:

- Windows Service em execucao;
- URL da API;
- proxy/firewall de saida HTTPS;
- relogio do Windows sincronizado;
- segredo HMAC valido;
- agente nao revogado.

### HMAC invalido

Verificar:

- `agent_id` correto;
- segredo local preservado;
- timestamp dentro da tolerancia;
- nonce novo por request;
- body hash calculado sobre o corpo exato enviado;
- path canonico sem dominio.

### Lock expirado

O lock expira se o agente demora alem de `MWS_AGENT_COMMAND_LOCK_SECONDS`. O comando pode voltar ao pool e ser reprocessado. O agente deve reportar `start`, `complete` ou `fail` dentro da janela.

### Conectividade SEFAZ

Verificar:

- DNS;
- TLS;
- proxy corporativo;
- endpoint por UF/ambiente;
- certificado cliente;
- disponibilidade do autorizador.

### Certificado vencido

O agente deve retornar erro claro. Operacao fiscal nao deve ser executada com certificado vencido.

### Token A3 ausente

Verificar:

- token conectado;
- driver instalado;
- certificado visivel no StoreName.My;
- permissao do servico Windows ao provider;
- interacao de PIN permitida pelo provedor.

### Usuario cancelou PIN

Tratar como erro tecnico de certificado. Nao reclassificar como rejeicao SEFAZ.

## Rotina Operacional Recomendada

- Monitorar dashboard diariamente.
- Verificar agentes offline.
- Acompanhar certificados vencendo.
- Investigar manifestacoes `failed` ou `rejected`.
- Reprocessar somente quando a regra fiscal permitir.
- Exportar XMLs por lote apenas apos confirmar filtros.

## TODO

- Criar runbook de incidentes por `error_code`.
- Criar matriz oficial de mensagens para suporte.
- Criar procedimento de revogacao e reativacao com evidencias de auditoria.
