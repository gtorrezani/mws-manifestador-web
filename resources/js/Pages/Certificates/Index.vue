<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type {
  Agent,
  AgentCertificate,
  Company,
  CompanyCertificate,
  Paginated,
  SefazConnectivityTest,
} from '@/types/models';
import { formatCpfCnpj, onlyDigits } from '@/utils/documents';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  agents: Agent[];
  agentCertificates: Paginated<AgentCertificate>;
  ignoredAgentCertificates: AgentCertificate[];
  companyCertificates: Paginated<CompanyCertificate>;
  sefazConnectivityTests: SefazConnectivityTest[];
}>();

const page = usePage();
const currentCompany = computed(() => (page.props.currentCompany ?? null) as Company | null);
const certificateMode = ref<'a1' | 'a3'>('a1');
const linkOpen = ref(false);
const showIgnored = ref(false);
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

const linkForm = useForm({
  agent_certificate_id: '',
  name: '',
});

const onlineAgents = computed(() =>
  props.agents.filter((agent) => (agent.operational_status ?? agent.status).toLowerCase() === 'online'),
);
const a1Certificates = computed(() =>
  props.companyCertificates.data.filter((certificate) => certificate.type === 'a1'),
);
const localCertificates = computed(() =>
  props.companyCertificates.data.filter((certificate) => certificate.type === 'a3'),
);
const modeTitle = computed(() => (certificateMode.value === 'a1' ? 'Certificado A1' : 'Certificado A3'));
const modeDescription = computed(() =>
  certificateMode.value === 'a1'
    ? 'Use A1 quando o certificado PFX/P12 ficará protegido no servidor. Este é o caminho padrão para a empresa.'
    : 'Use A3 quando a assinatura depender de token/cartão local acessado por um Agent instalado no computador.',
);
const agentProblem = computed(() => {
  if (props.agents.length === 0) {
    return 'Nenhum Agent está vinculado a esta empresa. Cadastre e ative um Agent antes de usar A3.';
  }

  if (onlineAgents.value.length === 0) {
    return 'Há Agent cadastrado, mas nenhum está online. Inicie o serviço no computador onde o certificado A3 está instalado.';
  }

  return null;
});

function requestInventory(agent: Agent, diagnostics = false): void {
  router.post(
    `/certificates/agent/${agent.id}/list`,
    diagnostics ? { include_rejected: true, include_expired: true } : {},
    { preserveScroll: true },
  );
}

function openLink(certificate: AgentCertificate): void {
  selectedAgentCertificate.value = certificate;
  linkForm.reset();
  linkForm.agent_certificate_id = String(certificate.id);
  linkForm.name = certificate.common_name ?? certificate.subject ?? certificate.subject_name ?? '';
  linkOpen.value = true;
}

function linkCertificate(): void {
  linkForm.post('/certificates/a3/link', {
    preserveScroll: true,
    onSuccess: () => {
      linkOpen.value = false;
      selectedAgentCertificate.value = null;
      linkForm.reset();
    },
  });
}

