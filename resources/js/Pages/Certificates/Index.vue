<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Agent, AgentCertificate, CompanyCertificate, Paginated, SefazConnectivityTest } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  agents: Agent[];
  agentCertificates: Paginated<AgentCertificate>;
  companyCertificates: Paginated<CompanyCertificate>;
  sefazConnectivityTests: SefazConnectivityTest[];
}>();

const a1Open = ref(false);
const a3Open = ref(false);
const selectedAgentCertificate = ref<AgentCertificate | null>(null);

const a1Form = useForm<{
  name: string;
  certificate_file: File | null;
  password: string;
}>({
  name: '',
  certificate_file: null,
  password: '',
});

const a3Form = useForm({
  agent_certificate_id: '',
  name: '',
});

const onlineAgents = computed(() => props.agents.filter((agent) => agent.status === 'online'));

function requestInventory(agent: Agent): void {
  router.post(`/certificates/agent/${agent.id}/list`, {}, { preserveScroll: true });
}

function openA3Link(certificate: AgentCertificate): void {
  selectedAgentCertificate.value = certificate;
  a3Form.reset();
  a3Form.agent_certificate_id = String(certificate.id);
  a3Form.name = certificate.subject ?? certificate.subject_name ?? '';
  a3Open.value = true;
}

function linkA3(): void {
  a3Form.post('/certificates/a3/link', {
    preserveScroll: true,
    onSuccess: () => {
      a3Open.value = false;
      selectedAgentCertificate.value = null;
      a3Form.reset();
    },
  });
}

function uploadA1(): void {
  a1Form.post('/certificates/a1', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      a1Open.value = false;
      a1Form.reset();
    },
  });
}

function testCertificate(certificate: CompanyCertificate): void {
  router.post(`/certificates/${certificate.id}/test`, {}, { preserveScroll: true });
}

function testSefazConnectivity(
  certificate: CompanyCertificate,
  mode: 'configuration_only' | 'live_homologation',
): void {
  router.post(`/certificates/${certificate.id}/test-sefaz-connectivity`, { mode }, { preserveScroll: true });
}

function testAgentCertificate(certificate: AgentCertificate): void {
  router.post(`/certificates/agent-certificate/${certificate.id}/test`, {}, { preserveScroll: true });
}

function setA1File(event: Event): void {
  const input = event.target as HTMLInputElement;
  a1Form.certificate_file = input.files?.[0] ?? null;
}

function canTestAgentCertificate(certificate: AgentCertificate): boolean {
  return Boolean(certificate.thumbprint && certificate.has_private_key && certificate.agent_id);
}

function typeLabel(type: string): string {
  return type === 'a1' ? 'A1 em nuvem' : 'A3 via agente';
}

function testStatusLabel(status: string | null): string {
  if (status === 'valid') {
    return 'Válido';
  }
  if (status === 'invalid') {
    return 'Inválido';
  }
  if (status === 'failed') {
    return 'Falhou';
  }

  return 'Não testado';
}

function canTestSefazConnectivity(certificate: CompanyCertificate): boolean {
  return certificate.type === 'a3' && Boolean(certificate.agent_id && certificate.thumbprint);
}

function canRunLiveSefazTest(certificate: CompanyCertificate): boolean {
  return canTestSefazConnectivity(certificate) && certificate.last_test_status === 'valid';
}

function connectivityModeLabel(mode: string): string {
  return mode === 'live_homologation' ? 'HomologaÃ§Ã£o real' : 'ConfiguraÃ§Ã£o apenas';
}

function connectivityStatusLabel(status: string): string {
  if (status === 'success') {
    return 'Sucesso';
  }
  if (status === 'failed') {
    return 'Falhou';
  }
  if (status === 'processing') {
    return 'Processando';
  }

  return 'Pendente';
}

function storeScopeLabel(scope: string | null): string {
  if (scope === 'CurrentUser' || scope === 'current_user') {
    return 'Usuário atual';
  }
  if (scope === 'LocalMachine' || scope === 'local_machine') {
    return 'Máquina local';
  }

  return '-';
}
</script>

