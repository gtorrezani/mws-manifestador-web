# Agent Windows: instalacao e operacao

Este documento define o modelo profissional de instalacao, ativacao, execucao e controle operacional do MWS Manifestador NF-e Agent para Windows. Ele cobre somente operacao do Agent; nao altera regras fiscais nem fluxos SEFAZ.

## 1. Modelo operacional do Agent

O Agent usa Agent Pull Model: a Web/API cria comandos e o Agent local, ja instalado e em execucao, faz heartbeat e polling periodico para buscar trabalho. A conexao sempre sai da maquina do cliente para a API. A Web cloud/API nao abre conexao inbound para a rede do cliente.

A separacao de responsabilidades e:

- Web/API: cadastro de empresa, geracao de codigo de ativacao, persistencia de agentes, comandos, resultados, heartbeats, diagnosticos e exibicao operacional.
- Agent local: acesso ao Windows Certificate Store, tokens A3, certificados locais, execucao de comandos e comunicacao autenticada com a API.

Por isso o Agent precisa rodar localmente. Sem um processo local em execucao, a Web nao consegue acessar certificado A3, consultar o Windows Certificate Store, iniciar servico parado ou instalar software na maquina do cliente.

Ciclo operacional:

1. Instalacao: o usuario baixa o instalador ou executa script administrativo na maquina Windows.
2. Ativacao: a Web gera um codigo curto; o Agent chama `/api/agent/v1/activate` e recebe credenciais.
3. Heartbeat: o Agent envia status, versao, maquina e inventario resumido em intervalo configurado.
4. Polling: o Agent consulta a fila de comandos disponiveis.
5. Execucao: o Agent marca comando como iniciado, executa localmente e retorna `complete` ou `fail`.
6. Atualizacao: a Web pode solicitar update apenas se o Agent estiver online e fazendo polling.
7. Diagnostico: a Web pode solicitar diagnostico remoto via comando; o Agent tambem pode expor diagnostico local restrito a `127.0.0.1`.

## 2. Estados do Agent

Estados operacionais recomendados:

- `not_installed`: nao ha Agent instalado ou nenhum registro foi criado. A Web so consegue inferir isso quando nao existe Agent ativado para a empresa.
- `pending_activation`: existe codigo de ativacao emitido ou Agent criado sem `activated_at`. Depende de cadastro/ativacao na Web.
- `online`: `last_seen_at` esta dentro do timeout de heartbeat configurado.
- `offline`: `last_seen_at` passou do timeout, ou nunca houve heartbeat apos ativacao.
- `outdated`: versao instalada e menor que `minimum_supported_version`.
- `revoked`: `revoked_at` preenchido ou status persistido revogado.
- `error`: Agent informou erro como status persistido.
- `service_stopped`: estado local informado por componente local, service manager ou watchdog. A Web nao detecta automaticamente se o servico esta parado; ela infere offline por timeout.
- `unknown`: dados insuficientes para classificar.

Detectaveis automaticamente pela Web: `pending_activation`, `online`, `offline`, `outdated`, `revoked`, `error` e `unknown`, usando dados persistidos. `not_installed` depende da ausencia de registro. `service_stopped` depende de informacao local enviada pelo Agent, watchdog ou diagnostico local.

## 3. O que a Web pode controlar

A Web pode:

- gerar codigo de ativacao;
- exibir link/download do instalador;
- listar versao instalada;
- exibir ultimo heartbeat;
- detectar offline por timeout;
- criar comando para listar certificados;
- criar comando para testar certificado;
- criar comando para diagnostico;
- criar comando para solicitar restart/update, se o Agent estiver online;
- revogar agente.

A Web nao pode:

- instalar o Agent sozinha em uma maquina nova;
- executar automaticamente um EXE/MSI no computador do usuario pelo navegador;
- iniciar um Windows Service parado se nao houver nenhum componente local rodando;
- resolver permissao local sem acao do usuario ou administrador;
- acessar token A3 diretamente;
- atravessar firewall, NAT ou politica corporativa para controlar a maquina do cliente.

Comandos remotos so funcionam quando o Agent esta online e fazendo polling. Se ele estiver offline, a Web apenas registra orientacao operacional para o usuario iniciar o servico localmente.

O botao de download da Web baixa apenas instalador real `.msi` ou `.exe`, seja por URL externa configurada, seja por arquivo local no storage privado. Arquivos `.txt`, `.md`, `.zip` e scripts `.ps1` nao sao aceitos como instalador principal. Por seguranca do navegador, a Web nao deve tentar abrir ou executar o instalador automaticamente. Um protocolo customizado como `mws-agent://` so pode ser usado no futuro depois que um instalador assinado registrar esse protocolo no Windows.

## 4. Opcoes de instalacao

Alternativas suportadas ou recomendadas:

- Instalacao manual com MSI/WiX: melhor experiencia para usuario final e padrao atual.
- Script PowerShell administrativo: util para suporte, homologacao e instalacao assistida.
- Instalacao assistida baixando pacote da Web: a Web fornece link do instalador e comando de ativacao.
- GPO/Intune: recomendado para empresas maiores que distribuem software centralmente.
- Watchdog Service opcional: segundo servico local pequeno para monitorar e reiniciar o Agent principal.

