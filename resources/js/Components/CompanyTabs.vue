<script setup lang="ts">
import type { Company } from '@/types/models';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  active: 'dashboard' | 'agents' | 'certificates' | 'fiscal-documents' | 'history' | 'settings';
}>();

const page = usePage();
const currentCompany = computed(() => (page.props.currentCompany ?? null) as Company | null);

const tabs = [
  {
    key: 'dashboard',
    label: 'Resumo',
    description: 'Visão operacional da empresa',
    href: '/',
  },
  {
    key: 'settings',
    label: 'Dados e configurações',
    description: 'Dados operacionais da empresa selecionada',
    href: '/settings',
  },
  {
    key: 'certificates',
    label: 'Certificados',
    description: 'Certificados fiscais e candidatos locais',
    href: '/certificates',
  },
  {
    key: 'agents',
    label: 'Agentes',
    description: 'Instalações locais, ativação e diagnóstico',
    href: '/agents',
  },
  {
    key: 'fiscal-documents',
    label: 'Documentos fiscais',
    description: 'Notas destinadas e XMLs',
    href: '/fiscal-documents',
  },
  {
    key: 'history',
    label: 'Histórico',
    description: 'Comandos, tentativas e retornos',
    href: '/history',
  },
] as const;
</script>

<template>
  <section class="company-tabs-shell">
    <div class="company-tabs-heading">
      <div>
        <span class="eyebrow">Empresa selecionada</span>
        <h2>{{ currentCompany?.legal_name ?? 'Empresa' }}</h2>
        <p v-if="currentCompany">
          {{ currentCompany.cnpj }} ·
          {{ currentCompany.fiscal_environment === 'production' ? 'Produção' : 'Homologação' }}
        </p>
      </div>
      <Link class="button" href="/companies">Trocar ou editar empresas</Link>
    </div>

    <nav class="company-tabs" aria-label="Área da empresa">
      <Link
        v-for="tab in tabs"
        :key="tab.key"
        :href="tab.href"
        class="company-tab"
        :class="{ active: props.active === tab.key }"
      >
        <strong>{{ tab.label }}</strong>
        <span>{{ tab.description }}</span>
      </Link>
    </nav>
  </section>
</template>

<style scoped>
.company-tabs-shell {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 18px;
  padding: 18px;
}

.company-tabs-heading {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
  margin-bottom: 16px;
}

.company-tabs-heading h2 {
  font-size: 20px;
  margin: 3px 0 4px;
}

.company-tabs-heading p {
  color: var(--muted);
  margin: 0;
}

.eyebrow {
  color: var(--primary);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.company-tabs {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.company-tab {
  border: 1px solid var(--border);
  border-radius: 8px;
  display: grid;
  gap: 4px;
  padding: 12px 14px;
}

.company-tab:hover {
  border-color: #9db4aa;
}

.company-tab.active {
  background: #eef7f4;
  border-color: var(--primary);
  color: #174b3c;
}

.company-tab strong {
  font-size: 14px;
}

.company-tab span {
  color: var(--muted);
  font-size: 12px;
  line-height: 1.35;
}

@media (max-width: 900px) {
  .company-tabs-heading {
    flex-direction: column;
  }

  .company-tabs {
    grid-template-columns: 1fr;
  }
}
</style>
