<script setup lang="ts">
import type { Company } from '@/types/models';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  title: string;
  subtitle?: string;
}>();

const page = usePage();
const currentCompany = computed(() => (page.props.currentCompany ?? null) as Company | null);
const availableCompanies = computed(() => (page.props.availableCompanies ?? []) as Company[]);
const selectedCompanyId = ref(currentCompany.value ? String(currentCompany.value.id) : '');
const flash = computed(() => {
  return page.props.flash as
    | {
        success?: string;
        error?: string;
        activationCode?: string | { code: string; expires_at?: string | null };
      }
    | undefined;
});

const navItems = [
  { label: 'Dashboard', href: '/' },
  { label: 'Empresas', href: '/companies' },
  { label: 'Agentes', href: '/agents' },
  { label: 'Certificados', href: '/certificates' },
  { label: 'Documentos Fiscais', href: '/fiscal-documents' },
  { label: 'Histórico', href: '/history' },
  { label: 'Configurações', href: '/settings' },
];

const environmentLabel = computed(() => {
  if (!currentCompany.value) {
    return 'Sem empresa ativa';
  }

  return currentCompany.value.fiscal_environment === 'production' ? 'Produção' : 'Homologação';
});

watch(
  currentCompany,
  (company) => {
    selectedCompanyId.value = company ? String(company.id) : '';
  },
  { immediate: true },
);

function switchCompany(): void {
  if (!selectedCompanyId.value || selectedCompanyId.value === String(currentCompany.value?.id ?? '')) {
    return;
  }

  router.post(
    '/current-company',
    { company_id: Number(selectedCompanyId.value) },
    {
      preserveScroll: false,
      replace: true,
    },
  );
}

function formatCnpj(value: string): string {
  return value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <strong>MWS</strong>
        <span>Manifestador NF-e</span>
      </div>

      <nav>
        <Link
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="nav-link"
          :class="{ active: page.url === item.href || (item.href !== '/' && page.url.startsWith(item.href)) }"
        >
          {{ item.label }}
        </Link>
      </nav>
    </aside>

    <main class="content">
      <header class="topbar">
        <div>
          <h1>{{ props.title }}</h1>
          <p v-if="props.subtitle">{{ props.subtitle }}</p>
        </div>
        <div class="company-switcher">
          <div v-if="currentCompany" class="company-summary">
            <strong>{{ currentCompany.trade_name || currentCompany.legal_name }}</strong>
            <span>{{ formatCnpj(currentCompany.cnpj) }} · {{ currentCompany.uf }}</span>
          </div>
          <select
            v-if="availableCompanies.length > 0"
            v-model="selectedCompanyId"
            class="company-select"
            @change="switchCompany"
          >
            <option v-for="company in availableCompanies" :key="company.id" :value="company.id">
              {{ company.legal_name }}
            </option>
          </select>
          <div class="environment-pill">{{ environmentLabel }}</div>
        </div>
      </header>

      <div v-if="flash?.success" class="flash">{{ flash.success }}</div>
      <div v-if="flash?.error" class="flash error">{{ flash.error }}</div>
      <div v-if="flash?.activationCode" class="flash">
        Código de ativação gerado:
        <strong class="mono">
          {{ typeof flash.activationCode === 'string' ? flash.activationCode : flash.activationCode.code }}
        </strong>
        <span v-if="typeof flash.activationCode !== 'string' && flash.activationCode.expires_at">
          , válido até {{ flash.activationCode.expires_at }}
        </span>
      </div>

      <slot />
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr);
  min-height: 100vh;
}

.flash.error {
  background: #fef3f2;
  border-color: #fecdca;
  color: #b42318;
}

.sidebar {
  background: #18231f;
  color: #eef5f1;
  padding: 22px 16px;
}

.brand {
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
  display: grid;
  gap: 2px;
  margin-bottom: 18px;
  padding-bottom: 18px;
}

.brand strong {
  font-size: 22px;
  letter-spacing: 0;
}

.brand span {
  color: #c2cec8;
  font-size: 13px;
}

nav {
  display: grid;
  gap: 6px;
}

.nav-link {
  border-radius: 6px;
  color: #d8e2dd;
  font-weight: 650;
  padding: 10px 12px;
}

.nav-link.active,
.nav-link:hover {
  background: #256f5c;
  color: #fff;
}

.content {
  min-width: 0;
  padding: 24px;
}

.topbar {
  align-items: center;
  display: flex;
  gap: 18px;
  justify-content: space-between;
  margin-bottom: 22px;
}

.topbar h1 {
  font-size: 26px;
  margin: 0;
}

.topbar p {
  color: var(--muted);
  margin: 5px 0 0;
}

.company-switcher {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: flex-end;
}

.company-summary {
  display: grid;
  gap: 2px;
  text-align: right;
}

.company-summary strong {
  color: var(--text);
  font-size: 13px;
}

.company-summary span {
  color: var(--muted);
  font-size: 12px;
}

.company-select {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text);
  min-width: 260px;
  padding: 8px 10px;
}

.environment-pill {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 999px;
  color: #9a3412;
  font-size: 12px;
  font-weight: 750;
  padding: 7px 10px;
}

@media (max-width: 900px) {
  .app-shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    position: static;
  }

  nav {
    display: flex;
    flex-wrap: wrap;
  }

  .content {
    padding: 18px;
  }

  .topbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .company-switcher {
    justify-content: flex-start;
    width: 100%;
  }

  .company-select {
    min-width: 100%;
  }
}
</style>
