<script setup lang="ts">
import AppLayout from '@/Components/Layout/AppLayout.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Agent, Company, Paginated } from '@/types/models';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  agents: Paginated<Agent>;
  latestActivations: Array<{ id: number; expires_at: string | null; company?: Company | null }>;
  agentConfig: {
    heartbeat_timeout_seconds: number;
    minimum_supported_version: string | null;
    installer_download_url: string | null;
    installer_download_available: boolean;
    installer_download_label: string;
    installer_status_message: string;
    installer_version: string | null;
    installer_sha256: string | null;
    local_diagnostics_port: number;
    activation_code_ttl_minutes: number;
    show_advanced_install_commands: boolean;
    install_command: string;
    dev_command: string;
  };
}>();

const page = usePage();
const activationOpen = ref(false);
const activationForm = useForm({});

const activationCode = computed(() => {
  const flash = page.props.flash as
    | { activationCode?: string | { code: string; expires_at?: string | null } }
    | undefined;
  const value = flash?.activationCode;

  if (!value) {
    return null;
  }

  return typeof value === 'string' ? value : value.code;
});

const activationExpiresAt = computed(() => {
  const flash = page.props.flash as
    | { activationCode?: string | { code: string; expires_at?: string | null } }
    | undefined;
  const value = flash?.activationCode;

  return typeof value === 'string' ? null : (value?.expires_at ?? null);
});

function generateActivationCode(): void {
  activationForm.post('/agents/activation-code', {
    preserveScroll: true,
    onSuccess: () => {
      activationOpen.value = false;
    },
  });
}

function revoke(agent: Agent): void {
  if (!window.confirm('Revogar este agente? Ele nao podera buscar novos comandos.')) {
    return;
  }

  router.post(`/agents/${agent.id}/revoke`, {}, { preserveScroll: true });
}

function listCertificates(agent: Agent): void {
  router.post(`/certificates/agent/${agent.id}/list`, {}, { preserveScroll: true });
}

function requestDiagnostics(agent: Agent): void {
  if (!agent.can_request_diagnostics) {
    window.alert('O agente esta offline. Nao e possivel enviar comandos ate que o servico local esteja em execucao.');
    return;
  }

  router.post(`/agents/${agent.id}/diagnostics/request`, {}, { preserveScroll: true });
}

async function copyActivationCode(): Promise<void> {
  if (activationCode.value) {
    await navigator.clipboard.writeText(activationCode.value);
  }
}

async function copyInstallCommand(): Promise<void> {
  await navigator.clipboard.writeText(props.agentConfig.install_command);
}
</script>

