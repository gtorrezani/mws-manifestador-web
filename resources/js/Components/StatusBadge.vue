<script setup lang="ts">
const props = defineProps<{
  status: string | null | undefined;
}>();

const labels: Record<string, string> = {
  pending: 'Pendente',
  processing: 'Processando',
  completed: 'Concluido',
  failed: 'Falhou',
  canceled: 'Cancelado',
  no_manifestation: 'Sem manifestacao',
  acknowledgement_requested: 'Ciencia solicitada',
  pending_final_manifestation: 'Pendente conclusiva',
  confirmation_requested: 'Confirmacao solicitada',
  unknown_requested: 'Desconhecimento solicitado',
  not_performed_requested: 'Nao realizada solicitada',
  rejected: 'Rejeitada',
  online: 'Online',
  offline: 'Offline',
  revoked: 'Revogado',
  active: 'Ativo',
  inactive: 'Inativo',
  pending_validation: 'Pendente validacao',
  expired: 'Vencido',
  invalid: 'Invalido',
  acknowledged: 'Ciencia',
  confirmed: 'Confirmada',
  unknown: 'Desconhecida',
  not_performed: 'Nao realizada',
  not_requested: 'Nao solicitado',
  available: 'Disponivel',
  unavailable: 'Indisponivel',
  not_installed: 'Nao instalado',
  pending_activation: 'Aguardando ativacao',
  outdated: 'Desatualizado',
  error: 'Erro',
  service_stopped: 'Servico parado',
};
</script>

<template>
  <span class="badge" :class="String(props.status ?? 'pending').toLowerCase()">
    {{ labels[String(props.status ?? '').toLowerCase()] ?? props.status ?? 'Sem status' }}
  </span>
</template>

<style scoped>
.badge {
  align-items: center;
  background: #eef1ed;
  border: 1px solid #d9ded8;
  border-radius: 999px;
  color: #41504a;
  display: inline-flex;
  font-size: 12px;
  font-weight: 750;
  min-height: 24px;
  padding: 0 9px;
  white-space: nowrap;
}

.completed,
.online,
.active,
.available,
.confirmed,
.pending_final_manifestation {
  background: #e8f6ef;
  border-color: #b9dfcc;
  color: #1f7a4d;
}

.pending,
.processing,
.pending_activation,
.pending_validation,
.acknowledged,
.acknowledgement_requested,
.confirmation_requested,
.unknown_requested,
.not_performed_requested {
  background: #fff7ed;
  border-color: #fed7aa;
  color: #9a3412;
}

.failed,
.rejected,
.revoked,
.offline,
.expired,
.invalid,
.outdated,
.error,
.service_stopped {
  background: #fef3f2;
  border-color: #fecdca;
  color: #b42318;
}

.canceled,
.inactive,
.no_manifestation,
.unknown,
.not_performed,
.not_requested,
.unavailable,
.not_installed {
  background: #f4f4f5;
  border-color: #d4d4d8;
  color: #52525b;
}
</style>
