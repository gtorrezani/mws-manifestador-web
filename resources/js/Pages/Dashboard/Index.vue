<script setup lang="ts">
import AppLayout from '@/Components/Layout/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { AgentCommand } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Tone = 'neutral' | 'success' | 'warning' | 'danger';

const props = defineProps<{
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

const totalAgents = computed(() => props.metrics.agentsOnline + props.metrics.agentsOffline);
const totalPending = computed(
  () =>
    props.metrics.pendingAcknowledgement +
    props.metrics.pendingConclusiveManifestation +
    props.metrics.manifestationErrors +
    props.metrics.expiringCertificates,
);
const xmlDownloadRate = computed(() => percentage(props.metrics.xmlDownloaded, props.metrics.documentsFound));
const agentOnlineRate = computed(() => percentage(props.metrics.agentsOnline, totalAgents.value));
const criticalIssues = computed(() => props.metrics.manifestationErrors + props.metrics.agentsOffline);

const healthTone = computed<Tone>(() => {
  if (criticalIssues.value > 0) {
    return 'danger';
  }

  if (totalPending.value > 0) {
    return 'warning';
  }

  return 'success';
});

const healthLabel = computed(() => {
  if (criticalIssues.value > 0) {
    return 'Atenção necessária';
  }

  if (totalPending.value > 0) {
    return 'Pendências em aberto';
  }

  return 'Operação estável';
});

const overviewCards = computed(() => [
  {
    label: 'Notas encontradas',
    value: props.metrics.documentsFound,
    detail: `${props.metrics.xmlDownloaded} XMLs disponíveis`,
    tone: 'neutral' as Tone,
  },
  {
    label: 'Pendências',
    value: totalPending.value,
    detail: 'Ciência, conclusão, certificados e erros',
    tone: totalPending.value > 0 ? ('warning' as Tone) : ('success' as Tone),
  },
  {
    label: 'Agentes online',
    value: props.metrics.agentsOnline,
    detail: totalAgents.value > 0 ? `${agentOnlineRate.value}% da base ativa` : 'Nenhum agente cadastrado',
    tone: props.metrics.agentsOffline > 0 ? ('danger' as Tone) : ('success' as Tone),
  },
  {
    label: 'Falhas de manifestação',
    value: props.metrics.manifestationErrors,
    detail: props.metrics.manifestationErrors > 0 ? 'Requer análise operacional' : 'Sem erros recentes',
    tone: props.metrics.manifestationErrors > 0 ? ('danger' as Tone) : ('success' as Tone),
  },
]);

const pendingBars = computed(() => {
  const items = [
    {
      label: 'Ciência pendente',
      value: props.metrics.pendingAcknowledgement,
      color: '#a15c07',
    },
    {
      label: 'Manifestação conclusiva',
      value: props.metrics.pendingConclusiveManifestation,
      color: '#b65c2d',
    },
    {
      label: 'Erros',
      value: props.metrics.manifestationErrors,
      color: '#b42318',
    },
    {
      label: 'Certificados vencendo',
      value: props.metrics.expiringCertificates,
      color: '#8a6f20',
    },
  ];
  const max = Math.max(...items.map((item) => item.value), 1);

  return items.map((item) => ({
    ...item,
    percent: item.value > 0 ? Math.max(8, Math.round((item.value / max) * 100)) : 0,
  }));
});

const commandStatusChart = computed(() => {
  const counts = props.latestSyncs.reduce<Record<string, number>>((accumulator, sync) => {
    const status = String(sync.status ?? 'pending').toLowerCase();
    accumulator[status] = (accumulator[status] ?? 0) + 1;
    return accumulator;
  }, {});
  const total = Math.max(props.latestSyncs.length, 1);

  return [
    { status: 'completed', label: 'Concluídos', color: '#1f7a4d' },
    { status: 'pending', label: 'Pendentes', color: '#a15c07' },
    { status: 'processing', label: 'Processando', color: '#256f5c' },
    { status: 'failed', label: 'Falhas', color: '#b42318' },
  ].map((item) => ({
    ...item,
    value: counts[item.status] ?? 0,
    percent: Math.round(((counts[item.status] ?? 0) / total) * 100),
  }));
});

const recentActivity = computed(() => props.latestSyncs.slice(0, 6));

function percentage(value: number, total: number): number {
  if (total <= 0) {
    return 0;
  }

  return Math.round((value / total) * 100);
}

function commandTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    sync_fiscal_documents: 'Sincronização DFe',
    test_sefaz_connectivity: 'Teste SEFAZ',
    test_certificate: 'Teste de certificado',
    list_certificates: 'Inventário A3',
    download_xml_by_access_key: 'Download XML',
  };

  return labels[type] ?? type.replaceAll('_', ' ');
}

