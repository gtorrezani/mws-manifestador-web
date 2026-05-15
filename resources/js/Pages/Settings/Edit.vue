<script setup lang="ts">
import FormField from '@/Components/FormField.vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import type { Company } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';

type SettingsCompany = Pick<Company, 'id' | 'legal_name' | 'trade_name' | 'cnpj' | 'uf'>;

type AutomationRules = {
  auto_acknowledge?: boolean;
  auto_download_after_acknowledgement?: boolean;
  notify_failed_manifestations?: boolean;
};

const props = defineProps<{
  companies: SettingsCompany[];
  selectedCompanyId: number | null;
  settings: Record<string, unknown>;
}>();

const automationRules = (props.settings.automation_rules ?? {}) as AutomationRules;

const form = useForm({
  company_id: props.selectedCompanyId ?? props.companies[0]?.id ?? '',
  xml_storage_disk: (props.settings.xml_storage_disk as string | undefined) ?? 's3',
  xml_retention_days: (props.settings.xml_retention_days as number | string | undefined) ?? 1825,
  sync_frequency_minutes: (props.settings.sync_frequency_minutes as number | string | undefined) ?? 60,
  automation_rules: {
    auto_acknowledge: Boolean(automationRules.auto_acknowledge),
    auto_download_after_acknowledgement: Boolean(automationRules.auto_download_after_acknowledgement),
    notify_failed_manifestations: Boolean(automationRules.notify_failed_manifestations),
  },
});

function formatCnpj(cnpj: string): string {
  return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

function companyLabel(company: SettingsCompany): string {
  const name = company.trade_name || company.legal_name;

  return `${name} - ${formatCnpj(company.cnpj)} (${company.uf})`;
}

function loadCompany(): void {
  if (!form.company_id) {
    return;
  }

  router.get(
    '/settings',
    { company_id: form.company_id },
    {
      preserveScroll: true,
      preserveState: false,
      replace: true,
    },
  );
}

function submit(): void {
  form.put('/settings', { preserveScroll: true });
}
</script>

<template>
  <Head title="Configura&ccedil;&otilde;es" />
  <AppLayout title="Configura&ccedil;&otilde;es" subtitle="Par&acirc;metros aplicados por empresa." show-subtitle>
    <form class="panel settings-form" @submit.prevent="submit">
      <FormField label="Empresa" :error="form.errors.company_id" required>
        <select
          v-model="form.company_id"
          class="select"
          :disabled="form.processing || !props.companies.length"
          @change="loadCompany"
        >
          <option disabled value="">Selecione uma empresa</option>
          <option v-for="company in props.companies" :key="company.id" :value="company.id">
            {{ companyLabel(company) }}
          </option>
        </select>
      </FormField>

      <p v-if="!props.companies.length" class="empty-state">
        Cadastre uma empresa antes de definir configura&ccedil;&otilde;es.
      </p>

      <fieldset class="settings-fields" :disabled="form.processing || !props.companies.length">
        <div class="grid cols-2">
          <FormField label="Storage XML" :error="form.errors.xml_storage_disk" required>
            <select v-model="form.xml_storage_disk" class="select">
              <option value="local">Filesystem local</option>
              <option value="s3">S3-compatible</option>
            </select>
          </FormField>

          <FormField
            label="Pol&iacute;tica de reten&ccedil;&atilde;o em dias"
            :error="form.errors.xml_retention_days"
            required
          >
            <input v-model="form.xml_retention_days" class="input" min="30" type="number" />
          </FormField>

          <FormField
            label="Frequ&ecirc;ncia de consulta em minutos"
            :error="form.errors.sync_frequency_minutes"
            required
          >
            <input v-model="form.sync_frequency_minutes" class="input" min="5" type="number" />
          </FormField>
        </div>

        <section class="automation">
          <h2>Regras de automa&ccedil;&atilde;o</h2>
          <label class="check">
            <input v-model="form.automation_rules.auto_acknowledge" type="checkbox" />
            Criar ci&ecirc;ncia automaticamente ap&oacute;s documentos destinados serem encontrados
          </label>
          <label class="check">
            <input v-model="form.automation_rules.auto_download_after_acknowledgement" type="checkbox" />
            Solicitar download XML ap&oacute;s ci&ecirc;ncia conclu&iacute;da
          </label>
          <label class="check">
            <input v-model="form.automation_rules.notify_failed_manifestations" type="checkbox" />
            Notificar manifesta&ccedil;&otilde;es com erro t&eacute;cnico
          </label>
        </section>

        <div class="actions">
          <button class="button primary" type="submit" :disabled="form.processing || !props.companies.length">
            Salvar configura&ccedil;&otilde;es
          </button>
        </div>
      </fieldset>
    </form>
  </AppLayout>
</template>

<style scoped>
.settings-form {
  display: grid;
  gap: 18px;
  max-width: 940px;
}

.settings-fields {
  border: 0;
  display: grid;
  gap: 18px;
  margin: 0;
  padding: 0;
}

.empty-state {
  color: var(--muted);
  font-weight: 650;
  margin: 0;
}

.automation {
  border-top: 1px solid var(--border);
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
}
</style>
