<template>
  <div class="space-y-6">
    <!-- Cartes de statistiques (KPIs) -->
    <section class="grid gap-3 sm:gap-4 grid-cols-2 sm:grid-cols-2 xl:grid-cols-5">
      <StatsCard title="Résidences" :value="formatNumber(stats.residences.total)">
        <template #icon>
          <Building2 class="w-5 h-5" />
        </template>
      </StatsCard>
      <StatsCard title="Véhicules" :value="formatNumber(stats.vehicles.total)">
        <template #icon>
          <Truck class="w-5 h-5" />
        </template>
      </StatsCard>
      <StatsCard title="Réservations actives" :value="formatNumber(stats.bookings.active)">
        <template #icon>
          <Calendar class="w-5 h-5" />
        </template>
      </StatsCard>
      <StatsCard title="Réservations en attente" :value="formatNumber(stats.bookings.pending)">
        <template #icon>
          <Clock class="w-5 h-5" />
        </template>
      </StatsCard>
      <StatsCard title="Revenus du mois" :value="`${formatPrice(stats.revenue.month)} CFA`">
        <template #icon>
          <DollarSign class="w-5 h-5" />
        </template>
      </StatsCard>
    </section>

    <!-- Réservations récentes -->
    <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
        <h2 class="text-base sm:text-lg font-semibold text-slate-900">Réservations récentes</h2>
        <Link
          :href="route('admin.bookings.index')"
          class="text-sm text-blue-600 hover:text-blue-700 font-medium"
        >
          Voir toutes →
        </Link>
      </div>
      <div v-if="recentBookings.length === 0" class="text-center py-8">
        <Calendar class="w-12 h-12 text-slate-300 mx-auto mb-2" />
        <p class="text-slate-500">Aucune réservation récente</p>
      </div>
      <div v-else class="table-scroll-wrap">
        <table class="w-full min-w-[600px]">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Client</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Bien</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Dates</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="booking in recentBookings" :key="booking.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-sm font-medium text-slate-900">
                {{ booking.customer }}
              </td>
              <td class="px-4 py-3 text-sm text-slate-600">
                {{ booking.property }}
              </td>
              <td class="px-4 py-3 text-sm text-slate-600">
                {{ booking.dates }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="text-xs px-3 py-1 rounded-full font-medium"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="route('admin.bookings.show', booking.id)"
                  class="text-blue-600 hover:text-blue-700 text-sm font-medium"
                >
                  Voir détails →
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Résumé des biens et Revenus -->
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Résumé des résidences -->
      <section class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
            <Building2 class="w-5 h-5" />
            Résidences
          </h2>
          <Link
            :href="route('admin.residences.index')"
            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
          >
            Voir toutes →
          </Link>
        </div>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-slate-500">Total</p>
              <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats.residences.total) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Actives</p>
              <p class="text-2xl font-semibold text-emerald-600">{{ formatNumber(stats.residences.active) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Inactives</p>
              <p class="text-2xl font-semibold text-slate-600">{{ formatNumber(stats.residences.inactive) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Sans photo</p>
              <p class="text-2xl font-semibold text-amber-600">{{ formatNumber(stats.residences.withoutImage) }}</p>
            </div>
          </div>
          <div v-if="stats.residences.last" class="pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500 mb-1">Dernière résidence ajoutée</p>
            <Link
              :href="route('admin.residences.show', stats.residences.last.id)"
              class="text-sm font-medium text-blue-600 hover:text-blue-700"
            >
              {{ stats.residences.last.name }}
            </Link>
          </div>
          <Link
            :href="route('admin.residences.create')"
            class="block w-full mt-4 px-4 py-2 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 transition-colors"
          >
            + Ajouter une résidence
          </Link>
        </div>
      </section>

      <!-- Résumé des véhicules -->
      <section class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
            <Truck class="w-5 h-5" />
            Véhicules
          </h2>
          <Link
            :href="route('admin.vehicles.index')"
            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
          >
            Voir tous →
          </Link>
        </div>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-slate-500">Total</p>
              <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats.vehicles.total) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Disponibles</p>
              <p class="text-2xl font-semibold text-emerald-600">{{ formatNumber(stats.vehicles.available) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Indisponibles</p>
              <p class="text-2xl font-semibold text-slate-600">{{ formatNumber(stats.vehicles.unavailable) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Sans photo</p>
              <p class="text-2xl font-semibold text-amber-600">{{ formatNumber(stats.vehicles.withoutImage) }}</p>
            </div>
          </div>
          <div v-if="stats.vehicles.last" class="pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500 mb-1">Dernier véhicule ajouté</p>
            <Link
              :href="route('admin.vehicles.show', stats.vehicles.last.id)"
              class="text-sm font-medium text-blue-600 hover:text-blue-700"
            >
              {{ stats.vehicles.last.name }}
            </Link>
          </div>
          <div v-if="stats.vehicles.withoutPrice > 0" class="pt-2">
            <p class="text-sm text-amber-600">⚠️ {{ stats.vehicles.withoutPrice }} véhicule(s) sans prix</p>
          </div>
          <Link
            :href="route('admin.vehicles.create')"
            class="block w-full mt-4 px-4 py-2 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 transition-colors"
          >
            + Ajouter un véhicule
          </Link>
        </div>
      </section>
    </div>

    <!-- Revenus & Performance -->
    <section class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
        <DollarSign class="w-5 h-5" />
        Revenus & Performance
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <p class="text-sm text-slate-500 mb-1">Revenus du jour</p>
          <p class="text-2xl font-semibold text-slate-900">{{ formatPrice(stats.revenue.today) }} CFA</p>
        </div>
        <div>
          <p class="text-sm text-slate-500 mb-1">Revenus du mois</p>
          <p class="text-2xl font-semibold text-emerald-600">{{ formatPrice(stats.revenue.month) }} CFA</p>
        </div>
        <div>
          <p class="text-sm text-slate-500 mb-1">Revenus cumulés</p>
          <p class="text-2xl font-semibold text-blue-600">{{ formatPrice(stats.revenue.total) }} CFA</p>
        </div>
        <div>
          <p class="text-sm text-slate-500 mb-1">Réservations payées</p>
          <p class="text-2xl font-semibold text-emerald-600">{{ formatNumber(stats.revenue.paidBookings) }}</p>
        </div>
        <div>
          <p class="text-sm text-slate-500 mb-1">Réservations non payées</p>
          <p class="text-2xl font-semibold text-amber-600">{{ formatNumber(stats.revenue.unpaidBookings) }}</p>
        </div>
      </div>
    </section>

    <!-- Alertes & Actions rapides -->
    <section v-if="alerts.length > 0" class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
        <AlertCircle class="w-5 h-5" />
        Alertes & Actions requises
      </h2>
      <div class="space-y-3">
        <div
          v-for="(alert, index) in alerts"
          :key="index"
          class="flex items-center justify-between p-4 rounded-lg border"
          :class="getAlertClass(alert.type)"
        >
          <div class="flex items-center gap-3">
            <AlertCircle class="w-5 h-5" :class="getAlertIconClass(alert.type)" />
            <p class="font-medium">{{ alert.message }}</p>
          </div>
          <Link
            :href="alert.href"
            class="px-4 py-2 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 text-sm font-medium"
          >
            {{ alert.action }}
          </Link>
        </div>
      </div>
    </section>

    <!-- Raccourcis rapides -->
    <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6">
      <h2 class="text-base sm:text-lg font-semibold text-slate-900 mb-3 sm:mb-4">Raccourcis rapides</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <Link
          :href="route('admin.residences.create')"
          class="flex flex-col items-center justify-center p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
        >
          <Building2 class="w-6 h-6 text-blue-600 mb-2" />
          <span class="text-sm font-medium text-slate-700">Ajouter une résidence</span>
        </Link>
        <Link
          :href="route('admin.vehicles.create')"
          class="flex flex-col items-center justify-center p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
        >
          <Truck class="w-6 h-6 text-blue-600 mb-2" />
          <span class="text-sm font-medium text-slate-700">Ajouter un véhicule</span>
        </Link>
        <Link
          :href="route('admin.combo-offers.create')"
          class="flex flex-col items-center justify-center p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
        >
          <Package class="w-6 h-6 text-blue-600 mb-2" />
          <span class="text-sm font-medium text-slate-700">Créer une offre</span>
        </Link>
        <Link
          :href="route('admin.bookings.index')"
          class="flex flex-col items-center justify-center p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
        >
          <Calendar class="w-6 h-6 text-blue-600 mb-2" />
          <span class="text-sm font-medium text-slate-700">Voir les réservations</span>
        </Link>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StatsCard from '../Components/StatsCard.vue';
import AdminLayout from '../Components/Layouts/AdminLayout.vue';
import { Building2, Truck, Calendar, Clock, DollarSign, AlertCircle, Package } from 'lucide-vue-next';
// Imports onMounted/onUnmounted supprimés - plus utilisés

defineOptions({
  layout: AdminLayout,
});

const props = defineProps<{
  stats: {
    residences: {
      total: number;
      active: number;
      inactive: number;
      withoutImage: number;
      last: {
        id: string | number;
        name: string;
      } | null;
    };
    vehicles: {
      total: number;
      available: number;
      unavailable: number;
      withoutImage: number;
      withoutPrice: number;
      last: {
        id: string | number;
        name: string;
      } | null;
    };
    bookings: {
      total: number;
      active: number;
      pending: number;
      completed: number;
      cancelled: number;
    };
    revenue: {
      today: number;
      month: number;
      total: number;
      paidBookings: number;
      unpaidBookings: number;
    };
    comboOffers: number;
  };
  recentBookings: Array<{
    id: number | string;
    customer: string;
    property: string;
    dates: string;
    status: string;
  }>;
  alerts: Array<{
    type: 'warning' | 'info' | 'error';
    message: string;
    action: string;
    href: string;
  }>;
}>();

// Fonctions de formatage
const formatNumber = (value: number | string): string => {
  const num = typeof value === 'string' ? parseInt(value.replace(/\D/g, '')) || 0 : value;
  return new Intl.NumberFormat('fr-FR').format(num);
};

const formatPrice = (value: number | string): string => {
  const num = typeof value === 'string' ? parseFloat(value.replace(/[^\d.,]/g, '').replace(',', '.')) || 0 : value;
  return new Intl.NumberFormat('fr-FR').format(num);
};

const formatStatus = (status: string): string => {
  const statusLower = (status || '').toLowerCase().trim();
  const statusMap: Record<string, string> = {
    awaiting_payment: 'En attente de paiement',
    awaitingpayment: 'En attente de paiement',
    'en attente de paiement': 'En attente de paiement',
    pending: 'En attente',
    'en attente': 'En attente',
    paid: 'Payée',
    'payé': 'Payée',
    paye: 'Payée',
    confirmed: 'Confirmée',
    confirmee: 'Confirmée',
    'confirmée': 'Confirmée',
    cancelled: 'Annulée',
    canceled: 'Annulée',
    annulee: 'Annulée',
    'annulée': 'Annulée',
    expired: 'Expirée',
    expiree: 'Expirée',
    'expirée': 'Expirée',
    failed: 'Échouée',
    echec: 'Échouée',
    'échec': 'Échouée',
    echoue: 'Échouée',
    'échoué': 'Échouée',
    completed: 'Terminée',
    terminee: 'Terminée',
    'terminée': 'Terminée',
  };
  return statusMap[statusLower] || status;
};

const getStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (
    statusLower === 'awaiting_payment' ||
    statusLower === 'awaitingpayment' ||
    statusLower === 'en attente de paiement'
  ) {
    return 'bg-orange-100 text-orange-900';
  }
  if (statusLower === 'paid' || statusLower === 'payé' || statusLower === 'paye') {
    return 'bg-emerald-100 text-emerald-800';
  }
  if (statusLower === 'confirmée' || statusLower === 'confirmed' || statusLower === 'confirmee') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'en attente' || statusLower === 'pending') {
    return 'bg-amber-100 text-amber-700';
  } else if (statusLower === 'annulée' || statusLower === 'cancelled' || statusLower === 'annulee' || statusLower === 'canceled') {
    return 'bg-red-100 text-red-700';
  } else if (statusLower === 'expired' || statusLower === 'expirée' || statusLower === 'expiree') {
    return 'bg-slate-100 text-slate-700';
  } else if (statusLower === 'failed' || statusLower === 'échec' || statusLower === 'echec' || statusLower === 'échoué' || statusLower === 'echoue') {
    return 'bg-red-100 text-red-700';
  } else if (statusLower === 'terminée' || statusLower === 'completed') {
    return 'bg-blue-100 text-blue-700';
  } else if (statusLower === 'terminee' || statusLower === 'terminée') {
    return 'bg-blue-100 text-blue-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getAlertClass = (type: string): string => {
  switch (type) {
    case 'warning':
      return 'bg-amber-50 border-amber-200';
    case 'error':
      return 'bg-red-50 border-red-200';
    case 'info':
    default:
      return 'bg-blue-50 border-blue-200';
  }
};

const getAlertIconClass = (type: string): string => {
  switch (type) {
    case 'warning':
      return 'text-amber-600';
    case 'error':
      return 'text-red-600';
    case 'info':
    default:
      return 'text-blue-600';
  }
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.residences.create': '/admin/residences/create',
    'admin.residences.index': '/admin/residences',
    'admin.residences.show': (id: string) => `/admin/residences/${id}`,
    'admin.vehicles.create': '/admin/vehicles/create',
    'admin.vehicles.index': '/admin/vehicles',
    'admin.vehicles.show': (id: string) => `/admin/vehicles/${id}`,
    'admin.combo-offers.create': '/admin/combo-offers/create',
    'admin.bookings.index': '/admin/bookings',
    'admin.bookings.show': (id: string) => `/admin/bookings/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  const url = routes[name];
  if (!url) {
    console.warn(`Route ${name} non trouvée, utilisation de #`);
    return '#';
  }
  return url;
};

// Diagnostic et fix pour les overlays (visibles et invisibles) qui bloquent les clics
// Script de gestion des overlays supprimé pour éviter les blocages
</script>
