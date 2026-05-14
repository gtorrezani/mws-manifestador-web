<script setup lang="ts">
const props = defineProps<{
  status: string | null | undefined;
}>();

const labels: Record<string, string> = {
  pending: 'Pendente',
  processing: 'Processando',
  completed: 'Concluído',
  failed: 'Falhou',
  canceled: 'Cancelado',
  no_manifestation: 'Sem manifestação',
  acknowledgement_requested: 'Ciência solicitada',
  pending_final_manifestation: 'Pendente conclusiva',
  confirmation_requested: 'Confirmação solicitada',
  unknown_requested: 'Desconhecimento solicitado',
  not_performed_requested: 'Não realizada solicitada',
  rejected: 'Rejeitada',
  online: 'Online',
  offline: 'Offline',
  revoked: 'Revogado',
  active: 'Ativo',
  inactive: 'Inativo',
  pending_validation: 'Pendente validação',
  expired: 'Vencido',
  invalid: 'Inválido',
  acknowledged: 'Ciência',
  confirmed: 'Confirmada',
  unknown: 'Desconhecida',
  not_performed: 'Não realizada',
  not_requested: 'Não solicitado',
  available: 'Disponível',
  unavailable: 'Indisponível',
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
.invalid {
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
.unavailable {
  background: #f4f4f5;
  border-color: #d4d4d8;
  color: #52525b;
}
</style>
