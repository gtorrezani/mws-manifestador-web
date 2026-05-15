<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { CompanyFiscalState, DistributionAvailability, FiscalDocument, Paginated } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
  documents: Paginated<FiscalDocument>;
  filters: Record<string, string | null>;
  fiscalState: CompanyFiscalState | null;
  distributionAvailability: DistributionAvailability;
  canSyncFiscalDocuments: boolean;
}>();

const filterForm = reactive({
  period_from: props.filters.period_from ?? '',
  period_to: props.filters.period_to ?? '',
  issuer_name: props.filters.issuer_name ?? '',
  issuer_cnpj: props.filters.issuer_cnpj ?? '',
  access_key: props.filters.access_key ?? '',
  manifestation_status: props.filters.manifestation_status ?? '',
  xml_download_status: props.filters.xml_download_status ?? '',
});

const selectedIds = ref<number[]>([]);
const activeDocument = ref<FiscalDocument | null>(null);
const manifestationOpen = ref(false);
const selectedEvent = ref('');

const manifestationForm = useForm({
  event_type: '',
  justification: '',
  confirmed: false,
});

const bulkForm = useForm({
  action: '',
  document_ids: [] as number[],
});

const eventLabels: Record<string, string> = {
  operation_acknowledgement: 'Ciência da Operação',
  operation_confirmation: 'Confirmação da Operação',
  operation_unknown: 'Desconhecimento da Operação',
  operation_not_performed: 'Operação Não Realizada',
};

const selectedCount = computed(() => selectedIds.value.length);
const allVisibleSelected = computed(() => {
  const ids = props.documents.data.map((document) => document.id);
  return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
});
const canRequestDistribution = computed(() => props.canSyncFiscalDocuments && props.distributionAvailability.allowed);
const distributionBlockedMessage = computed(() => {
  if (!props.canSyncFiscalDocuments) {
    return 'Vincule e teste um certificado A3 válido antes de consultar a SEFAZ.';
  }

  return props.distributionAvailability.allowed ? null : props.distributionAvailability.message;
});

function applyFilters(): void {
  router.get('/fiscal-documents', filterForm, {
    preserveState: true,
    replace: true,
  });
}

function clearFilters(): void {
  Object.assign(filterForm, {
    period_from: '',
    period_to: '',
    issuer_name: '',
    issuer_cnpj: '',
    access_key: '',
    manifestation_status: '',
    xml_download_status: '',
  });
  applyFilters();
}

function toggleAllVisible(): void {
  const ids = props.documents.data.map((document) => document.id);
  if (allVisibleSelected.value) {
    selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
    return;
  }

  selectedIds.value = Array.from(new Set([...selectedIds.value, ...ids]));
}

function toggleDocument(documentId: number): void {
  if (selectedIds.value.includes(documentId)) {
    selectedIds.value = selectedIds.value.filter((id) => id !== documentId);
    return;
  }

  selectedIds.value = [...selectedIds.value, documentId];
}

function openManifestation(document: FiscalDocument, eventType: string): void {
  activeDocument.value = document;
  selectedEvent.value = eventType;
  manifestationForm.clearErrors();
  manifestationForm.event_type = eventType;
  manifestationForm.justification = '';
  manifestationForm.confirmed = eventType === 'operation_acknowledgement';
  manifestationOpen.value = true;
}

function submitManifestation(): void {
  if (!activeDocument.value) {
    return;
  }

  manifestationForm.post(`/fiscal-documents/${activeDocument.value.id}/manifest`, {
    preserveScroll: true,
    onSuccess: () => {
      manifestationOpen.value = false;
      activeDocument.value = null;
    },
  });
}

function requestXml(document: FiscalDocument): void {
  router.post(`/fiscal-documents/${document.id}/download-xml`, {}, { preserveScroll: true });
}

function syncFiscalDocuments(): void {
  router.post('/fiscal-documents/sync', {}, { preserveScroll: true });
}

function runBulk(action: string): void {
  bulkForm.action = action;
  bulkForm.document_ids = selectedIds.value;
  bulkForm.post('/fiscal-documents/bulk', {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.value = [];
    },
  });
}

