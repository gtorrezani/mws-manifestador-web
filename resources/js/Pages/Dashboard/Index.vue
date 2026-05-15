<script setup lang="ts">
import AppLayout from '@/Components/Layout/AppLayout.vue';
import CompanyTabs from '@/Components/CompanyTabs.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { AgentCommand } from '@/types/models';
import { Head } from '@inertiajs/vue3';

defineProps<{
  metrics: {
    documentsFound: number;
    xmlDownloaded: number;
    pendingAcknowledgement: number;
    pendingConclusiveManifestation: number;
    manifestationErrors: number;
    agentsOnline: number;
    agentsOffline: number;
    expiringCertificates: number;
  };
  latestSyncs: AgentCommand[];
}>();
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout title="Dashboard">
    <CompanyTabs active="dashboard" />

    <div class="grid cols-4">
      <StatCard label="Notas encontradas" :value="metrics.documentsFound" />
      <StatCard label="XMLs baixados" :value="metrics.xmlDownloaded" tone="success" />
      <StatCard label="Pendentes de ciência" :value="metrics.pendingAcknowledgement" tone="warning" />
      <StatCard
        label="Pendentes de manifestação conclusiva"
        :value="metrics.pendingConclusiveManifestation"
        tone="warning"
      />
      <StatCard label="Manifestações com erro" :value="metrics.manifestationErrors" tone="danger" />
      <StatCard label="Agentes online" :value="metrics.agentsOnline" tone="success" />
      <StatCard label="Agentes offline" :value="metrics.agentsOffline" tone="danger" />
      <StatCard label="Certificados vencendo" :value="metrics.expiringCertificates" tone="warning" />
    </div>

    <section class="panel latest">
      <div class="section-title">
        <h2>Últimas sincronizações</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Empresa</th>
              <th>Comando</th>
              <th>Status</th>
              <th>Criado em</th>
              <th>Finalizado em</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sync in latestSyncs" :key="sync.uuid">
              <td>{{ sync.company?.legal_name ?? 'Sem empresa' }}</td>
              <td class="mono">{{ sync.type }}</td>
              <td><StatusBadge :status="sync.status" /></td>
              <td>{{ sync.created_at ?? '-' }}</td>
              <td>{{ sync.completed_at ?? sync.failed_at ?? '-' }}</td>
            </tr>
            <tr v-if="latestSyncs.length === 0">
              <td colspan="5" class="muted">Nenhuma sincronização registrada.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppLayout>
</template>

<style scoped>
.latest {
  margin-top: 18px;
}
</style>
