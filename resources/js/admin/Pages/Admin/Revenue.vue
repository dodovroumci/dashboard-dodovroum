<template>
  <div class="p-8 space-y-8 bg-[#f8fafc] min-h-screen font-sans selection:bg-blue-100">
    <header class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
      <div class="animate-in fade-in slide-in-from-left duration-700">
        <h1 class="text-4xl font-extrabold tracking-tight text-slate-950">
          Dodovroum <span class="text-blue-600">Analytics</span>
        </h1>
        <p class="text-slate-500 mt-1 font-medium">Pilotage des revenus et performances de la flotte.</p>
      </div>

      <div class="flex items-center gap-3 bg-white/60 backdrop-blur-md px-5 py-2.5 rounded-2xl shadow-sm border border-white flex-shrink-0 animate-in fade-in slide-in-from-right duration-700">
        <div class="relative flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
        </div>
        <span class="text-sm font-semibold text-slate-700">Live: {{ currentTime }}</span>
      </div>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <StatCard
        v-for="(card, index) in statCards"
        :key="card.title"
        v-bind="card"
        :class="`animate-in fade-in zoom-in-95 duration-500`"
        :style="{ animationDelay: (index * 100) + 'ms' }"
        class="hover:scale-[1.02] transition-transform duration-300"
      />
    </div>

    <div class="grid grid-cols-1 gap-6">
      <div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] border border-white shadow-xl shadow-slate-200/50 animate-in fade-in slide-in-from-bottom-10 duration-1000">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="font-black text-xl text-slate-900 tracking-tight">Flux de Trésorerie</h3>
            <p class="text-sm text-slate-500">Évolution mensuelle des commissions (FCFA)</p>
          </div>
          <div class="flex gap-2">
            <a
              :href="exportCsvHref"
              class="px-4 py-2 text-xs font-bold bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors inline-flex items-center"
            >
              Export CSV
            </a>
          </div>
        </div>

        <div v-if="loading" class="h-[350px] flex flex-col items-center justify-center gap-4">
          <div class="w-12 h-12 border-4 border-blue-600/20 border-t-blue-600 rounded-full animate-spin"></div>
          <p class="text-slate-400 font-medium animate-pulse">Analyse des données...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="isEmptyState" class="h-[380px] flex flex-col items-center justify-center gap-6 px-8">
          <div class="relative w-64 h-48 flex items-center justify-center">
            <!-- Illustration SVG simple -->
            <svg class="w-full h-full text-slate-300" fill="none" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
              <!-- Graphique vide stylisé -->
              <rect x="50" y="50" width="300" height="200" rx="8" fill="currentColor" opacity="0.1"/>
              <line x1="50" y1="250" x2="350" y2="250" stroke="currentColor" stroke-width="2" opacity="0.3"/>
              <line x1="50" y1="250" x2="50" y2="50" stroke="currentColor" stroke-width="2" opacity="0.3"/>
              <!-- Points de données vides -->
              <circle cx="100" cy="200" r="4" fill="currentColor" opacity="0.2"/>
              <circle cx="150" cy="180" r="4" fill="currentColor" opacity="0.2"/>
              <circle cx="200" cy="160" r="4" fill="currentColor" opacity="0.2"/>
              <circle cx="250" cy="140" r="4" fill="currentColor" opacity="0.2"/>
              <circle cx="300" cy="120" r="4" fill="currentColor" opacity="0.2"/>
            </svg>
          </div>
          <div class="text-center space-y-2">
            <h4 class="text-lg font-bold text-slate-700">Aucune donnée disponible</h4>
            <p class="text-sm text-slate-500 max-w-md">
              Les données de revenus apparaîtront ici une fois que des réservations confirmées seront enregistrées.
            </p>
          </div>
        </div>

        <!-- Graphique avec données -->
        <div v-else class="h-[380px] w-full">
          <apexchart
            type="area"
            height="100%"
            :options="chartOptions"
            :series="chartSeries"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import StatCard from '../../Components/Owner/StatCard.vue';
import AdminLayout from '../../Components/Layouts/AdminLayout.vue';
import { Wallet, TrendingUp, CalendarCheck, Home } from 'lucide-vue-next';

defineOptions({
  layout: AdminLayout,
});

/** --- Interfaces --- */
interface DashboardStats {
  totalRevenue: number;
  totalBookings: number;
  occupationRate: number;
  activeProperties: number;
  trends: Record<string, number>;
  chartData: Array<{ month: string; total: number }>;
}

/** --- Props & State --- */
const props = defineProps<{ stats?: Partial<DashboardStats> }>();
const loading = ref<boolean>(true);
const chartData = ref<Array<{ month: string; total: number }>>([]);