function uploadA1(): void {
  a1Form.post('/certificates/a1', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
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
  return Boolean(
    certificate.thumbprint &&
    certificate.has_private_key &&
    certificate.agent_id &&
    certificate.is_fiscal_candidate &&
    certificate.is_icp_brasil &&
    certificate.is_usable_for_client_auth &&
    !certificate.is_certificate_authority &&
    !certificate.is_expired,
  );
}

function canLinkCandidate(certificate: AgentCertificate): boolean {
  return Boolean(
    canTestAgentCertificate(certificate) &&
    certificate.is_icp_brasil &&
    !certificate.is_certificate_authority &&
    certificate.classification === 'fiscal_candidate' &&
    certificate.document_type === 'cnpj' &&
    onlyDigits(certificate.cnpj ?? certificate.document) === onlyDigits(currentCompany.value?.cnpj ?? null),
  );
}

function blockedLinkReason(certificate: AgentCertificate): string | null {
  if (canLinkCandidate(certificate)) {
    return null;
  }

  if (certificate.document_type !== 'cnpj') {
    return 'Somente e-CNPJ pode ser vinculado à empresa nesta tela.';
  }

  if (onlyDigits(certificate.cnpj ?? certificate.document) !== onlyDigits(currentCompany.value?.cnpj ?? null)) {
    return 'O CNPJ do certificado não confere com a empresa selecionada.';
  }

  return primaryReason(certificate);
}

function canTestLinkedCertificate(certificate: CompanyCertificate): boolean {
  return certificate.type === 'a1' || Boolean(certificate.thumbprint);
}

function canTestSefazConnectivity(certificate: CompanyCertificate): boolean {
  return certificate.type === 'a3' && Boolean(certificate.agent_id && certificate.thumbprint);
}

function canRunLiveSefazTest(certificate: CompanyCertificate): boolean {
  return canTestSefazConnectivity(certificate) && certificate.last_test_status === 'valid';
}

function linkedCertificateTitle(certificate: CompanyCertificate): string {
  if (certificate.name) {
    return certificate.type === 'a3' ? certificate.name.replace(/\bA3\b/gi, 'fiscal local') : certificate.name;
  }

  return certificate.type === 'a1' ? 'Certificado A1 armazenado' : 'Certificado fiscal local';
}

function certificateTitle(certificate: AgentCertificate): string {
  return certificate.common_name ?? certificate.subject ?? certificate.subject_name ?? 'Certificado local do Agent';
}

function certificateTypeLabel(certificate: CompanyCertificate): string {
  return certificate.type === 'a1' ? 'A1 no servidor' : 'A3 via Agent local';
}

function testStatusLabel(status: string | null): string {
  const labels: Record<string, string> = {
    failed: 'Falhou',
    invalid: 'Inválido',
    valid: 'Válido',
  };

  return labels[status ?? ''] ?? 'Não testado';
}

function connectivityModeLabel(mode: string): string {
  return mode === 'live_homologation' ? 'Homologação real' : 'Configuração';
}

function connectivityStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    failed: 'Falhou',
    processing: 'Processando',
    success: 'Sucesso',
  };

  return labels[status] ?? 'Pendente';
}

function storeScopeLabel(scope: string | null): string {
  if (scope === 'CurrentUser' || scope === 'current_user') {
    return 'Usuário atual';
  }

  if (scope === 'LocalMachine' || scope === 'local_machine') {
    return 'Máquina local';
  }

  return 'Origem não informada';
}

function formatDate(value: string | null): string {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value));
}

function formatDateTime(value: string | null): string {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value));
}

function formatDocument(value: string | null, type: string | null = 'cnpj'): string {
  if (!value) {
    return '-';
  }

  return formatCpfCnpj(value, type);
}

function primaryReason(certificate: AgentCertificate): string {
  if (certificate.rejection_reasons?.[0]) {
    return certificate.rejection_reasons[0];
  }

  if (certificate.is_expired) {
    return 'Certificado vencido.';
  }

  if (!certificate.has_private_key) {
    return 'Certificado sem chave privada.';
  }

  if (!certificate.is_icp_brasil) {
    return 'Emissor/cadeia não indica ICP-Brasil.';
  }

  return 'Inventário anterior sem classificação fiscal. Atualize pelo Agent.';
}

function classificationLabel(classification: string): string {
  const labels: Record<string, string> = {
    ca_certificate: 'Autoridade certificadora',
    expired_fiscal: 'Fiscal vencido',
    fiscal_candidate: 'Fiscal candidato',
    missing_private_key: 'Sem chave privada',
    system_certificate: 'Sistema',
    unknown: 'Não fiscal',
  };

  return labels[classification] ?? classification;
}
</script>

