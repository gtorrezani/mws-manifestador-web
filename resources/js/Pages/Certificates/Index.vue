<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Agent, AgentCertificate, Company, CompanyCertificate, Paginated } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  companies: Company[];
  agents: Agent[];
  agentCertificates: Paginated<AgentCertificate>;
  companyCertificates: Paginated<CompanyCertificate>;
}>();

const a1Open = ref(false);
const a3Open = ref(false);
const selectedAgentCertificate = ref<AgentCertificate | null>(null);

const a1Form = useForm<{
  company_id: string;
  name: string;
  certificate_file: File | null;
  password: string;
}>({
  company_id: '',
  name: '',
  certificate_file: null,
  password: '',
});

const a3Form = useForm({
  company_id: '',
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
  a3Form.name = certificate.subject_name ?? '';
  if (certificate.cnpj) {
    const matchingCompany = props.companies.find((company) => company.cnpj === certificate.cnpj);
    a3Form.company_id = matchingCompany ? String(matchingCompany.id) : '';
  }
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

function setA1File(event: Event): void {
  const input = event.target as HTMLInputElement;
  a1Form.certificate_file = input.files?.[0] ?? null;
}

function typeLabel(type: string): string {
  return type === 'a1' ? 'A1 em nuvem' : 'A3 via agente';
}

function storeScopeLabel(scope: string | null): string {
  if (scope === 'current_user') {
    return 'Usuário atual';
  }
  if (scope === 'local_machine') {
    return 'Máquina local';
  }

  return '-';
}
</script>

<template>
  <Head title="Certificados" />
  <AppLayout
    title="Certificados"
    subtitle="Cadastro, vínculo e validação dos certificados usados nas operações fiscais."
  >
    <div class="section-title">
      <h2>Certificados vinculados às empresas</h2>
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
              <span>{{ certificate.last_test_status ?? 'Não testado' }}</span>
              <div class="muted">{{ certificate.last_tested_at ?? '-' }}</div>
            </td>
            <td>{{ certificate.agent?.name ?? '-' }}</td>
            <td><button class="button" type="button" @click="testCertificate(certificate)">Testar</button></td>
          </tr>
          <tr v-if="props.companyCertificates.data.length === 0">
            <td colspan="8" class="muted">Nenhum certificado vinculado.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.companyCertificates.links" />

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
          Listar certificados em {{ agent.name }}
        </button>
        <span v-if="onlineAgents.length === 0" class="muted">Nenhum agente online para listar certificados.</span>
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
            <th>Agente</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="certificate in props.agentCertificates.data" :key="certificate.uuid">
            <td>
              <strong>{{ certificate.subject_name ?? '-' }}</strong>
              <div class="muted mono">{{ certificate.thumbprint }}</div>
            </td>
            <td class="mono">{{ certificate.cnpj ?? '-' }}</td>
            <td>{{ storeScopeLabel(certificate.store_scope) }}</td>
            <td>{{ certificate.valid_until ?? '-' }}</td>
            <td>{{ certificate.has_private_key ? 'Sim' : 'Não' }}</td>
            <td>{{ certificate.agent?.name ?? '-' }}</td>
            <td><StatusBadge :status="certificate.status" /></td>
            <td><button class="button" type="button" @click="openA3Link(certificate)">Vincular</button></td>
          </tr>
          <tr v-if="props.agentCertificates.data.length === 0">
            <td colspan="8" class="muted">Nenhum certificado A3 informado por agente.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.agentCertificates.links" />

    <Modal :open="a1Open" title="Cadastrar certificado A1" @close="a1Open = false">
      <form class="grid cols-2" @submit.prevent="uploadA1">
        <FormField label="Empresa" :error="a1Form.errors.company_id" required>
          <select v-model="a1Form.company_id" class="select">
            <option value="" disabled>Selecione uma empresa</option>
            <option v-for="company in props.companies" :key="company.id" :value="company.id">
              {{ company.legal_name }}
            </option>
          </select>
        </FormField>
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
          A senha é usada para validar o certificado e armazenada criptografada. Nunca informe PIN de certificado A3
          nesta tela.
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
          <strong>{{ selectedAgentCertificate.subject_name ?? 'Certificado selecionado' }}</strong>
          <span class="mono">{{ selectedAgentCertificate.thumbprint }}</span>
        </div>
        <FormField label="Empresa" :error="a3Form.errors.company_id" required>
          <select v-model="a3Form.company_id" class="select">
            <option value="" disabled>Selecione uma empresa</option>
            <option v-for="company in props.companies" :key="company.id" :value="company.id">
              {{ company.legal_name }} - {{ company.cnpj }}
            </option>
          </select>
        </FormField>
        <FormField label="Nome de exibição" :error="a3Form.errors.name">
          <input v-model="a3Form.name" class="input" />
        </FormField>
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
.actions {
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
