<script setup lang="ts">
import AppLayout from '@/Components/Layout/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Agent, Paginated } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
  agent: Agent;
  diagnostics: Paginated<{ id: number; event: string; occurred_at: string | null; metadata?: Record<string, unknown> }>;
}>();
</script>

<template>
  <Head title="Diagnóstico do agente" />
  <AppLayout :title="`Diagnóstico - ${agent.name}`" subtitle="Eventos técnicos recebidos do agente local.">
    <Link class="button" href="/agents">Voltar</Link>

    <section class="panel diagnostics">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Evento</th>
              <th>Data/hora</th>
              <th>Detalhes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in diagnostics.data" :key="item.id">
              <td class="mono">{{ item.event }}</td>
              <td>{{ item.occurred_at ?? '-' }}</td>
              <td>
                <pre>{{ JSON.stringify(item.metadata ?? {}, null, 2) }}</pre>
              </td>
            </tr>
            <tr v-if="diagnostics.data.length === 0">
              <td colspan="3" class="muted">Nenhum diagnóstico registrado.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="diagnostics.links" />
    </section>
  </AppLayout>
</template>

<style scoped>
.diagnostics {
  margin-top: 16px;
}

pre {
  margin: 0;
  max-width: 680px;
  white-space: pre-wrap;
}
</style>
