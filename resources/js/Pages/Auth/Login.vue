<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
  cpf: '',
  password: '',
  remember: false,
});

const formattedCpf = computed({
  get: () => formatCpf(form.cpf),
  set: (value: string) => {
    form.cpf = value.replace(/\D/g, '').slice(0, 11);
  },
});

function formatCpf(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 11);

  if (digits.length <= 3) {
    return digits;
  }

  if (digits.length <= 6) {
    return `${digits.slice(0, 3)}.${digits.slice(3)}`;
  }

  if (digits.length <= 9) {
    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  }

  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}

function submit(): void {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head title="Login" />

  <main class="login-page">
    <section class="login-panel" aria-labelledby="login-title">
      <div class="brand">
        <strong>MWS</strong>
        <span>Manifestador NF-e</span>
      </div>

      <form class="login-form" @submit.prevent="submit">
        <div>
          <h1 id="login-title">Acessar sistema</h1>
          <p>Entre com CPF e senha para continuar.</p>
        </div>

        <FormField label="CPF" :error="form.errors.cpf" required>
          <input
            v-model="formattedCpf"
            class="input"
            inputmode="numeric"
            autocomplete="username"
            autofocus
            placeholder="000.000.000-00"
            type="text"
          />
        </FormField>

        <FormField label="Senha" :error="form.errors.password" required>
          <input v-model="form.password" class="input" autocomplete="current-password" type="password" />
        </FormField>

        <label class="remember">
          <input v-model="form.remember" type="checkbox" />
          <span>Lembrar-me</span>
        </label>

        <button class="button primary" type="submit" :disabled="form.processing">
          {{ form.processing ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>
    </section>
  </main>
</template>

<style scoped>
.login-page {
  align-items: center;
  display: grid;
  min-height: 100vh;
  padding: 24px;
}

.login-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: var(--shadow);
  display: grid;
  gap: 26px;
  margin: 0 auto;
  max-width: 420px;
  padding: 28px;
  width: 100%;
}

.brand {
  border-bottom: 1px solid var(--border);
  display: grid;
  gap: 2px;
  padding-bottom: 18px;
}

.brand strong {
  color: var(--primary);
  font-size: 24px;
  letter-spacing: 0;
}

.brand span {
  color: var(--muted);
  font-size: 13px;
}

.login-form {
  display: grid;
  gap: 16px;
}

.login-form h1 {
  font-size: 24px;
  margin: 0;
}

.login-form p {
  color: var(--muted);
  margin: 6px 0 0;
}

.remember {
  align-items: center;
  color: #41504a;
  display: inline-flex;
  font-weight: 650;
  gap: 8px;
}

.remember input {
  height: 16px;
  width: 16px;
}
</style>
