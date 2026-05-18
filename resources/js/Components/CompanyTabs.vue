<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
  active: 'company' | 'agents' | 'settings' | 'certificates';
}>();

const tabs = [
  {
    key: 'company',
    label: 'Dados da empresa',
    description: 'Cadastro e status operacional',
    href: '/companies',
  },
  {
    key: 'agents',
    label: 'Dados do agente',
    description: 'Instalação, ativação e diagnóstico',
    href: '/agents',
  },
  {
    key: 'settings',
    label: 'Configurações da empresa',
    description: 'Regras fiscais, XML e automações',
    href: '/settings',
  },
  {
    key: 'certificates',
    label: 'Certificados',
    description: 'A1, A3 e validação fiscal',
    href: '/certificates',
  },
] as const;
</script>

<template>
  <section class="company-tabs-shell">
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
  overflow: hidden;
}

.company-tabs {
  background: #f7f8f6;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  padding: 0 18px;
}

.company-tab {
  border-bottom: 3px solid transparent;
  display: grid;
  gap: 4px;
  min-height: 72px;
  padding: 14px 16px 12px;
}

.company-tab:hover {
  color: #174b3c;
}

.company-tab.active {
  background: #fff;
  border-bottom-color: var(--primary);
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
  .company-tabs {
    grid-template-columns: 1fr;
    padding: 0;
  }

  .company-tab {
    border-bottom: 1px solid var(--border);
  }
}
</style>
