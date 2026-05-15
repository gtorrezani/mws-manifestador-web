<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Agent, Company, Paginated } from '@/types/models';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  companies: Paginated<Company>;
  agents: Agent[];
  certificates: Array<{ id: number; name?: string; thumbprint?: string }>;
}>();

const modalOpen = ref(false);
const editingCompany = ref<Company | null>(null);

const form = useForm({
  tenant_id: 1,
  legal_name: '',
  trade_name: '',
  cnpj: '',
  state_registration: '',
  uf: '',
  is_active: true,
});

const modalTitle = computed(() => (editingCompany.value ? 'Editar empresa' : 'Cadastrar empresa'));

function certificateText(company: Company): string {
  const certificate = company.certificates?.[0];

  if (!certificate) {
    return 'Nenhum certificado cadastrado';
  }

  const type = certificate.type.toUpperCase();
  const status = certificate.status === 'active' ? 'ativo' : certificate.status;
  const validUntil = certificate.valid_until ? ` ate ${certificate.valid_until}` : '';

  return `${type} ${status}${validUntil}`;
}

function openCreate(): void {
  editingCompany.value = null;
  form.reset();
  form.clearErrors();
  modalOpen.value = true;
}

function openEdit(company: Company): void {
  editingCompany.value = company;
  form.clearErrors();
  form.tenant_id = company.tenant_id;
  form.legal_name = company.legal_name;
  form.trade_name = company.trade_name ?? '';
  form.cnpj = company.cnpj;
  form.uf = company.uf;
  form.is_active = company.is_active;
  modalOpen.value = true;
}

function submit(): void {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      modalOpen.value = false;
      form.reset();
    },
  };

  if (editingCompany.value) {
    form.put(`/companies/${editingCompany.value.id}`, options);
    return;
  }

  form.post('/companies', options);
}
</script>

<template>
  <Head title="Empresas" />
  <AppLayout title="Empresas">
    <template #actions>
      <button class="button primary" type="button" @click="openCreate">Nova empresa</button>
    </template>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>CNPJ</th>
            <th>Razão social</th>
            <th>UF</th>
            <th>Agente vinculado</th>
            <th>Certificado</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="company in props.companies.data" :key="company.uuid">
            <td class="mono">{{ company.cnpj }}</td>
            <td>
              <strong>{{ company.legal_name }}</strong>
              <div v-if="company.trade_name" class="muted">{{ company.trade_name }}</div>
            </td>
            <td>{{ company.uf }}</td>
            <td>{{ company.agents?.[0]?.name ?? 'Não vinculado' }}</td>
            <td>{{ certificateText(company) }}</td>
            <td><StatusBadge :status="company.is_active ? 'active' : 'inactive'" /></td>
            <td><button class="button" type="button" @click="openEdit(company)">Editar</button></td>
          </tr>
          <tr v-if="props.companies.data.length === 0">
            <td colspan="7" class="muted">Nenhuma empresa cadastrada.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="props.companies.links" />

    <Modal :open="modalOpen" :title="modalTitle" @close="modalOpen = false">
      <form class="grid cols-2" @submit.prevent="submit">
        <FormField label="CNPJ" :error="form.errors.cnpj" required>
          <input v-model="form.cnpj" class="input" inputmode="numeric" maxlength="14" />
        </FormField>
        <FormField label="UF" :error="form.errors.uf" required>
          <input v-model="form.uf" class="input" maxlength="2" />
        </FormField>
        <FormField label="Razão social" :error="form.errors.legal_name" required>
          <input v-model="form.legal_name" class="input" />
        </FormField>
        <FormField label="Nome fantasia" :error="form.errors.trade_name">
          <input v-model="form.trade_name" class="input" />
        </FormField>
        <label class="check">
          <input v-model="form.is_active" type="checkbox" />
          Empresa ativa
        </label>
        <div class="actions">
          <button class="button" type="button" @click="modalOpen = false">Cancelar</button>
          <button class="button primary" type="submit" :disabled="form.processing">Salvar</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<style scoped>
.check {
  align-items: center;
  display: flex;
  font-weight: 700;
  gap: 8px;
  padding-top: 26px;
}

.actions {
  display: flex;
  gap: 8px;
  grid-column: 1 / -1;
  justify-content: flex-end;
}
</style>