<template>
  <Head title="Empresa - Agentes" />
  <AppLayout title="Empresa" subtitle="Dados, certificados e agentes da empresa selecionada." show-subtitle>
    <CompanyTabs active="agents" />
    <section class="panel install-panel">
      <div class="install-header">
        <div>
          <h2>Instalacao do Agent Windows</h2>
          <p class="muted">Ative a maquina local com um instalador Windows e acompanhe o status nesta tela.</p>
        </div>
        <div class="install-actions">
          <button class="button primary" type="button" @click="activationOpen = true">Gerar codigo de ativacao</button>
          <a
            v-if="props.agentConfig.installer_download_available && props.agentConfig.installer_download_url"
            class="button"
            :href="props.agentConfig.installer_download_url"
            download
          >
            {{ props.agentConfig.installer_download_label }}
          </a>
          <button v-else class="button" type="button" disabled title="Instalador ainda nao configurado neste ambiente.">
            {{ props.agentConfig.installer_download_label }}
          </button>
        </div>
      </div>

      <div class="installer-status" :class="{ warning: !props.agentConfig.installer_download_available }">
        <strong>{{
          props.agentConfig.installer_download_available ? 'Download disponivel' : 'Download indisponivel'
        }}</strong>
        <span>{{ props.agentConfig.installer_status_message }}</span>
      </div>

      <dl v-if="props.agentConfig.installer_download_available" class="installer-metadata">
        <div>
          <dt>Versao</dt>
          <dd>{{ props.agentConfig.installer_version ?? 'Nao informada' }}</dd>
        </div>
        <div>
          <dt>SHA-256</dt>
          <dd>{{ props.agentConfig.installer_sha256 ?? 'Nao informado' }}</dd>
        </div>
      </dl>

      <div v-if="activationCode" class="activation-code-card">
        <div>
          <span class="label">Codigo de ativacao</span>
          <strong class="activation-code">{{ activationCode }}</strong>
          <p v-if="activationExpiresAt" class="muted">Valido ate {{ activationExpiresAt }}</p>
        </div>
        <button class="button" type="button" @click="copyActivationCode">Copiar codigo</button>
      </div>

      <div class="install-content">
        <div>
          <h3>Fluxo recomendado</h3>
          <ol class="steps">
            <li><strong>Gere o codigo</strong><span>Ele sera exibido uma unica vez nesta tela.</span></li>
            <li>
              <strong>Baixe o instalador</strong><span>Use o MSI/EXE oficial configurado para este ambiente.</span>
            </li>
            <li><strong>Execute o instalador</strong><span>Abra o arquivo baixado no Windows.</span></li>
            <li>
              <strong>Informe o codigo no instalador</strong><span>O assistente ativara o Agent junto a API.</span>
            </li>
            <li>
              <strong>Aguarde o status Online</strong
              ><span>A Web detecta o heartbeat depois que o servico inicia.</span>
            </li>
          </ol>
        </div>

        <aside class="install-side">
          <h3>O que acontece no Windows</h3>
          <ul>
            <li>O instalador abre o assistente local.</li>
            <li>O assistente testa conexao com a API.</li>
            <li>O Agent salva credenciais com DPAPI.</li>
            <li>O servico local envia heartbeat para a Web.</li>
          </ul>
        </aside>
      </div>

      <div class="browser-security-note">
        Por seguranca, o navegador nao pode instalar ou iniciar o Agent automaticamente. Apos baixar, execute o
        instalador no Windows.
      </div>

      <p class="muted">
        Em producao, o instalador abrira o Assistente de Configuracao do MWS Agent para informar o codigo de ativacao,
        testar a conexao e iniciar o servico.
      </p>

      <details class="advanced-install">
        <summary>Instalacao avancada / suporte tecnico</summary>
        <div v-if="props.agentConfig.show_advanced_install_commands" class="advanced-body">
          <p class="muted">Execute o PowerShell como administrador. Nao informe PIN de A3 neste comando.</p>
          <div class="command-grid">
            <div>
              <span class="label">PowerShell</span>
              <code>{{ props.agentConfig.install_command }}</code>
            </div>
            <div>
              <span class="label">Desenvolvimento</span>
              <code>{{ props.agentConfig.dev_command }}</code>
            </div>
          </div>
          <button class="button" type="button" @click="copyInstallCommand">Copiar comando de suporte</button>
        </div>
        <p v-else class="muted advanced-body">Comandos avancados desabilitados neste ambiente.</p>
      </details>
    </section>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Status</th>
            <th>Versao</th>
            <th>Ultimo heartbeat</th>
            <th>Empresa</th>
            <th>Instalacao</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="agent in props.agents.data" :key="agent.uuid">
            <td>
              <strong>{{ agent.name }}</strong>
              <div class="muted">{{ agent.machine_name ?? 'Maquina nao informada' }}</div>
            </td>
            <td><StatusBadge :status="agent.operational_status ?? agent.status" /></td>
            <td>{{ agent.version ?? '-' }}</td>
            <td>{{ agent.last_seen_at ?? '-' }}</td>
            <td>{{ agent.company?.legal_name ?? 'Sem vinculo' }}</td>
            <td>
              <span class="mono">{{ agent.installation_id ?? '-' }}</span>
            </td>
            <td class="row-actions">
              <button class="button" type="button" @click="requestDiagnostics(agent)">Solicitar diagnostico</button>
              <Link class="button" :href="`/agents/${agent.id}/diagnostics`">Historico</Link>
              <button class="button" type="button" @click="listCertificates(agent)">Listar certificados</button>
              <button class="button danger" type="button" @click="revoke(agent)">Revogar</button>
            </td>
          </tr>
          <tr v-if="props.agents.data.length === 0">
            <td colspan="7" class="muted">Nenhum agente ativado para a empresa selecionada.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.agents.links" />

    <section class="panel activations">
      <h2>Codigos recentes</h2>
      <div v-if="props.latestActivations.length === 0" class="muted">Nenhum codigo emitido recentemente.</div>
      <div v-for="activation in props.latestActivations" :key="activation.id" class="activation-row">
        <span>{{ activation.company?.legal_name ?? 'Sem empresa vinculada' }}</span>
        <strong>Expira em {{ activation.expires_at ?? '-' }}</strong>
      </div>
    </section>

    <Modal :open="activationOpen" title="Gerar codigo de ativacao" @close="activationOpen = false">
      <form class="grid" @submit.prevent="generateActivationCode">
        <div class="modal-copy">
          O codigo sera gerado para a empresa selecionada no topo da aplicacao. Ele sera exibido uma unica vez e deve
          ser informado no Agent local durante a ativacao.
        </div>
        <div class="actions">
          <button class="button" type="button" @click="activationOpen = false">Cancelar</button>
          <button class="button primary" type="submit" :disabled="activationForm.processing">Gerar</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<style scoped>
