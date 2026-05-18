<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import type { Company, Paginated, User } from '@/types/models';
import { formatCnpj, formatCpf, onlyDigits } from '@/utils/documents';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  companies: Paginated<Company>;
  companyUsers: User[];
}>();

const page = usePage();
const mode = ref<'edit' | 'create'>('edit');
const currentCompany = computed(() => (page.props.currentCompany ?? null) as Company | null);
const selectedCompany = computed(
  () =>
    props.companies.data.find((company) => company.id === currentCompany.value?.id) ??
    currentCompany.value ??
    props.companies.data[0] ??
    null,
);
const selectedCompanyUsers = computed(() => {
  if (!selectedCompany.value) {
    return [];
  }

  return props.companyUsers.filter((user) =>
    (user.companies ?? []).some((company) => company.id === selectedCompany.value?.id),
  );
});

const editForm = useForm({
  tenant_id: 1,
  legal_name: '',
  trade_name: '',
  cnpj: '',
  state_registration: '',
  uf: '',
  is_active: true,
});

const createForm = useForm({
  tenant_id: 1,
  legal_name: '',
  trade_name: '',
  cnpj: '',
  state_registration: '',
  uf: '',
  is_active: true,
});

const userForm = useForm({
  name: '',
  cpf: '',
  password: '',
  is_active: true,
});

const brazilianStates = [
  { value: 'AC', label: 'AC - Acre' },
  { value: 'AL', label: 'AL - Alagoas' },
  { value: 'AP', label: 'AP - Amapá' },
  { value: 'AM', label: 'AM - Amazonas' },
  { value: 'BA', label: 'BA - Bahia' },
  { value: 'CE', label: 'CE - Ceará' },
  { value: 'DF', label: 'DF - Distrito Federal' },
  { value: 'ES', label: 'ES - Espírito Santo' },
  { value: 'GO', label: 'GO - Goiás' },
  { value: 'MA', label: 'MA - Maranhão' },
  { value: 'MT', label: 'MT - Mato Grosso' },
  { value: 'MS', label: 'MS - Mato Grosso do Sul' },
  { value: 'MG', label: 'MG - Minas Gerais' },
  { value: 'PA', label: 'PA - Pará' },
  { value: 'PB', label: 'PB - Paraíba' },
  { value: 'PR', label: 'PR - Paraná' },
  { value: 'PE', label: 'PE - Pernambuco' },
  { value: 'PI', label: 'PI - Piauí' },
  { value: 'RJ', label: 'RJ - Rio de Janeiro' },
  { value: 'RN', label: 'RN - Rio Grande do Norte' },
  { value: 'RS', label: 'RS - Rio Grande do Sul' },
  { value: 'RO', label: 'RO - Rondônia' },
  { value: 'RR', label: 'RR - Roraima' },
  { value: 'SC', label: 'SC - Santa Catarina' },
  { value: 'SP', label: 'SP - São Paulo' },
  { value: 'SE', label: 'SE - Sergipe' },
  { value: 'TO', label: 'TO - Tocantins' },
] as const;

watch(
  selectedCompany,
  (company) => {
    if (!company) {
      return;
    }

    editForm.clearErrors();
    editForm.tenant_id = company.tenant_id;
    editForm.legal_name = company.legal_name;
    editForm.trade_name = company.trade_name ?? '';
    editForm.cnpj = formatCnpj(company.cnpj);
    editForm.state_registration = company.state_registration ?? '';
    editForm.uf = company.uf;
    editForm.is_active = company.is_active;
    createForm.tenant_id = company.tenant_id;
  },
  { immediate: true },
);

