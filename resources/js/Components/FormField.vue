<script setup lang="ts">
defineProps<{
  label: string;
  error?: string;
  required?: boolean;
}>();
</script>

<template>
  <label class="field">
    <span>{{ label }}<strong v-if="required">*</strong></span>
    <slot />
    <small v-if="error" class="error">{{ error }}</small>
  </label>
</template>

<style scoped>
.field {
  display: grid;
  gap: 6px;
}

.field span {
  color: #41504a;
  font-size: 13px;
  font-weight: 700;
}

.field strong {
  color: var(--danger);
  margin-left: 3px;
}
</style>