.row-actions,
.actions,
.install-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.actions,
.install-actions {
  justify-content: flex-end;
}

.install-panel {
  margin-bottom: 18px;
}

.install-header {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.activation-code-card {
  align-items: center;
  background: #eef7f4;
  border: 1px solid #b9dfcc;
  border-radius: 8px;
  display: flex;
  gap: 14px;
  justify-content: space-between;
  margin-top: 16px;
  padding: 14px;
}

.activation-code {
  display: block;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
  font-size: 26px;
  letter-spacing: 0;
  margin-top: 4px;
}

.installer-status {
  align-items: flex-start;
  background: #eef7f4;
  border: 1px solid #b9dfcc;
  border-radius: 8px;
  display: grid;
  gap: 3px;
  margin-top: 16px;
  padding: 12px;
}

.installer-status.warning {
  background: #fff7ed;
  border-color: #fed7aa;
  color: #9a3412;
}

.installer-status span {
  color: var(--muted);
}

.installer-status.warning span {
  color: #9a3412;
}

.installer-metadata {
  display: grid;
  gap: 8px;
  grid-template-columns: minmax(140px, 180px) minmax(0, 1fr);
  margin: 14px 0 0;
}

.installer-metadata div {
  background: #f7f7f5;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
}

.installer-metadata dt {
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 4px;
  text-transform: uppercase;
}

.installer-metadata dd {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
  margin: 0;
  overflow-wrap: anywhere;
}

.install-content {
  display: grid;
  gap: 20px;
  grid-template-columns: minmax(0, 1fr) minmax(260px, 340px);
  margin-top: 16px;
}

.install-content h3 {
  font-size: 15px;
  margin: 0 0 10px;
}

.install-side {
  background: #f7f7f5;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px;
}

.install-side ul {
  color: #41504a;
  margin: 0;
  padding-left: 18px;
}

.install-side li {
  margin: 7px 0;
}

.steps {
  counter-reset: install-step;
  display: grid;
  gap: 10px;
  list-style: none;
  margin: 16px 0;
  padding: 0;
}

.steps li {
  align-items: flex-start;
  display: grid;
  gap: 2px;
  grid-template-columns: 34px minmax(0, 1fr);
}

.steps li::before {
  align-items: center;
  background: #256f5c;
  border-radius: 999px;
  color: #fff;
  content: counter(install-step);
  counter-increment: install-step;
  display: inline-flex;
  font-size: 13px;
  font-weight: 800;
  height: 26px;
  justify-content: center;
  margin-top: 1px;
  width: 26px;
}

.steps li strong,
.steps li span {
  grid-column: 2;
}

.steps li span {
  color: var(--muted);
}

.browser-security-note {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 6px;
  color: #9a3412;
  margin: 12px 0;
  padding: 12px;
}

.advanced-install {
  border-top: 1px solid var(--border);
  margin-top: 16px;
  padding-top: 12px;
}

.advanced-install summary {
  cursor: pointer;
  font-weight: 750;
}

.advanced-body {
  margin-top: 12px;
}

.command-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  margin: 12px 0;
}

.command-grid code,
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
}

.command-grid code {
  background: #f7f7f5;
  border: 1px solid var(--border);
  border-radius: 6px;
  display: block;
  margin-top: 4px;
  overflow-wrap: anywhere;
  padding: 10px;
}

.label {
  color: #6b756f;
  font-size: 12px;
  font-weight: 750;
  text-transform: uppercase;
}

.activations {
  margin-top: 18px;
}

.activation-row {
  align-items: center;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
}

.activation-row:first-of-type {
  border-top: 0;
}

.modal-copy {
  background: #f1f4f0;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: #41504a;
  padding: 12px;
}

@media (max-width: 720px) {
  .install-header,
  .activation-code-card {
    align-items: stretch;
    flex-direction: column;
  }

  .install-actions {
    justify-content: flex-start;
  }

  .install-content {
    grid-template-columns: 1fr;
  }
}
</style>