function submitEdit(): void {
  if (!selectedCompany.value) {
    return;
  }

  editForm.cnpj = onlyDigits(editForm.cnpj);
  editForm.put(`/companies/${selectedCompany.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      editForm.cnpj = formatCnpj(editForm.cnpj);
    },
    onError: () => {
      editForm.cnpj = formatCnpj(editForm.cnpj);
    },
  });
}

function submitCreate(): void {
  createForm.cnpj = onlyDigits(createForm.cnpj);
  createForm.post('/companies', {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset('legal_name', 'trade_name', 'cnpj', 'state_registration', 'uf');
      mode.value = 'edit';
    },
    onError: () => {
      createForm.cnpj = formatCnpj(createForm.cnpj);
    },
  });
}

function submitUser(): void {
  if (!selectedCompany.value) {
    return;
  }

  userForm.cpf = onlyDigits(userForm.cpf);
  userForm.post(`/companies/${selectedCompany.value.id}/users`, {
    preserveScroll: true,
    onSuccess: () => userForm.reset('name', 'cpf', 'password'),
    onError: () => {
      userForm.cpf = formatCpf(userForm.cpf);
    },
  });
}

function setEditCnpj(value: string): void {
  editForm.cnpj = formatCnpj(value);
}

function setCreateCnpj(value: string): void {
  createForm.cnpj = formatCnpj(value);
}

function setUserCpf(value: string): void {
  userForm.cpf = formatCpf(value);
}

function showCreate(): void {
  mode.value = 'create';
  createForm.clearErrors();
  createForm.tenant_id = selectedCompany.value?.tenant_id ?? currentCompany.value?.tenant_id ?? 1;
}
</script>

<template>
  <Head title="Empresa - Dados" />
  <AppLayout title="Empresa" subtitle="Dados cadastrais e fiscais da empresa selecionada." show-subtitle>
    <CompanyTabs active="company" />

    <section class="company-page">
      <div class="actions-row">
        <button class="button" type="button" :class="{ primary: mode === 'edit' }" @click="mode = 'edit'">
          Editar empresa
        </button>
        <button class="button" type="button" :class="{ primary: mode === 'create' }" @click="showCreate">
          Nova empresa
        </button>
      </div>

      <form v-if="mode === 'create'" class="panel company-form" @submit.prevent="submitCreate">
        <div class="section-title">
          <div>
            <h2>Nova empresa</h2>
          </div>
          <button class="button primary" type="submit" :disabled="createForm.processing">
            {{ createForm.processing ? 'Salvando...' : 'Cadastrar empresa' }}
          </button>
        </div>

        <div class="grid cols-2">
          <FormField label="Razão social" :error="createForm.errors.legal_name" required>
            <input v-model="createForm.legal_name" class="input" />
          </FormField>

          <FormField label="Nome fantasia" :error="createForm.errors.trade_name">
            <input v-model="createForm.trade_name" class="input" />
          </FormField>

          <FormField label="CNPJ" :error="createForm.errors.cnpj" required>
            <input
              :value="createForm.cnpj"
              class="input mono"
              inputmode="numeric"
              maxlength="18"
              @input="setCreateCnpj(($event.target as HTMLInputElement).value)"
            />
          </FormField>

          <FormField label="Inscrição estadual" :error="createForm.errors.state_registration">
            <input v-model="createForm.state_registration" class="input" maxlength="32" />
          </FormField>

          <FormField label="UF" :error="createForm.errors.uf" required>
            <select v-model="createForm.uf" class="select">
              <option value="">Selecione</option>
              <option v-for="state in brazilianStates" :key="state.value" :value="state.value">
                {{ state.label }}
              </option>
            </select>
          </FormField>
        </div>

        <label class="check">
          <input v-model="createForm.is_active" type="checkbox" />
          Empresa ativa
        </label>
      </form>

      <form v-else-if="selectedCompany" class="panel company-form" @submit.prevent="submitEdit">
        <div class="section-title">
          <div>
            <h2>Dados cadastrais</h2>
          </div>
          <button class="button primary" type="submit" :disabled="editForm.processing">
            {{ editForm.processing ? 'Salvando...' : 'Salvar alterações' }}
          </button>
        </div>

        <div class="grid cols-2">
          <FormField label="Razão social" :error="editForm.errors.legal_name" required>
            <input v-model="editForm.legal_name" class="input" />
          </FormField>

          <FormField label="Nome fantasia" :error="editForm.errors.trade_name">
            <input v-model="editForm.trade_name" class="input" />
          </FormField>

          <FormField label="CNPJ" :error="editForm.errors.cnpj" required>
            <input
              :value="editForm.cnpj"
              class="input mono"
              inputmode="numeric"
              maxlength="18"
              @input="setEditCnpj(($event.target as HTMLInputElement).value)"
            />
          </FormField>

          <FormField label="Inscrição estadual" :error="editForm.errors.state_registration">
            <input v-model="editForm.state_registration" class="input" maxlength="32" />
          </FormField>

          <FormField label="UF" :error="editForm.errors.uf" required>
            <select v-model="editForm.uf" class="select">
              <option v-for="state in brazilianStates" :key="state.value" :value="state.value">
                {{ state.label }}
              </option>
            </select>
          </FormField>
        </div>

        <label class="check">
          <input v-model="editForm.is_active" type="checkbox" />
          Empresa ativa
        </label>
      </form>

      <section v-else class="panel empty-state">Nenhuma empresa vinculada ao usuário atual.</section>

      <section v-if="selectedCompany" class="panel users-panel">
        <div class="section-title">
          <div>
            <h2>Usuários da empresa</h2>
          </div>
        </div>

        <form class="user-form" @submit.prevent="submitUser">
          <FormField label="Nome" :error="userForm.errors.name" required>
            <input v-model="userForm.name" class="input" />
          </FormField>

          <FormField label="CPF" :error="userForm.errors.cpf" required>
            <input
              :value="userForm.cpf"
              class="input mono"
              inputmode="numeric"
              maxlength="14"
              @input="setUserCpf(($event.target as HTMLInputElement).value)"
            />
          </FormField>

          <FormField label="Senha inicial" :error="userForm.errors.password" required>
            <input v-model="userForm.password" class="input" type="password" autocomplete="new-password" />
          </FormField>

          <label class="check inline-check">
            <input v-model="userForm.is_active" type="checkbox" />
            Ativo
          </label>

          <button class="button primary" type="submit" :disabled="userForm.processing">
            {{ userForm.processing ? 'Salvando...' : 'Cadastrar usuário' }}
          </button>
        </form>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>Status</th>
                <th>Último login</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in selectedCompanyUsers" :key="user.id">
                <td>{{ user.name }}</td>
                <td class="mono">{{ formatCpf(user.cpf) }}</td>
                <td>{{ user.is_active && !user.blocked_at ? 'Ativo' : 'Bloqueado/Inativo' }}</td>
                <td>{{ user.last_login_at ?? '-' }}</td>
              </tr>
              <tr v-if="selectedCompanyUsers.length === 0">
                <td colspan="4" class="muted">Nenhum usuário vinculado.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </AppLayout>
</template>

<style scoped>
.company-page {
  width: 100%;
}

.company-form {
  min-width: 0;
}

.actions-row {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
}

.section-title {
  align-items: flex-start;
  gap: 16px;
}

.check {
  align-items: center;
  border-top: 1px solid var(--border);
  display: flex;
  font-weight: 700;
  gap: 8px;
  margin-top: 18px;
  padding-top: 18px;
}

.empty-state {
  color: var(--muted);
}

.users-panel {
  margin-top: 18px;
}

.user-form {
  align-items: end;
  display: grid;
  gap: 14px;
  grid-template-columns: minmax(180px, 1fr) 150px minmax(160px, 220px) 90px auto;
}

.inline-check {
  border-top: 0;
  margin-top: 0;
  padding-top: 0;
}

.table-wrapper {
  margin-top: 18px;
  overflow-x: auto;
}

.data-table {
  border-collapse: collapse;
  width: 100%;
}

.data-table th,
.data-table td {
  border-top: 1px solid var(--border);
  padding: 10px;
  text-align: left;
  white-space: nowrap;
}

@media (max-width: 720px) {
  .section-title {
    align-items: stretch;
    flex-direction: column;
  }

  .actions-row,
  .user-form {
    align-items: stretch;
    grid-template-columns: 1fr;
  }
}
</style>
