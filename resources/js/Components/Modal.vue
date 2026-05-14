<script setup lang="ts">
defineProps<{
  open: boolean;
  title: string;
}>();

const emit = defineEmits<{
  close: [];
}>();
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="overlay" @click.self="emit('close')">
      <section class="dialog" role="dialog" aria-modal="true">
        <header>
          <h2>{{ title }}</h2>
          <button class="close" type="button" @click="emit('close')">Fechar</button>
        </header>
        <slot />
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.overlay {
  align-items: center;
  background: rgba(24, 35, 31, 0.55);
  display: flex;
  inset: 0;
  justify-content: center;
  padding: 18px;
  position: fixed;
  z-index: 50;
}

.dialog {
  background: #fff;
  border-radius: 8px;
  box-shadow: var(--shadow);
  max-width: 620px;
  padding: 20px;
  width: 100%;
}

header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}

h2 {
  font-size: 20px;
  margin: 0;
}

.close {
  background: transparent;
  border: 0;
  color: var(--muted);
  font-weight: 700;
}
</style>
