<script setup lang="ts">
import AppLayout from '@/Components/Layout/AppLayout.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { AgentCommand, Paginated } from '@/types/models';
import { Head } from '@inertiajs/vue3';

defineProps<{
  commands: Paginated<AgentCommand>;
  sefazRequests: Array<{
    id: number;
    service: string;
    correlation_id: string | null;
    status: string | null;
    created_at: string | null;
    responses?: Array<{
      id: number;
      status_code: string | null;
      reason: string | null;
      protocol_number: string | null;
    }>;
  }>;
}>();

const technicalMessage = (code: string | null, message: string | null): string => {
  if (code === 'SEFAZ_XML_SCHEMA_INVALID') {
    return 'XML rejeitado pela validação técnica antes do envio à SEFAZ.';
  }

  return message ?? '-';
};
</script>

<template>
  <Head title="Histórico" />
  <AppLayout title="Histórico">
    <CompanyTabs active="history" />

    <section class="panel">
      <div class="section-title">
        <h2>Comandos</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>UUID</th>
              <th>Empresa</th>
              <th>Tipo</th>
              <th>Status</th>
              <th>Prioridade</th>
              <th>Erro técnico</th>
              <th>Criado em</th>
              <th>Finalizado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="command in commands.data" :key="command.uuid">
              <td class="mono">{{ command.uuid }}</td>
              <td>{{ command.company?.legal_name ?? 'Sem empresa' }}</td>
              <td class="mono">{{ command.type }}</td>
              <td><StatusBadge :status="command.status" /></td>
              <td>{{ command.priority }}</td>
              <td>
                <span v-if="command.attempts?.[0]?.error_code" class="error-text">
                  {{ technicalMessage(command.attempts[0].error_code, command.attempts[0].error_message) }}
                </span>
                <span v-else>-</span>
              </td>
              <td>{{ command.created_at ?? '-' }}</td>
              <td>{{ command.completed_at ?? command.failed_at ?? '-' }}</td>
            </tr>
            <tr v-if="commands.data.length === 0">
              <td colspan="8" class="muted">Nenhum comando registrado.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="commands.links" />
    </section>

    <section class="panel sefaz">
      <div class="section-title">
        <h2>Requisições SEFAZ</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Serviço</th>
              <th>Correlação</th>
              <th>Status SEFAZ</th>
              <th>Mensagem</th>
              <th>Protocolo</th>
              <th>Data/hora</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="request in sefazRequests" :key="request.id">
              <td class="mono">{{ request.service }}</td>
              <td class="mono">{{ request.correlation_id ?? '-' }}</td>
              <td>{{ request.responses?.[0]?.status_code ?? request.status ?? '-' }}</td>
              <td>{{ request.responses?.[0]?.reason ?? '-' }}</td>
              <td class="mono">{{ request.responses?.[0]?.protocol_number ?? '-' }}</td>
              <td>{{ request.created_at ?? '-' }}</td>
            </tr>
            <tr v-if="sefazRequests.length === 0">
              <td colspan="6" class="muted">Nenhuma requisição SEFAZ registrada.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppLayout>
</template>

<style scoped>
.sefaz {
  margin-top: 18px;
}

.error-text {
  color: #b42318;
  font-weight: 600;
}
</style>