function formatCurrency(value: string | number | null): string {
  if (value === null) {
    return '-';
  }

  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return '-';
  }

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<template>
  <Head title="Documentos Fiscais" />
  <AppLayout title="Documentos Fiscais">
    <CompanyTabs active="fiscal-documents" />

    <form class="toolbar" @submit.prevent="applyFilters">
      <FormField label="Período inicial">
        <input v-model="filterForm.period_from" class="input" type="date" />
      </FormField>
      <FormField label="Período final">
        <input v-model="filterForm.period_to" class="input" type="date" />
      </FormField>
      <FormField label="Fornecedor">
        <input v-model="filterForm.issuer_name" class="input" />
      </FormField>
      <FormField label="CNPJ emitente">
        <input v-model="filterForm.issuer_cnpj" class="input" inputmode="numeric" />
      </FormField>
      <FormField label="Chave NF-e">
        <input v-model="filterForm.access_key" class="input mono" inputmode="numeric" />
      </FormField>
      <FormField label="Status manifestação">
        <select v-model="filterForm.manifestation_status" class="select">
          <option value="">Todos</option>
          <option value="no_manifestation">Sem manifestação</option>
          <option value="acknowledgement_requested">Ciência solicitada</option>
          <option value="acknowledged">Ciência</option>
          <option value="pending_final_manifestation">Pendente conclusiva</option>
          <option value="confirmation_requested">Confirmação solicitada</option>
          <option value="confirmed">Confirmada</option>
          <option value="unknown_requested">Desconhecimento solicitado</option>
          <option value="unknown">Desconhecida</option>
          <option value="not_performed_requested">Não realizada solicitada</option>
          <option value="not_performed">Não realizada</option>
          <option value="failed">Falhou</option>
          <option value="rejected">Rejeitada</option>
        </select>
      </FormField>
      <FormField label="Status XML">
        <select v-model="filterForm.xml_download_status" class="select">
          <option value="">Todos</option>
          <option value="not_requested">Não solicitado</option>
          <option value="pending">Pendente</option>
          <option value="processing">Processando</option>
          <option value="available">Disponível</option>
          <option value="failed">Falhou</option>
          <option value="unavailable">Indisponível</option>
        </select>
      </FormField>
      <div class="toolbar compact">
        <button class="button primary" type="submit">Filtrar</button>
        <button class="button" type="button" @click="clearFilters">Limpar</button>
      </div>
    </form>

    <div class="sync-panel">
      <div>
        <strong>Distribuição DFe</strong>
        <div class="muted">
          Último NSU {{ props.fiscalState?.last_nsu ?? '000000000000000' }} · Máximo
          {{ props.fiscalState?.max_nsu ?? '000000000000000' }}
        </div>
        <div class="muted">
          {{ props.fiscalState?.last_message ?? 'Nenhuma consulta executada para a empresa selecionada.' }}
        </div>
        <div class="distribution-details">
          <span>Última consulta: {{ formatDateTime(props.fiscalState?.last_distribution_attempt_at) }}</span>
          <span>Próxima consulta: {{ formatDateTime(props.distributionAvailability.available_at) }}</span>
          <span v-if="props.fiscalState?.distribution_block_reason">
            Motivo: {{ props.fiscalState.distribution_block_reason }}
          </span>
        </div>
      </div>
      <button
        class="button primary"
        type="button"
        :disabled="!canRequestDistribution"
        :title="distributionBlockedMessage ?? 'Consultar distribuição DFe na SEFAZ'"
        @click="syncFiscalDocuments"
      >
        Consultar SEFAZ
      </button>
      <small v-if="distributionBlockedMessage" class="error">
        {{ distributionBlockedMessage }}
      </small>
    </div>

    <div class="bulk-bar">
      <strong>{{ selectedCount }} selecionado(s)</strong>
      <button class="button" type="button" :disabled="selectedCount === 0" @click="runBulk('acknowledge')">
        Criar ciência
      </button>
      <button class="button" type="button" :disabled="selectedCount === 0" @click="runBulk('download_xml')">
        Baixar XML
      </button>
      <button class="button" type="button" :disabled="selectedCount === 0" @click="runBulk('export_zip')">
        Exportar ZIP
      </button>
      <small v-if="bulkForm.errors.document_ids" class="error">{{ bulkForm.errors.document_ids }}</small>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" :checked="allVisibleSelected" @change="toggleAllVisible" /></th>
            <th>Chave</th>
            <th>Número</th>
            <th>Série</th>
            <th>Emissão</th>
            <th>Emitente</th>
            <th>Valor</th>
            <th>Manifestação</th>
            <th>XML</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="document in props.documents.data" :key="document.uuid">
            <td>
              <input
                type="checkbox"
                :checked="selectedIds.includes(document.id)"
                @change="toggleDocument(document.id)"
              />
            </td>
            <td class="mono access-key">{{ document.access_key }}</td>
            <td>{{ document.number ?? '-' }}</td>
            <td>{{ document.series ?? '-' }}</td>
            <td>{{ document.issued_at ?? '-' }}</td>
            <td>
              <strong>{{ document.issuer_name ?? 'Emitente não informado' }}</strong>
              <div class="muted mono">{{ document.issuer_cnpj ?? '-' }}</div>
            </td>
            <td>{{ formatCurrency(document.total_amount) }}</td>
            <td><StatusBadge :status="document.manifestation_status" /></td>
            <td><StatusBadge :status="document.xml_download_status" /></td>
            <td>
              <div class="row-actions">
                <button class="button" type="button" @click="openManifestation(document, 'operation_acknowledgement')">
                  Ciência
                </button>
                <button class="button" type="button" @click="openManifestation(document, 'operation_confirmation')">
                  Confirmar
                </button>
                <button class="button" type="button" @click="openManifestation(document, 'operation_unknown')">
                  Desconhecer
                </button>
                <button class="button" type="button" @click="openManifestation(document, 'operation_not_performed')">
                  Não realizada
                </button>
                <button class="button" type="button" @click="requestXml(document)">Baixar XML</button>
              </div>
            </td>
          </tr>
          <tr v-if="props.documents.data.length === 0">
            <td colspan="10" class="muted">Nenhum documento encontrado para os filtros atuais.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.documents.links" />

    <Modal
      :open="manifestationOpen"
      :title="eventLabels[selectedEvent] ?? 'Manifestação'"
      @close="manifestationOpen = false"
    >
      <form class="grid" @submit.prevent="submitManifestation">
        <div class="manifestation-summary">
          <strong>{{ activeDocument?.issuer_name ?? 'Emitente não informado' }}</strong>
          <span class="mono">{{ activeDocument?.access_key }}</span>
        </div>

        <FormField
          v-if="selectedEvent === 'operation_not_performed'"
          label="Justificativa"
          :error="manifestationForm.errors.justification"
          required
        >
          <textarea
            v-model="manifestationForm.justification"
            class="textarea"
            maxlength="255"
            placeholder="Informe a justificativa operacional."
          />
        </FormField>

        <label v-if="selectedEvent !== 'operation_acknowledgement'" class="confirm-box">
          <input v-model="manifestationForm.confirmed" type="checkbox" />
          Confirmo que este evento é conclusivo e deve ser enviado para a SEFAZ.
        </label>
        <small v-if="manifestationForm.errors.confirmed" class="error">
          {{ manifestationForm.errors.confirmed }}
        </small>

        <div class="actions">
          <button class="button" type="button" @click="manifestationOpen = false">Cancelar</button>
          <button class="button primary" type="submit" :disabled="manifestationForm.processing">Criar comando</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<style scoped>
.bulk-bar {
  align-items: center;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 12px;
  padding: 10px 12px;
}

.sync-panel {
  align-items: center;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: space-between;
  margin-bottom: 12px;
  padding: 12px;
}

.distribution-details {
  color: var(--muted);
  display: flex;
  flex-wrap: wrap;
  font-size: 12px;
  gap: 10px;
  margin-top: 6px;
}

.access-key {
  max-width: 260px;
  word-break: break-all;
}

.row-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-width: 520px;
}

.manifestation-summary {
  background: #f1f4f0;
  border: 1px solid var(--border);
  border-radius: 6px;
  display: grid;
  gap: 6px;
  padding: 12px;
}

.confirm-box {
  align-items: flex-start;
  display: flex;
  font-weight: 700;
  gap: 8px;
}

.actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
</style>