<template>
  <Head title="Certificados" />
  <AppLayout title="Certificados" subtitle="Cadastro, vínculo e validação dos certificados da empresa selecionada.">
    <div class="section-title">
      <h2>Certificados vinculados</h2>
      <button class="button primary" type="button" @click="a1Open = true">Cadastrar A1</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Nome</th>
            <th>Validade</th>
            <th>Status</th>
            <th>Último teste</th>
            <th>Agente</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="certificate in props.companyCertificates.data" :key="certificate.uuid">
            <td>{{ certificate.company?.legal_name ?? '-' }}</td>
            <td>{{ typeLabel(certificate.type) }}</td>
            <td>
              <strong>{{ certificate.name ?? '-' }}</strong>
              <div class="muted mono">{{ certificate.thumbprint ?? '-' }}</div>
            </td>
            <td>{{ certificate.valid_until ?? '-' }}</td>
            <td><StatusBadge :status="certificate.status" /></td>
            <td>
              <span>{{ testStatusLabel(certificate.last_test_status) }}</span>
              <div class="muted">{{ certificate.last_tested_at ?? '-' }}</div>
              <div v-if="certificate.last_test_message" class="muted">{{ certificate.last_test_message }}</div>
            </td>
            <td>{{ certificate.agent?.name ?? '-' }}</td>
            <td>
              <div class="row-actions">
                <button
                  class="button"
                  type="button"
                  :disabled="!certificate.thumbprint"
                  @click="testCertificate(certificate)"
                >
                  Testar
                </button>
                <button
                  class="button"
                  type="button"
                  :disabled="!canTestSefazConnectivity(certificate)"
                  @click="testSefazConnectivity(certificate, 'configuration_only')"
                >
                  Testar SEFAZ
                </button>
                <button
                  class="button"
                  type="button"
                  :disabled="!canRunLiveSefazTest(certificate)"
                  @click="testSefazConnectivity(certificate, 'live_homologation')"
                >
                  HomologaÃ§Ã£o real
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="props.companyCertificates.data.length === 0">
            <td colspan="8" class="muted">Nenhum certificado vinculado para a empresa selecionada.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.companyCertificates.links" />

    <section class="panel inventory-actions">
      <div class="section-title">
        <h2>Conectividade SEFAZ</h2>
      </div>
      <p class="muted">
        O modo configuraÃ§Ã£o apenas nÃ£o chama a SEFAZ. HomologaÃ§Ã£o real deve ser solicitada explicitamente e nÃ£o
        manifesta notas.
      </p>
    </section>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Modo</th>
            <th>Ambiente</th>
            <th>UF</th>
            <th>Status</th>
            <th>Endpoint</th>
            <th>Mensagem</th>
            <th>DuraÃ§Ã£o</th>
            <th>Data/hora</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="test in props.sefazConnectivityTests" :key="test.uuid">
            <td>{{ connectivityModeLabel(test.mode) }}</td>
            <td>{{ test.environment }}</td>
            <td>{{ test.uf }}</td>
            <td><StatusBadge :status="test.status" /> {{ connectivityStatusLabel(test.status) }}</td>
            <td class="mono">{{ test.endpoint ?? '-' }}</td>
            <td>{{ test.sefaz_message ?? test.error_message ?? '-' }}</td>
            <td>{{ test.duration_ms !== null ? `${test.duration_ms} ms` : '-' }}</td>
            <td>{{ test.completed_at ?? test.requested_at }}</td>
          </tr>
          <tr v-if="props.sefazConnectivityTests.length === 0">
            <td colspan="8" class="muted">Nenhum teste de conectividade SEFAZ executado para a empresa selecionada.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <section class="panel inventory-actions">
      <div class="section-title">
        <h2>Inventário A3 dos agentes</h2>
      </div>
      <div class="agent-buttons">
        <button
          v-for="agent in onlineAgents"
          :key="agent.id"
          class="button"
          type="button"
          @click="requestInventory(agent)"
        >
          Listar certificados do agente {{ agent.name }}
        </button>
        <span v-if="onlineAgents.length === 0" class="muted">
          Nenhum agente online para listar certificados na empresa selecionada.
        </span>
      </div>
    </section>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Certificado A3</th>
            <th>CNPJ</th>
            <th>Origem</th>
            <th>Validade</th>
            <th>Chave privada</th>
            <th>Validação</th>
            <th>Último teste</th>
            <th>Agente</th>
            <th>Última visualização</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="certificate in props.agentCertificates.data" :key="certificate.uuid">
            <td>
              <strong>{{ certificate.subject ?? certificate.subject_name ?? '-' }}</strong>
              <div class="muted">{{ certificate.issuer ?? certificate.issuer_name ?? '-' }}</div>
              <div class="muted mono">{{ certificate.thumbprint }}</div>
            </td>
            <td class="mono">{{ certificate.cnpj ?? '-' }}</td>
            <td>{{ storeScopeLabel(certificate.store_location ?? certificate.store_scope) }}</td>
            <td>{{ certificate.not_after ?? certificate.valid_until ?? '-' }}</td>
            <td>{{ certificate.has_private_key ? 'Sim' : 'Não' }}</td>
            <td>
              <StatusBadge :status="certificate.is_valid ? 'active' : certificate.is_expired ? 'expired' : 'invalid'" />
              <div v-if="certificate.validation_message" class="muted">{{ certificate.validation_message }}</div>
            </td>
            <td>
              <span>{{ testStatusLabel(certificate.last_test_status) }}</span>
              <div class="muted">{{ certificate.last_tested_at ?? '-' }}</div>
              <div v-if="certificate.last_test_message" class="muted">{{ certificate.last_test_message }}</div>
            </td>
            <td>{{ certificate.agent?.name ?? '-' }}</td>
            <td>{{ certificate.last_seen_at ?? '-' }}</td>
            <td>
              <div class="row-actions">
                <button
                  class="button"
                  type="button"
                  :disabled="!canTestAgentCertificate(certificate)"
                  @click="testAgentCertificate(certificate)"
                >
                  Testar
                </button>
                <button class="button" type="button" @click="openA3Link(certificate)">Vincular</button>
              </div>
            </td>
          </tr>
          <tr v-if="props.agentCertificates.data.length === 0">
            <td colspan="10" class="muted">Nenhum certificado A3 informado por agente para a empresa selecionada.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.agentCertificates.links" />

    <Modal :open="a1Open" title="Cadastrar certificado A1" @close="a1Open = false">
      <form class="grid cols-2" @submit.prevent="uploadA1">
        <FormField label="Nome de exibição" :error="a1Form.errors.name">
          <input v-model="a1Form.name" class="input" />
        </FormField>
        <FormField label="Arquivo PFX/P12" :error="a1Form.errors.certificate_file" required>
          <input class="input" type="file" accept=".pfx,.p12" @change="setA1File" />
        </FormField>
        <FormField label="Senha" :error="a1Form.errors.password" required>
          <input v-model="a1Form.password" class="input" type="password" autocomplete="new-password" />
        </FormField>
        <div class="modal-copy">
          O certificado A1 será vinculado à empresa selecionada no topo da aplicação. A senha é usada para validar o
          certificado e armazenada criptografada. Nunca informe PIN de certificado A3 nesta tela.
        </div>
        <div class="actions">
          <button class="button" type="button" @click="a1Open = false">Cancelar</button>
          <button class="button primary" type="submit" :disabled="a1Form.processing">Salvar A1</button>
        </div>
      </form>
    </Modal>

    <Modal :open="a3Open" title="Vincular certificado A3" @close="a3Open = false">
      <form class="grid" @submit.prevent="linkA3">
        <div v-if="selectedAgentCertificate" class="selected-certificate">
          <strong>{{
            selectedAgentCertificate.subject ?? selectedAgentCertificate.subject_name ?? 'Certificado selecionado'
          }}</strong>
          <span class="mono">{{ selectedAgentCertificate.thumbprint }}</span>
        </div>
        <FormField label="Nome de exibição" :error="a3Form.errors.name">
          <input v-model="a3Form.name" class="input" />
        </FormField>
        <div class="modal-copy">O vínculo será criado para a empresa selecionada no topo da aplicação.</div>
        <div class="actions">
          <button class="button" type="button" @click="a3Open = false">Cancelar</button>
          <button class="button primary" type="submit" :disabled="a3Form.processing">Vincular A3</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<style scoped>
.inventory-actions {
  margin: 20px 0;
}

.agent-buttons,
.actions,
.row-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.actions {
  grid-column: 1 / -1;
  justify-content: flex-end;
}

.modal-copy,
.selected-certificate {
  background: #f1f4f0;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: #41504a;
  display: grid;
  gap: 6px;
  grid-column: 1 / -1;
  padding: 12px;
}
</style>