function formatDateTime(value: string | null): string {
  if (!value) {
    return '-';
  }

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout title="Dashboard">
    <section class="hero-panel" :class="healthTone">
      <div>
        <span class="eyebrow">Visão operacional</span>
        <h2>{{ healthLabel }}</h2>
        <p>
          {{ metrics.documentsFound }} notas monitoradas, {{ metrics.xmlDownloaded }} XMLs baixados e
          {{ totalPending }} ponto(s) de atenção no momento.
        </p>
      </div>
      <div class="hero-score">
        <span>{{ xmlDownloadRate }}%</span>
        <strong>XMLs baixados</strong>
      </div>
    </section>

    <section class="metric-grid">
      <article v-for="card in overviewCards" :key="card.label" class="metric-card" :class="card.tone">
        <span>{{ card.label }}</span>
        <strong>{{ card.value }}</strong>
        <small>{{ card.detail }}</small>
      </article>
    </section>

    <section class="dashboard-grid">
      <article class="panel chart-panel">
        <div class="section-title">
          <h2>Documentos e XMLs</h2>
          <span class="panel-tag">{{ metrics.xmlDownloaded }} / {{ metrics.documentsFound }}</span>
        </div>

        <div class="donut-layout">
          <div class="donut" aria-label="Percentual de XMLs baixados">
            <svg viewBox="0 0 120 120" role="img">
              <circle class="donut-track" cx="60" cy="60" r="46" pathLength="100" />
              <circle
                class="donut-progress"
                cx="60"
                cy="60"
                r="46"
                pathLength="100"
                :stroke-dasharray="`${xmlDownloadRate} 100`"
              />
            </svg>
            <div>
              <strong>{{ xmlDownloadRate }}%</strong>
              <span>baixados</span>
            </div>
          </div>

          <dl class="summary-list">
            <div>
              <dt>Notas encontradas</dt>
              <dd>{{ metrics.documentsFound }}</dd>
            </div>
            <div>
              <dt>XMLs disponíveis</dt>
              <dd>{{ metrics.xmlDownloaded }}</dd>
            </div>
            <div>
              <dt>Sem XML disponível</dt>
              <dd>{{ Math.max(metrics.documentsFound - metrics.xmlDownloaded, 0) }}</dd>
            </div>
          </dl>
        </div>
      </article>

      <article class="panel chart-panel">
        <div class="section-title">
          <h2>Pendências</h2>
          <span class="panel-tag">{{ totalPending }} abertas</span>
        </div>

        <div class="bar-list">
          <div v-for="bar in pendingBars" :key="bar.label" class="bar-row">
            <div class="bar-label">
              <span>{{ bar.label }}</span>
              <strong>{{ bar.value }}</strong>
            </div>
            <div class="bar-track">
              <span class="bar-fill" :style="{ width: `${bar.percent}%`, backgroundColor: bar.color }" />
            </div>
          </div>
        </div>
      </article>

      <article class="panel agents-panel">
        <div class="section-title">
          <h2>Agentes</h2>
          <span class="panel-tag">{{ totalAgents }} cadastrados</span>
        </div>
        <div class="agent-meter">
          <div class="meter-bar">
            <span :style="{ width: `${agentOnlineRate}%` }" />
          </div>
          <strong>{{ agentOnlineRate }}% online</strong>
        </div>
        <div class="agent-split">
          <div>
            <span>Online</span>
            <strong>{{ metrics.agentsOnline }}</strong>
          </div>
          <div>
            <span>Offline</span>
            <strong>{{ metrics.agentsOffline }}</strong>
          </div>
        </div>
      </article>

      <article class="panel command-panel">
        <div class="section-title">
          <h2>Últimos comandos</h2>
          <span class="panel-tag">{{ latestSyncs.length }} recentes</span>
        </div>
        <div class="status-bars">
          <div v-for="item in commandStatusChart" :key="item.status" class="status-column">
            <div class="status-column-track">
              <span
                :style="{ height: `${Math.max(item.percent, item.value > 0 ? 8 : 0)}%`, backgroundColor: item.color }"
              />
            </div>
            <strong>{{ item.value }}</strong>
            <span>{{ item.label }}</span>
          </div>
        </div>
      </article>
    </section>

    <section class="panel latest">
      <div class="section-title">
        <h2>Atividade recente</h2>
      </div>

      <div v-if="recentActivity.length > 0" class="activity-list">
        <article v-for="sync in recentActivity" :key="sync.uuid" class="activity-item">
          <div class="activity-dot" :class="String(sync.status ?? 'pending').toLowerCase()" />
          <div>
            <strong>{{ commandTypeLabel(sync.type) }}</strong>
            <span>{{ sync.company?.legal_name ?? 'Sem empresa' }}</span>
          </div>
          <StatusBadge :status="sync.status" />
          <time>{{ formatDateTime(sync.completed_at ?? sync.failed_at ?? sync.created_at) }}</time>
        </article>
      </div>

      <div v-else class="empty-state">Nenhuma sincronização registrada.</div>
    </section>
  </AppLayout>
</template>

<style scoped>
.hero-panel {
  align-items: center;
  background: linear-gradient(135deg, #ffffff 0%, #eef7f4 100%);
  border: 1px solid #cfe0d8;
  border-radius: 8px;
  display: flex;
  gap: 24px;
  justify-content: space-between;
  margin-bottom: 18px;
  padding: 22px 24px;
}

.hero-panel h2 {
  font-size: 24px;
  margin: 4px 0 6px;
}

.hero-panel p {
  color: var(--muted);
  margin: 0;
}

.hero-panel.warning {
  background: linear-gradient(135deg, #ffffff 0%, #fff7ed 100%);
  border-color: #fed7aa;
}

.hero-panel.danger {
  background: linear-gradient(135deg, #ffffff 0%, #fff1f0 100%);
  border-color: #fecdca;
}

.eyebrow,
.panel-tag {
  color: var(--primary);
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
}

.hero-score {
  align-items: center;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  display: grid;
  min-width: 150px;
  padding: 14px 18px;
  text-align: center;
}

.hero-score span {
  font-size: 34px;
  font-weight: 850;
}

.hero-score strong {
  color: var(--muted);
  font-size: 12px;
  text-transform: uppercase;
}

.metric-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-bottom: 18px;
}

.metric-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-top: 4px solid #7b8a83;
  border-radius: 8px;
  display: grid;
  gap: 8px;
  min-height: 132px;
  padding: 16px;
}

.metric-card span,
.metric-card small {
  color: var(--muted);
}

.metric-card span {
  font-weight: 750;
}

.metric-card strong {
  font-size: 34px;
  line-height: 1;
}

.metric-card.success {
  border-top-color: var(--success);
}

.metric-card.warning {
  border-top-color: var(--warning);
}

.metric-card.danger {
  border-top-color: var(--danger);
}

.dashboard-grid {
  display: grid;
  gap: 18px;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
}

.chart-panel,
.agents-panel,
.command-panel {
  min-height: 260px;
}

.donut-layout {
  align-items: center;
  display: grid;
  gap: 18px;
  grid-template-columns: 180px minmax(0, 1fr);
}

.donut {
  display: grid;
  place-items: center;
  position: relative;
}

.donut svg {
  height: 170px;
  transform: rotate(-90deg);
  width: 170px;
}

.donut-track,
.donut-progress {
  fill: none;
  stroke-width: 14;
}

.donut-track {
  stroke: #e7ebe6;
}

.donut-progress {
  stroke: var(--primary);
  stroke-linecap: round;
}

.donut > div {
  display: grid;
  place-items: center;
  position: absolute;
}

.donut strong {
  font-size: 30px;
}

.donut span {
  color: var(--muted);
  font-size: 12px;
  font-weight: 750;
  text-transform: uppercase;
}

.summary-list {
  display: grid;
  gap: 10px;
  margin: 0;
}

.summary-list div {
  align-items: center;
  background: #f7f8f6;
  border: 1px solid var(--border);
  border-radius: 8px;
  display: flex;
  justify-content: space-between;
  padding: 12px 14px;
}

.summary-list dt {
  color: var(--muted);
  font-weight: 700;
}

.summary-list dd {
  font-size: 20px;
  font-weight: 850;
  margin: 0;
}

.bar-list {
  display: grid;
  gap: 16px;
}

.bar-label {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 7px;
}

.bar-label span {
  color: #41504a;
  font-weight: 750;
}

.bar-track {
  background: #eef1ed;
  border-radius: 999px;
  height: 12px;
  overflow: hidden;
}

.bar-fill {
  border-radius: inherit;
  display: block;
  height: 100%;
}

.agent-meter {
  display: grid;
  gap: 10px;
  margin-top: 22px;
}

.meter-bar {
  background: #eef1ed;
  border-radius: 999px;
  height: 18px;
  overflow: hidden;
}

.meter-bar span {
  background: var(--success);
  border-radius: inherit;
  display: block;
  height: 100%;
}

.agent-split {
  display: grid;
  gap: 12px;
  grid-template-columns: 1fr 1fr;
  margin-top: 22px;
}

.agent-split div {
  background: #f7f8f6;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px;
}

.agent-split span {
  color: var(--muted);
  display: block;
  font-weight: 750;
  margin-bottom: 8px;
}

.agent-split strong {
  font-size: 28px;
}

.status-bars {
  align-items: end;
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(4, 1fr);
  min-height: 190px;
}

.status-column {
  display: grid;
  gap: 7px;
  justify-items: center;
}

.status-column-track {
  align-items: end;
  background: #eef1ed;
  border-radius: 999px 999px 6px 6px;
  display: flex;
  height: 128px;
  overflow: hidden;
  width: 34px;
}

.status-column-track span {
  border-radius: inherit;
  display: block;
  width: 100%;
}

.status-column > span {
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  text-align: center;
}

.latest {
  margin-top: 18px;
}

.activity-list {
  display: grid;
  gap: 10px;
}

.activity-item {
  align-items: center;
  background: #fbfcfa;
  border: 1px solid var(--border);
  border-radius: 8px;
  display: grid;
  gap: 12px;
  grid-template-columns: 12px minmax(0, 1fr) auto auto;
  padding: 12px;
}

.activity-dot {
  background: #7b8a83;
  border-radius: 999px;
  height: 10px;
  width: 10px;
}

.activity-dot.completed,
.activity-dot.success {
  background: var(--success);
}

.activity-dot.pending,
.activity-dot.processing {
  background: var(--warning);
}

.activity-dot.failed {
  background: var(--danger);
}

.activity-item strong,
.activity-item span {
  display: block;
}

.activity-item span,
.activity-item time {
  color: var(--muted);
}

.activity-item time {
  white-space: nowrap;
}

.empty-state {
  background: #f7f8f6;
  border: 1px dashed var(--border);
  border-radius: 8px;
  color: var(--muted);
  padding: 24px;
  text-align: center;
}

@media (max-width: 1180px) {
  .metric-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 720px) {
  .hero-panel,
  .donut-layout,
  .activity-item {
    align-items: stretch;
    grid-template-columns: 1fr;
  }

  .hero-panel {
    flex-direction: column;
  }

  .metric-grid,
  .agent-split {
    grid-template-columns: 1fr;
  }

  .activity-dot {
    display: none;
  }
}
</style>