Recomendacao:

- Curto prazo: instalador WiX/MSI com Worker Service, Configurator WPF e Tray Monitor; scripts PowerShell ficam somente para suporte.
- Medio prazo: auto-update controlado, assinado e com rollback.
- Futuro: Watchdog local separado para recuperar quedas do servico principal.

## 5. Ativacao

Fluxo recomendado:

1. Usuario cria ou solicita Agent na Web.
2. Web gera activation code curto e com TTL.
3. Usuario instala o Agent.
4. Configurator local recebe o activation code.
5. Agent chama `/api/agent/v1/activate` com activation code, `installation_id`, `machine_name`, versao e inventario seguro.
6. API valida o codigo e emite credenciais do Agent.
7. Agent salva credenciais com DPAPI.
8. Agent inicia heartbeat e polling.

O activation code nao substitui as credenciais do Agent. Ele deve ser temporario, exibido uma unica vez e nunca versionado. O Agent nao deve gravar PIN de A3, segredo HMAC em texto puro ou private key.

O MSI nao embute activation code. O Configurator usa o codigo uma vez, chama a API e grava as credenciais retornadas com DPAPI em `%ProgramData%\MWS Manifestador Agent\agent-credentials.dpapi`.

## 6. Execucao como Windows Service

Em producao, o Agent deve rodar como Windows Service com inicializacao automatica. Em desenvolvimento, console mode e mais simples para debugar ativacao, store de certificados e prompts de token A3.

A instalação local deve deixar pontos visíveis para o usuário:

- atalho `MWS Agent Configurator` no Menu Iniciar;
- atalho `MWS Agent Tray Monitor` no Menu Iniciar;
- atalho `MWS Agent Logs` para abrir a pasta de logs;
- ícone de bandeja quando o Tray Monitor estiver em execução.

O Tray Monitor roda no contexto do usuário logado e não substitui o Windows Service. Sair do Tray fecha apenas a camada visual. Ações de iniciar, parar ou reiniciar o serviço podem exigir permissão administrativa.

Se a Web não mostrar Online após a instalação, o usuário deve abrir o Configurator ou o ícone de bandeja, confirmar API/ativação e verificar se o serviço `MWSManifestadorAgent` está rodando.

Conta de servico:

- `LocalSystem`: simples para instalar, mas pode nao enxergar certificados em `CurrentUser` do usuario real.
- `NetworkService`: menor privilegio local, mas tambem pode nao enxergar certificados em `CurrentUser`.
- Usuario dedicado ou usuario interativo: pode acessar certificados A3 em `CurrentUser`, porem exige gestao de senha, logon rights e politica de seguranca.

Certificados A3 frequentemente dependem do contexto do usuario, driver/token e UI de PIN. Se o certificado esta em `CurrentUser`, o servico rodando como `LocalSystem` pode nao encontra-lo. Para A3, validar em console no mesmo usuario costuma ser o primeiro passo de diagnostico.

## 7. Controle via Web

Comandos operacionais futuros recomendados:

- `agent_restart_requested`
- `agent_update_requested`
- `agent_diagnostics_requested`
- `agent_collect_logs_requested`
- `agent_refresh_certificate_inventory`

Todos dependem do Agent online e fazendo polling. A Web pode criar o comando; quem executa e o componente local. Se o Agent estiver offline, a fila pode receber comandos futuros em alguns casos, mas comandos de controle operacional devem ser bloqueados ou sinalizados claramente para evitar falsa expectativa.

## 8. Watchdog opcional

Um Watchdog seria um segundo servico local pequeno com responsabilidade limitada:

- verificar se o Agent principal esta rodando;
- reiniciar o Agent se ele travar;
- aplicar update local autorizado;
- enviar diagnostico local minimo.

A Web ainda nao inicia nada diretamente. Ela pode apenas pedir ao Agent ou Watchdog online que execute uma acao. Se Agent e Watchdog estiverem parados, e necessaria acao local do usuario, suporte remoto autorizado, GPO/Intune ou ferramenta corporativa equivalente.

## Parametros de configuracao Web

`config/agent.php` centraliza parametros operacionais:

- `heartbeat_timeout_seconds`: janela para considerar o Agent online.
- `minimum_supported_version`: versao minima aceita antes de mostrar `outdated`.
- `installer_download_url`: link exibido na UI.
- `installer_local_disk`: disk usado para arquivo local do instalador.
- `installer_local_path`: caminho local relativo no disk configurado.
- `installer_file_name`: nome enviado no download.
- `installer_version`: versao exibida na UI.
- `installer_sha256`: checksum exibido na UI.
- `local_diagnostics_port`: porta esperada do diagnostico local.
- `activation_code_ttl_minutes`: validade do activation code.

Esses parametros devem ser ajustados por ambiente e nunca devem conter segredos.

Para publicar localmente o MSI gerado pelo repositorio do Agent:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\publish-local-agent-installer.ps1 `
  -InstallerPath C:\Git\mws-manifestador-agent\artifacts\installer\MWS-Manifestador-Agent-Setup.msi
```

O script copia o MSI para `storage/app/private/installers`, calcula SHA-256 e imprime as variaveis `.env` necessarias.
