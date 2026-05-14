<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Agent, Company, Paginated } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
  agents: Paginated<Agent>;
  companies: Company[];
  latestActivations: Array<{ id: number; expires_at: string | null; company?: Company | null }>;
}>();

const activationOpen = ref(false);
const activationForm = useForm({
  company_id: '',
});

function generateActivationCode(): void {
  activationForm.post('/agents/activation-code', {
    preserveScroll: true,
    onSuccess: () => {
      activationOpen.value = false;
      activationForm.reset();
    },
  });
}

function revoke(agent: Agent): void {
  if (!window.confirm('Revogar este agente? Ele não poderá buscar novos comandos.')) {
    return;
  }

  router.post(`/agents/${agent.id}/revoke`, {}, { preserveScroll: true });
}

function listCertificates(agent: Agent): void {
  router.post(`/certificates/agent/${agent.id}/list`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head title="Agentes" />
  <AppLayout title="Agentes" subtitle="Instalações locais responsáveis por certificados A3 e comunicação com a SEFAZ.">
    <div class="section-title">
      <h2>Agentes instalados</h2>
      <button class="button primary" type="button" @click="activationOpen = true">Gerar código de ativação</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Status</th>
            <th>Versão</th>
            <th>Última comunicação</th>
            <th>Empresa vinculada</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="agent in props.agents.data" :key="agent.uuid">
            <td>
              <strong>{{ agent.name }}</strong>
              <div class="muted">{{ agent.machine_name ?? 'Máquina não informada' }}</div>
            </td>
            <td><StatusBadge :status="agent.status" /></td>
            <td>{{ agent.version ?? '-' }}</td>
            <td>{{ agent.last_seen_at ?? '-' }}</td>
            <td>{{ agent.company?.legal_name ?? 'Sem vínculo' }}</td>
            <td class="row-actions">
              <Link class="button" :href="`/agents/${agent.id}/diagnostics`">Diagnóstico</Link>
              <button class="button" type="button" @click="listCertificates(agent)">Listar certificados</button>
              <button class="button danger" type="button" @click="revoke(agent)">Revogar</button>
            </td>
          </tr>
          <tr v-if="props.agents.data.length === 0">
            <td colspan="6" class="muted">Nenhum agente ativado.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.agents.links" />

    <section class="panel activations">
      <h2>Códigos recentes</h2>
      <div v-if="props.latestActivations.length === 0" class="muted">Nenhum código emitido recentemente.</div>
      <div v-for="activation in props.latestActivations" :key="activation.id" class="activation-row">
        <span>{{ activation.company?.legal_name ?? 'Sem empresa vinculada' }}</span>
        <strong>Expira em {{ activation.expires_at ?? '-' }}</strong>
      </div>
    </section>

    <Modal :open="activationOpen" title="Gerar código de ativação" @close="activationOpen = false">
      <form class="grid" @submit.prevent="generateActivationCode">
        <FormField label="Empresa" :error="activationForm.errors.company_id">
          <select v-model="activationForm.company_id" class="select">
            <option value="" disabled>Selecione uma empresa</option>
            <option v-for="company in props.companies" :key="company.id" :value="company.id">
              {{ company.legal_name }}
            </option>
          </select>
        </FormField>
        <div class="modal-copy">
          O código será exibido uma única vez e deve ser informado no agente local durante a ativação.
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
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.actions {
  justify-content: flex-end;
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
</style>