<template>
  <Head title="Empresa - Certificados" />
  <AppLayout title="Empresa" subtitle="Dados, certificados e agentes da empresa selecionada." show-subtitle>
    <CompanyTabs active="certificates" />

    <section class="mode-panel">
      <div class="mode-copy">
        <span class="eyebrow">Tipo de certificado</span>
        <h2>{{ modeTitle }}</h2>
        <p>{{ modeDescription }}</p>
      </div>
      <label class="mode-select">
        <span>O cliente vai usar</span>
        <select v-model="certificateMode" class="select">
          <option value="a1">A1 - arquivo no servidor</option>
          <option value="a3">A3 - token/cartão no computador</option>
        </select>
      </label>
    </section>

    <section v-if="certificateMode === 'a1'" class="page-grid a1-grid">
      <main class="main-stack">
        <section class="panel upload-panel">
          <div class="section-heading">
            <div>
              <span class="eyebrow">Padrão</span>
              <h2>Enviar certificado A1</h2>
            </div>
          </div>

          <form class="upload-form" @submit.prevent="uploadA1">
            <FormField label="Nome de exibição" :error="a1Form.errors.name">
              <input v-model="a1Form.name" class="input" placeholder="Ex.: Certificado fiscal da matriz" />
            </FormField>
            <FormField label="Arquivo PFX/P12" :error="a1Form.errors.certificate_file" required>
              <input class="input" type="file" accept=".pfx,.p12" @change="setA1File" />
            </FormField>
            <FormField label="Senha do arquivo" :error="a1Form.errors.password" required>
              <input v-model="a1Form.password" class="input" type="password" autocomplete="new-password" />
            </FormField>
            <div class="upload-note">
              O arquivo deve conter chave privada, CNPJ da empresa selecionada e cadeia fiscal ICP-Brasil. A senha é
              usada para validar e armazenar o A1 de forma protegida.
            </div>
            <div class="form-actions">
              <button class="button primary" type="submit" :disabled="a1Form.processing">Salvar A1</button>
            </div>
          </form>
        </section>

        <section>
          <div class="section-heading spaced">
            <div>
              <span class="eyebrow">Servidor</span>
              <h2>Certificados A1 cadastrados</h2>
            </div>
          </div>
          <div class="card-list">
            <article v-for="certificate in a1Certificates" :key="certificate.uuid" class="certificate-card">
              <div class="card-heading">
                <div>
                  <h3>{{ linkedCertificateTitle(certificate) }}</h3>
                  <span>{{ certificateTypeLabel(certificate) }}</span>
                </div>
                <StatusBadge :status="certificate.status" />
              </div>

              <div class="info-grid">
                <div>
                  <span>Validade</span>
                  <strong>{{ formatDate(certificate.valid_until) }}</strong>
                </div>
                <div>
                  <span>Último teste</span>
                  <strong>{{ testStatusLabel(certificate.last_test_status) }}</strong>
                </div>
                <div>
                  <span>Origem</span>
                  <strong>Upload no servidor</strong>
                </div>
              </div>

              <p v-if="certificate.last_test_message" class="subtle-message">{{ certificate.last_test_message }}</p>
              <p v-if="certificate.thumbprint" class="thumbprint">{{ certificate.thumbprint }}</p>

              <div class="card-actions">
                <button class="button" type="button" @click="testCertificate(certificate)">Testar</button>
              </div>
            </article>

            <div v-if="a1Certificates.length === 0" class="empty-state">
              Nenhum A1 foi enviado para o servidor nesta empresa.
            </div>
          </div>
        </section>
      </main>

      <aside class="side-stack">
        <section class="panel compact-panel">
          <span class="eyebrow">Importante</span>
          <h2>A1 fica no servidor</h2>
          <p class="diagnostic-copy">
            Esta lista não usa o Windows Store nem certificados do Agent. Certificados vencidos continuam visíveis para
            diagnóstico, mas não devem ser usados em chamadas fiscais.
          </p>
        </section>
      </aside>
    </section>

    <section v-else class="page-grid">
      <main class="main-stack">
        <section class="panel agent-panel" :class="{ warning: Boolean(agentProblem) }">
          <div>
            <span class="eyebrow">Pré-requisito A3</span>
            <h2>Agent local obrigatório</h2>
            <p v-if="agentProblem">{{ agentProblem }}</p>
            <p v-else>
              Há {{ onlineAgents.length }} Agent online. O certificado precisa estar disponível no Windows Store do
              usuário ou da máquina onde esse Agent está rodando.
            </p>
          </div>
          <div class="toolbar-actions">
            <button
              v-for="agent in onlineAgents"
              :key="agent.id"
              class="button"
              type="button"
              @click="requestInventory(agent)"
            >
              Buscar certificados em {{ agent.name }}
            </button>
            <button
              v-for="agent in onlineAgents"
              :key="`diag-${agent.id}`"
              class="button quiet"
              type="button"
              @click="requestInventory(agent, true)"
            >
              Diagnóstico {{ agent.name }}
            </button>
          </div>
        </section>

        <section>
          <div class="section-heading spaced">
            <div>
              <span class="eyebrow">Empresa</span>
              <h2>Certificados A3 vinculados</h2>
            </div>
          </div>
          <div class="card-list">
            <article v-for="certificate in localCertificates" :key="certificate.uuid" class="certificate-card">
              <div class="card-heading">
                <div>
                  <h3>{{ linkedCertificateTitle(certificate) }}</h3>
                  <span>{{ certificateTypeLabel(certificate) }}</span>
                </div>
                <StatusBadge :status="certificate.status" />
              </div>

              <div class="info-grid">
                <div>
                  <span>Validade</span>
                  <strong>{{ formatDate(certificate.valid_until) }}</strong>
                </div>
                <div>
                  <span>Último teste</span>
                  <strong>{{ testStatusLabel(certificate.last_test_status) }}</strong>
                </div>
                <div>
                  <span>Agent</span>
                  <strong>{{ certificate.agent?.name ?? '-' }}</strong>
                </div>
              </div>

              <p v-if="certificate.last_test_message" class="subtle-message">{{ certificate.last_test_message }}</p>
              <p v-if="certificate.thumbprint" class="thumbprint">{{ certificate.thumbprint }}</p>

              <div class="card-actions">
                <button
                  class="button"
                  type="button"
                  :disabled="!canTestLinkedCertificate(certificate)"
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
                  Homologação real
                </button>
              </div>
            </article>

            <div v-if="localCertificates.length === 0" class="empty-state">
              Nenhum certificado A3 foi vinculado a esta empresa.
            </div>
          </div>
        </section>

        <section>
          <div class="section-heading spaced">
            <div>
              <span class="eyebrow">Windows Store</span>
              <h2>Candidatos fiscais encontrados</h2>
            </div>
          </div>

          <div class="card-list">
            <article v-for="certificate in props.agentCertificates.data" :key="certificate.uuid" class="candidate-card">
              <div class="card-heading">
                <div>
                  <h3>{{ certificateTitle(certificate) }}</h3>
                  <span>
                    {{ formatDocument(certificate.document ?? certificate.cnpj, certificate.document_type) }}
                    · {{ storeScopeLabel(certificate.store_location ?? certificate.store_scope) }}
                  </span>
                </div>
                <span class="candidate-pill">ICP-Brasil fiscal</span>
              </div>

              <div class="info-grid compact">
                <div>
                  <span>Validade</span>
                  <strong>{{ formatDate(certificate.not_after ?? certificate.valid_until) }}</strong>
                </div>
                <div>
                  <span>Agent</span>
                  <strong>{{ certificate.agent?.name ?? '-' }}</strong>
                </div>
                <div>
                  <span>Tipo</span>
                  <strong>A1/A3 não confirmado</strong>
                </div>
              </div>

              <div class="fact-list">
                <span :class="{ ok: certificate.is_icp_brasil }"
                  >ICP-Brasil: {{ certificate.is_icp_brasil ? 'sim' : 'não' }}</span
                >
                <span :class="{ ok: certificate.has_private_key }">
                  Chave privada: {{ certificate.has_private_key ? 'sim' : 'não' }}
                </span>
                <span :class="{ danger: certificate.is_expired }"
                  >Vencido: {{ certificate.is_expired ? 'sim' : 'não' }}</span
                >
                <span :class="{ danger: certificate.is_certificate_authority }">
                  CA: {{ certificate.is_certificate_authority ? 'sim' : 'não' }}
                </span>
                <span :class="{ ok: certificate.is_usable_for_client_auth }">
                  Uso cliente: {{ certificate.is_usable_for_client_auth ? 'sim' : 'não' }}
                </span>
                <span :class="{ ok: canLinkCandidate(certificate) }">
                  CNPJ compatível: {{ canLinkCandidate(certificate) ? 'sim' : 'não' }}
                </span>
              </div>

              <p class="issuer">{{ certificate.issuer ?? certificate.issuer_name ?? '-' }}</p>
              <p class="thumbprint">{{ certificate.thumbprint }}</p>
              <div v-if="certificate.warnings?.length" class="warning-line">{{ certificate.warnings[0] }}</div>
              <div v-if="blockedLinkReason(certificate)" class="blocked-line">{{ blockedLinkReason(certificate) }}</div>

              <div class="card-actions">
                <button
                  class="button"
                  type="button"
                  :disabled="!canTestAgentCertificate(certificate)"
                  @click="testAgentCertificate(certificate)"
                >
                  Testar
                </button>
                <button
                  class="button primary"
                  type="button"
                  :disabled="!canLinkCandidate(certificate)"
                  @click="openLink(certificate)"
                >
                  Vincular à empresa
                </button>
              </div>
            </article>

            <div v-if="props.agentCertificates.data.length === 0" class="empty-state">
              Nenhum candidato fiscal encontrado. Isso é esperado quando a máquina possui apenas certificados de sistema
              ou quando o token/certificado fiscal não está disponível para o usuário do Agent.
            </div>
          </div>
        </section>
      </main>

      <aside class="side-stack">
        <section class="panel compact-panel">
          <div class="section-heading small">
            <div>
              <span class="eyebrow">SEFAZ</span>
              <h2>Conectividade</h2>
            </div>
          </div>

          <div class="timeline">
            <article v-for="test in props.sefazConnectivityTests" :key="test.uuid" class="timeline-item">
              <div class="timeline-head">
                <strong>{{ connectivityModeLabel(test.mode) }}</strong>
                <StatusBadge :status="test.status" />
              </div>
              <span class="meta"
                >{{ test.uf }} · {{ test.environment }} · {{ connectivityStatusLabel(test.status) }}</span
              >
              <p>{{ test.sefaz_message ?? test.error_message ?? test.endpoint ?? '-' }}</p>
              <span class="muted">{{ formatDateTime(test.completed_at ?? test.requested_at) }}</span>
            </article>
            <div v-if="props.sefazConnectivityTests.length === 0" class="empty-state compact">
              Nenhum teste executado.
            </div>
          </div>
        </section>

        <section class="panel compact-panel">
          <div class="section-heading small">
            <div>
              <span class="eyebrow">Diagnóstico</span>
              <h2>Ignorados pelo filtro</h2>
            </div>
            <button class="link-button" type="button" @click="showIgnored = !showIgnored">
              {{ showIgnored ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>

          <p class="diagnostic-copy">
            Certificados de sistema, CAs, vencidos e sem chave privada ficam fora da lista de candidatos.
          </p>

          <div v-if="showIgnored" class="rejected-list">
            <article
              v-for="certificate in props.ignoredAgentCertificates"
              :key="certificate.uuid"
              class="rejected-item"
            >
              <strong>{{ certificateTitle(certificate) }}</strong>
              <span>{{ classificationLabel(certificate.classification) }}</span>
              <p>{{ primaryReason(certificate) }}</p>
            </article>
            <div v-if="props.ignoredAgentCertificates.length === 0" class="empty-state compact">
              Nenhum certificado rejeitado no último inventário.
            </div>
          </div>
        </section>
      </aside>
    </section>

    <Modal :open="linkOpen" title="Vincular certificado fiscal local" @close="linkOpen = false">
      <form class="grid" @submit.prevent="linkCertificate">
        <div v-if="selectedAgentCertificate" class="selected-certificate">
          <strong>{{ certificateTitle(selectedAgentCertificate) }}</strong>
          <span>{{ formatDocument(selectedAgentCertificate.document, selectedAgentCertificate.document_type) }}</span>
          <span class="mono">{{ selectedAgentCertificate.thumbprint }}</span>
        </div>
        <FormField label="Nome de exibição" :error="linkForm.errors.name">
          <input v-model="linkForm.name" class="input" />
        </FormField>
        <div class="modal-copy">
          O Windows Store não informa com segurança se a mídia é A1 ou A3. O sistema vincula como certificado fiscal
          local do Agent e valida o uso por teste de certificado.
        </div>
        <div class="actions">
          <button class="button" type="button" @click="linkOpen = false">Cancelar</button>
          <button class="button primary" type="submit" :disabled="linkForm.processing">Vincular</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<style scoped>
.mode-panel,
.certificate-card,
.candidate-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
}

.mode-panel {
  align-items: center;
  display: grid;
  gap: 20px;
  grid-template-columns: minmax(0, 1fr) 320px;
  margin-bottom: 18px;
  padding: 20px;
}

.mode-copy h2,
.section-heading h2,
.compact-panel h2,
.agent-panel h2 {
  margin: 0;
}

.mode-copy p,
.agent-panel p,
.diagnostic-copy {
  color: var(--muted);
  margin: 6px 0 0;
}

.mode-select {
  display: grid;
  gap: 7px;
}

.mode-select span,
.meta,
.info-grid span,
.card-heading span {
  color: var(--muted);
  font-size: 12px;
}

.page-grid {
  align-items: start;
  display: grid;
  gap: 22px;
  grid-template-columns: minmax(0, 1fr) 360px;
}

.a1-grid {
  grid-template-columns: minmax(0, 1fr) 320px;
}

.main-stack,
.side-stack,
.card-list,
.timeline,
.rejected-list {
  display: grid;
  gap: 12px;
}

.upload-panel,
.agent-panel {
  padding: 18px;
}

.agent-panel {
  align-items: center;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.agent-panel.warning {
  background: #fff7ed;
  border-color: #fed7aa;
}

.upload-form {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-top: 14px;
}

.upload-note {
  background: #f7f8f5;
  border: 1px solid #e4e8e2;
  border-radius: 8px;
  color: #41504a;
  grid-column: 1 / -1;
  padding: 12px;
}

.form-actions {
  display: flex;
  grid-column: 1 / -1;
  justify-content: flex-end;
}

.section-heading {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.section-heading.spaced {
  margin-top: 28px;
}

.section-heading.small {
  margin-bottom: 12px;
}

.eyebrow {
  color: #6f4e1f;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.certificate-card,
.candidate-card {
  display: grid;
  gap: 14px;
  padding: 16px;
}

.card-heading {
  align-items: start;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.card-heading h3 {
  font-size: 16px;
  margin: 0 0 4px;
  overflow-wrap: anywhere;
}

.info-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-top: 14px;
}

.info-grid.compact {
  margin-top: 12px;
}

.info-grid div {
  background: #f7f8f5;
  border: 1px solid #e4e8e2;
  border-radius: 8px;
  display: grid;
  gap: 3px;
  min-width: 0;
  padding: 10px;
}

.info-grid strong,
.issuer,
.timeline-item p,
.rejected-item p {
  overflow-wrap: anywhere;
}

.subtle-message,
.issuer,
.warning-line,
.blocked-line {
  color: var(--muted);
  margin: 12px 0 0;
}

.thumbprint {
  color: #47524d;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace;
  font-size: 12px;
  margin: 12px 0 0;
  overflow-wrap: anywhere;
}

.toolbar-actions,
.card-actions,
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.button.quiet,
.link-button {
  color: #6f4e1f;
}

.link-button {
  background: transparent;
  border: 0;
  cursor: pointer;
  font-weight: 750;
  padding: 0;
}

.candidate-pill {
  background: #edf8f3;
  border: 1px solid #b8decf;
  border-radius: 999px;
  color: #17634d;
  font-size: 12px;
  font-weight: 800;
  padding: 6px 9px;
  white-space: nowrap;
}

.fact-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.fact-list span {
  background: #f7f8f5;
  border: 1px solid #d7ddd6;
  border-radius: 999px;
  color: #4a5751;
  font-size: 12px;
  font-weight: 750;
  padding: 5px 8px;
}

.fact-list .ok {
  background: #edf8f3;
  border-color: #b8decf;
  color: #17634d;
}

.fact-list .danger {
  background: #fef3f2;
  border-color: #fecdca;
  color: #b42318;
}

.warning-line,
.blocked-line {
  border-radius: 8px;
  padding: 9px 10px;
}

.warning-line {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #8a4b1f;
}

.blocked-line {
  background: #fef3f2;
  border: 1px solid #fecdca;
  color: #b42318;
}

.compact-panel {
  padding: 16px;
}

.timeline-item,
.rejected-item {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  display: grid;
  gap: 7px;
  padding: 12px;
}

.timeline-head {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.timeline-item p,
.rejected-item p {
  color: var(--muted);
  margin: 0;
}

.rejected-item span {
  color: #8a4b1f;
  font-size: 12px;
  font-weight: 800;
}

.empty-state {
  background: #fff;
  border: 1px dashed #cbd3cc;
  border-radius: 8px;
  color: var(--muted);
  padding: 18px;
}

.empty-state.compact {
  padding: 12px;
}

.modal-copy,
.selected-certificate {
  background: #f7f8f5;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: #41504a;
  display: grid;
  gap: 6px;
  grid-column: 1 / -1;
  padding: 12px;
}

.actions {
  grid-column: 1 / -1;
}

@media (max-width: 1180px) {
  .mode-panel,
  .page-grid,
  .a1-grid {
    grid-template-columns: 1fr;
  }

  .agent-panel {
    align-items: stretch;
    display: grid;
  }

  .toolbar-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 720px) {
  .upload-form,
  .info-grid {
    grid-template-columns: 1fr;
  }

  .card-heading,
  .toolbar-actions,
  .card-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .card-actions .button,
  .toolbar-actions .button {
    width: 100%;
  }
}
</style>
