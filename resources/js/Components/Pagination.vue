<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
  links: Array<{ url: string | null; label: string; active: boolean }>;
}>();

function labelFor(label: string): string {
  return label.replace('&laquo;', 'Anterior').replace('&raquo;', 'Próxima');
}
</script>

<template>
  <div class="pagination">
    <component
      :is="link.url ? Link : 'span'"
      v-for="link in links"
      :key="link.label"
      :href="link.url || undefined"
      class="page-link"
      :class="{ active: link.active, disabled: !link.url }"
    >
      {{ labelFor(link.label) }}
    </component>
  </div>
</template>

<style scoped>
.pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 14px;
}

.page-link {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text);
  min-width: 34px;
  padding: 7px 10px;
  text-align: center;
}

.page-link.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

.page-link.disabled {
  color: #a1aaa5;
}
</style>
