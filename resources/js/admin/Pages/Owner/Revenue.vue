<template>
  <div class="p-8 space-y-8 bg-slate-50/50 min-h-screen">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Finances</h1>
        <p class="text-slate-500">Suivi de vos revenus et performances immobilières.</p>
      </div>
      <div class="bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="relative flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500" />
        </div>
        <span class="text-sm font-semibold text-slate-700">Live: {{ currentTime }}</span>
      </div>
    </div>

    <!-- Bento Grid Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <StatCard
        title="Revenu Total"
        :value="formatPrice(stats.totalRevenue)"
        :trend="stats.trends.totalRevenue"
        :icon="Wallet"
        color="bg-blue-600"
      />
      <StatCard
        title="Réservations"
        :value="formatNumber(stats.totalBookings)"
        :trend="stats.trends.bookings"
        :icon="CalendarCheck"
        color="bg-purple-600"
      />
      <StatCard
        title="Taux d'occupation"
        :value="`${stats.occupationRate}%`"
        :trend="stats.trends.occupation"
        :icon="TrendingUp"
        color="bg-orange-600"
      />
      <StatCard
        title="Biens Actifs"
        :value="formatNumber(stats.activeProperties)"
        :trend="stats.trends.properties"
        :icon="Home"
        color="bg-indigo-600"
      />
    </div>

    <!-- Graphique de Croissance -->
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
      <h3 class="font-bold text-lg mb-6 text-slate-900">Évolution du CA</h3>
      <div v-if="loading" class="h-[350px] flex items-center justify-center">
        <div class="text-slate-400">Chargement des données...</div>
      </div>
      <div v-else-if="chartData.length === 0" class="h-[350px] flex items-center justify-center">
        <div class="text-slate-400">Aucune donnée disponible</div>
      </div>
      <div v-else class="h-[350px] w-full">
        <apexchart
          type="area"
          height="350"
          :options="chartOptions"
          :series="chartSeries"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import StatCard from '../../Components/Owner/StatCard.vue';
import OwnerLayout from '../../Components/Layouts/OwnerLayout.vue';
import { Wallet, TrendingUp, CalendarCheck, Home } from 'lucide-vue-next';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  stats?: {
    totalRevenue: number;
    totalBookings: number;
    occupationRate: number;
    activeProperties: number;
    trends: {
      totalRevenue: number;
      bookings: number;
      occupation: number;
      properties: number;
    };
    chartData?: Array<{ month: string; total: number }>;
  };
}>();

const loading = ref(true);
const chartData = ref<Array<{ month: string; total: number }>>([]);
const currentTime = ref<string>(new Date().toLocaleTimeString());
let clockTimer: ReturnType<typeof setInterval> | undefined;
let statsPollingTimer: ReturnType<typeof setInterval> | undefined;

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

const stats = computed(() => {
  return props.stats || {
    totalRevenue: 0,
    totalBookings: 0,
    occupationRate: 0,
    activeProperties: 0,
    trends: {
      totalRevenue: 0,
      bookings: 0,
      occupation: 0,
      properties: 0,
    },
    chartData: [],
  };
});

// Formatage des prix en FCFA
const formatPrice = (value: number | string): string => {
  const num = typeof value === 'string' ? parseFloat(value.replace(/[^\d.,]/g, '').replace(',', '.')) || 0 : value;
  return new Intl.NumberFormat('fr-FR').format(num);
};

// Formatage des nombres
const formatNumber = (value: number | string): string => {
  const num = typeof value === 'string' ? parseInt(value.replace(/\D/g, '')) || 0 : value;
  return new Intl.NumberFormat('fr-FR').format(num);
};

// Configuration du graphique
const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 350,
    toolbar: {
      show: false,
    },
    zoom: {
      enabled: false,
    },
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    curve: 'monotone',
    width: 3,
    colors: ['#2563eb'],
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.1,
      opacityTo: 0,
      stops: [0, 95],
    },
  },
  grid: {
    borderColor: '#f1f5f9',
    strokeDashArray: 3,
    xaxis: {
      lines: {
        show: false,
      },
    },
    yaxis: {
      lines: {
        show: true,
      },
    },
  },
  xaxis: {
    categories: chartData.value.map((d) => d.month),
    axisBorder: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
    labels: {
      style: {
        colors: '#64748b',
        fontSize: '12px',
      },
    },
  },
  yaxis: {
    axisBorder: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
    labels: {
      style: {
        colors: '#64748b',
        fontSize: '12px',
      },
      formatter: (value: number) => `${value / 1000}k`,
    },
  },
  tooltip: {
    style: {
      fontSize: '12px',
    },
    y: {
      formatter: (value: number) => `${value.toLocaleString('fr-FR')} FCFA`,
    },
  },
  colors: ['#2563eb'],
}));

const chartSeries = computed(() => [
  {
    name: 'Revenu',
    data: chartData.value.map((d) => d.total),
  },
]);

onMounted(() => {
  clockTimer = setInterval(() => {
    currentTime.value = new Date().toLocaleTimeString();
  }, 1000);

  if (stats.value.chartData && stats.value.chartData.length > 0) {
    chartData.value = stats.value.chartData;
  } else {
    chartData.value = [];
  }
  loading.value = false;

  statsPollingTimer = setInterval(() => {
    router.reload({ only: ['stats'] });
  }, 60000);
});

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer);
  if (statsPollingTimer) clearInterval(statsPollingTimer);
});
</script>

