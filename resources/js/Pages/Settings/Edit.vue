<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import type { CompanyFiscalState } from '@/types/models';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  settings: Record<string, { value?: unknown } | undefined>;
  fiscalState: CompanyFiscalState;
}>();

type AutomationRules = {
  auto_acknowledge?: boolean;
  auto_download_after_acknowledgement?: boolean;
  notify_failed_manifestations?: boolean;
};

function settingValue<T>(key: string, fallback: T): T {
  const raw = props.settings[key]?.value;

  if (raw !== null && typeof raw === 'object' && 'value' in raw) {
    return (raw as { value: T }).value;
  }

  return (raw ?? fallback) as T;
}

const automationRules = settingValue<AutomationRules>('automation_rules', {});

const form = useForm({
  last_nsu: props.fiscalState.last_nsu,
  retention_days: settingValue('retention_days', 1825),
  sync_frequency_minutes: settingValue('sync_frequency_minutes', 60),
  automation_rules: automationRules,
});

function submit(): void {
  form.put('/settings', { preserveScroll: true });
}
</script>

<template>
  <Head title="Empresa" />
  <AppLayout title="Empresa" subtitle="Dados, certificados e agentes da empresa selecionada." show-subtitle>
    <CompanyTabs active="settings" />

    <form class="panel settings-form" @submit.prevent="submit">
      <div class="section-title">
        <h2>Configurações fiscais</h2>
      </div>
      <div class="grid cols-2">
        <FormField label="Último NSU" :error="form.errors.last_nsu" required>
          <input v-model="form.last_nsu" class="input mono" inputmode="numeric" maxlength="15" />
        </FormField>

        <FormField label="Política de retenção em dias" :error="form.errors.retention_days" required>
          <input v-model="form.retention_days" class="input" min="30" type="number" />
        </FormField>

        <FormField label="Frequência de consulta em minutos" :error="form.errors.sync_frequency_minutes" required>
          <input v-model="form.sync_frequency_minutes" class="input" min="5" type="number" />
        </FormField>
      </div>

      <section class="automation">
        <h2>Regras de automação</h2>
        <label class="check">
          <input v-model="form.automation_rules.auto_acknowledge" type="checkbox" />
          Criar ciência automaticamente após documentos destinados serem encontrados
        </label>
        <label class="check">
          <input v-model="form.automation_rules.auto_download_after_acknowledgement" type="checkbox" />
          Solicitar download XML após ciência concluída
        </label>
        <label class="check">
          <input v-model="form.automation_rules.notify_failed_manifestations" type="checkbox" />
          Notificar manifestações com erro técnico
        </label>
      </section>

      <div class="actions">
        <button class="button primary" type="submit" :disabled="form.processing">Salvar configurações</button>
      </div>
    </form>
  </AppLayout>
</template>

<style scoped>
.automation {
  border-top: 1px solid var(--border);
  margin-top: 18px;
  padding-top: 18px;
}

.automation h2 {
  font-size: 18px;
}

.check {
  align-items: center;
  display: flex;
  font-weight: 700;
  gap: 8px;
  margin-top: 12px;
}

.actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}
</style>
