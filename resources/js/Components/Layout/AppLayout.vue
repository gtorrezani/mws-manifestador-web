<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  title: string;
  subtitle?: string;
}>();

const page = usePage();
const flash = computed(() => {
  return page.props.flash as
    | {
        success?: string;
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
        <div class="environment-pill">Produção assistida</div>
      </header>

      <div v-if="flash?.success" class="flash">{{ flash.success }}</div>
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
}
</style>