watch(
  () => props.stats?.chartData,
  (next) => {
    if (next && Array.isArray(next) && next.length > 0) {
      chartData.value = next;
    } else {
      chartData.value = [];
    }
  },
  { deep: true },
);
const currentTime = ref<string>(new Date().toLocaleTimeString());
let timer: ReturnType<typeof setInterval> | undefined;
let dataPollingTimer: ReturnType<typeof setInterval> | undefined;

/** URL d’export : Ziggy expose `window.route` via @routes ; sinon URL statique (zéro risque). */
const FALLBACK_ADMIN_EXPORT_CSV = '/admin/revenue/export.csv';
const exportCsvHref = computed((): string => {
  if (typeof window === 'undefined') {
    return FALLBACK_ADMIN_EXPORT_CSV;
  }
  const routeFn = (window as Window & { route?: (name: string, ...args: unknown[]) => string }).route;
  if (typeof routeFn === 'function') {
    try {
      return routeFn('admin.revenue.export');
    } catch {
      return FALLBACK_ADMIN_EXPORT_CSV;
    }
  }
  return FALLBACK_ADMIN_EXPORT_CSV;
});

/** --- Logic --- */
const stats = computed(() => ({
  totalRevenue: props.stats?.totalRevenue ?? 0,
  totalBookings: props.stats?.totalBookings ?? 0,
  occupationRate: props.stats?.occupationRate ?? 0,
  activeProperties: props.stats?.activeProperties ?? 0,
  trends: props.stats?.trends ?? { totalRevenue: 0, bookings: 0, occupation: 0, properties: 0 },
}));

const statCards = computed(() => [
  { title: "Commissions DodoVroum (10%)", value: formatPrice(stats.value.totalRevenue), trend: stats.value.trends.totalRevenue, icon: Wallet, color: "bg-blue-600" },
  { title: "Réservations", value: stats.value.totalBookings, trend: stats.value.trends.bookings, icon: CalendarCheck, color: "bg-indigo-600" },
  { title: "Occupation", value: `${stats.value.occupationRate}%`, trend: stats.value.trends.occupation, icon: TrendingUp, color: "bg-emerald-600" },
  { title: "Biens Actifs", value: stats.value.activeProperties, trend: stats.value.trends.properties, icon: Home, color: "bg-slate-900" },
]);

const formatPrice = (v: number) => new Intl.NumberFormat('fr-FR').format(v) + ' FCFA';

/** --- Chart Configuration (Dribbble Style) --- */
const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    fontFamily: 'Plus Jakarta Sans, sans-serif',
    animations: { enabled: true, easing: 'easeinout', speed: 800 },
  },
  stroke: { curve: 'smooth', width: 4, colors: ['#2563eb'] },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.45,
      opacityTo: 0.05,
      stops: [0, 100],
      colorStops: [{ offset: 0, color: "#2563eb", opacity: 0.4 }, { offset: 100, color: "#2563eb", opacity: 0 }]
    },
  },
  markers: { size: 5, colors: ['#2563eb'], strokeColors: '#fff', strokeWidth: 3, hover: { size: 7 } },
  grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { left: 20, right: 20 } },
  xaxis: {
    categories: chartData.value.map((d: { month: string; total: number }) => d.month),
    labels: { style: { colors: '#64748b', fontWeight: 600 } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      style: { colors: '#64748b', fontWeight: 600 },
      formatter: (v: number) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v
    },
  },
  tooltip: {
    theme: 'dark',
    x: { show: false },
    y: { formatter: (v: number) => `${v.toLocaleString('fr-FR')} FCFA` }
  },
}));

const chartSeries = computed(() => [{ name: 'Commissions (10%)', data: chartData.value.map((d: { month: string; total: number }) => d.total) }]);

// Vérifier si les données sont réellement vides (tous les totaux à 0)
const isEmptyState = computed(() => {
  if (!chartData.value || chartData.value.length === 0) return true;
  return chartData.value.every((d: { month: string; total: number }) => d.total === 0);
});

onMounted(() => {
  // Timer pour l'heure en temps réel
  timer = setInterval(() => { 
    currentTime.value = new Date().toLocaleTimeString(); 
  }, 1000);
  
  // Initialiser les données
  if (props.stats?.chartData && Array.isArray(props.stats.chartData) && props.stats.chartData.length > 0) {
    chartData.value = props.stats.chartData;
  } else {
    // Si pas de données, laisser vide pour afficher l'Empty State
    chartData.value = [];
  }
  
  // Désactiver le loading après un court délai
  setTimeout(() => { 
    loading.value = false; 
  }, 600);
  
  // Polling des données toutes les 60 secondes
  dataPollingTimer = setInterval(() => {
    router.reload({ 
      only: ['stats'],
    });
  }, 60000); // 60 secondes
});

onUnmounted(() => {
  if (timer) {
    clearInterval(timer);
  }
  if (dataPollingTimer) {
    clearInterval(dataPollingTimer);
  }
});
</script>

<style scoped>
/* Custom animations for the "Dribbble" feel */
.animate-in {
  animation-fill-mode: forwards;
}
</style>