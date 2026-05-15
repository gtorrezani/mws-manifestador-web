<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  settings: Record<string, { value?: unknown } | undefined>;
}>();

type AutomationRules = {
  auto_acknowledge?: boolean;
  auto_download_after_acknowledgement?: boolean;
  notify_failed_manifestations?: boolean;
};

const automationRules = (props.settings.automation_rules?.value ?? {}) as AutomationRules;

const form = useForm({
  default_fiscal_environment: props.settings.default_fiscal_environment?.value ?? 'production',
  xml_storage_disk: props.settings.xml_storage_disk?.value ?? 's3',
  xml_retention_days: props.settings.xml_retention_days?.value ?? 1825,
  sync_frequency_minutes: props.settings.sync_frequency_minutes?.value ?? 60,
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
        <FormField label="Ambiente fiscal padrão" :error="form.errors.default_fiscal_environment" required>
          <select v-model="form.default_fiscal_environment" class="select">
            <option value="production">Produção</option>
            <option value="homologation">Homologação</option>
          </select>
        </FormField>

        <FormField label="Storage XML" :error="form.errors.xml_storage_disk" required>
          <select v-model="form.xml_storage_disk" class="select">
            <option value="local">Filesystem local</option>
            <option value="s3">S3-compatible</option>
          </select>
        </FormField>

        <FormField label="Política de retenção em dias" :error="form.errors.xml_retention_days" required>
          <input v-model="form.xml_retention_days" class="input" min="30" type="number" />
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
.settings-form {
  max-width: 940px;
}

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
