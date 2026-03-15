<template>
  <div class="p-8 space-y-8 bg-slate-50/50 min-h-screen">
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Finances</h1>
        <p class="text-slate-500">Suivi de vos revenus et performances immobilières.</p>
      </div>
      <div class="bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
        <span class="text-sm font-medium">Actualisé en temps réel (GMT)</span>
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
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StatCard from '../../Components/Owner/StatCard.vue';
import OwnerLayout from '../../Components/Layouts/OwnerLayout.vue';
import { Wallet, TrendingUp, CalendarCheck, Home } from 'lucide-vue-next';

defineOptions({
  layout: OwnerLayout,
});

const page = usePage();
const loading = ref(true);
const chartData = ref<Array<{ month: string; total: number }>>([]);

// Props depuis le backend
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

// Stats par défaut si non fournies
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
  // Initialiser les données du graphique depuis les props ou utiliser des données par défaut
  if (stats.value.chartData && stats.value.chartData.length > 0) {
    chartData.value = stats.value.chartData;
  } else {
    // Données par défaut si aucune donnée n'est fournie
    chartData.value = [
      { month: 'Oct', total: 450000 },
      { month: 'Nov', total: 520000 },
      { month: 'Dec', total: 890000 },
      { month: 'Jan', total: 710000 },
    ];
  }
  loading.value = false;
});
</script>

