<script setup lang="ts">
defineProps<{
  label: string;
  value: string | number;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
}>();
</script>

<template>
  <section class="stat" :class="tone ?? 'neutral'">
    <span>{{ label }}</span>
    <strong>{{ value }}</strong>
  </section>
</template>

<style scoped>
.stat {
  background: var(--surface);
  border: 1px solid var(--border);
  border-left: 4px solid #7b8a83;
  border-radius: 8px;
  padding: 16px;
}

.stat span {
  color: var(--muted);
  display: block;
  font-size: 13px;
  margin-bottom: 8px;
}

.stat strong {
  font-size: 28px;
}

.success {
  border-left-color: var(--success);
}

.warning {
  border-left-color: var(--warning);
}

.danger {
  border-left-color: var(--danger);
}
</style>
