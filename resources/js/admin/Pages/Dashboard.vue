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
      <StatsCard title="Revenus du mois" :value="formatRevenue(stats.revenue.month)">
        <template #icon>
          <DollarSign class="w-5 h-5" />
        </template>
      </StatsCard>
    </section>

    <!-- Réservations récentes -->
    <section class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-4 sm:px-6 pt-4 sm:pt-5 pb-3 sm:pb-4 border-b border-slate-100">
        <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">Réservations récentes</h2>
        <Link
          :href="route('admin.bookings.index')"
          class="text-xs font-semibold text-brand hover:text-brand-dark transition-colors duration-150"
        >
          Voir toutes →
        </Link>
      </div>
      <div v-if="recentBookings.length === 0" class="text-center py-12">
        <Calendar class="w-10 h-10 text-slate-200 mx-auto mb-3" />
        <p class="text-sm text-slate-400 font-medium">Aucune réservation récente</p>
      </div>
      <div v-else class="table-scroll-wrap">
        <table class="w-full min-w-[600px]">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100">
              <th class="px-4 sm:px-6 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Client</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Bien</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Dates</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Statut</th>
              <th class="px-4 sm:px-6 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="booking in recentBookings" :key="booking.id"
                class="hover:bg-slate-50/70 transition-colors duration-100">
              <td class="px-4 sm:px-6 py-3.5 text-sm font-semibold text-slate-800">
                {{ booking.customer }}
              </td>
              <td class="px-4 py-3.5 text-sm text-slate-500">
                {{ booking.property }}
              </td>
              <td class="px-4 py-3.5 text-sm text-slate-500 whitespace-nowrap">
                {{ booking.dates }}
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
              </td>
              <td class="px-4 sm:px-6 py-3.5 text-right">
                <Link
                  :href="route('admin.bookings.show', booking.id)"
                  class="text-xs font-semibold text-brand hover:text-brand-dark transition-colors duration-150"
                >
                  Détails →
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Résumé des biens et Revenus -->
    <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
      <!-- Résumé des résidences -->
      <section class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2 tracking-tight">
            <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
              <Building2 class="w-4 h-4" />
            </span>
            Résidences
          </h2>
          <Link
            :href="route('admin.residences.index')"
            class="text-xs font-semibold text-brand hover:text-brand-dark transition-colors duration-150"
          >
            Voir toutes →
          </Link>
        </div>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50/80 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Total</p>
              <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ formatNumber(stats.residences.total) }}</p>
            </div>
            <div class="bg-emerald-50/60 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Actives</p>
              <p class="text-2xl font-bold text-emerald-600 tracking-tight">{{ formatNumber(stats.residences.active) }}</p>
            </div>
            <div class="bg-slate-50/80 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Inactives</p>
              <p class="text-2xl font-bold text-slate-500 tracking-tight">{{ formatNumber(stats.residences.inactive) }}</p>
            </div>
            <div class="bg-amber-50/60 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Sans photo</p>
              <p class="text-2xl font-bold text-amber-600 tracking-tight">{{ formatNumber(stats.residences.withoutImage) }}</p>
            </div>
          </div>
          <div v-if="stats.residences.last" class="pt-3 border-t border-slate-100">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Dernière ajoutée</p>
            <Link
              :href="route('admin.residences.show', stats.residences.last.id)"
              class="text-sm font-semibold text-brand hover:text-brand-dark transition-colors duration-150"
            >
              {{ stats.residences.last.name }}
            </Link>
          </div>
          <Link
            :href="route('admin.residences.create')"
            class="flex items-center justify-center w-full mt-1 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold text-center rounded-xl hover:bg-blue-700 active:scale-[.99] transition-all duration-150 shadow-sm shadow-blue-200"
          >
            + Ajouter une résidence
          </Link>
        </div>
      </section>

      <!-- Résumé des véhicules -->
      <section class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2 tracking-tight">
            <span class="w-7 h-7 rounded-lg bg-brand/10 text-brand flex items-center justify-center flex-shrink-0">
              <Truck class="w-4 h-4" />
            </span>
            Véhicules
          </h2>
          <Link
            :href="route('admin.vehicles.index')"
            class="text-xs font-semibold text-brand hover:text-brand-dark transition-colors duration-150"
          >
            Voir tous →
          </Link>
        </div>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50/80 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Total</p>
              <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ formatNumber(stats.vehicles.total) }}</p>
            </div>
            <div class="bg-emerald-50/60 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Disponibles</p>
              <p class="text-2xl font-bold text-emerald-600 tracking-tight">{{ formatNumber(stats.vehicles.available) }}</p>
            </div>
            <div class="bg-slate-50/80 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Indisponibles</p>
              <p class="text-2xl font-bold text-slate-500 tracking-tight">{{ formatNumber(stats.vehicles.unavailable) }}</p>
            </div>
            <div class="bg-amber-50/60 rounded-xl p-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Sans photo</p>
              <p class="text-2xl font-bold text-amber-600 tracking-tight">{{ formatNumber(stats.vehicles.withoutImage) }}</p>
            </div>
          </div>
          <div v-if="stats.vehicles.last" class="pt-3 border-t border-slate-100">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Dernier ajouté</p>
            <Link
              :href="route('admin.vehicles.show', stats.vehicles.last.id)"
              class="text-sm font-semibold text-brand hover:text-brand-dark transition-colors duration-150"
            >
              {{ stats.vehicles.last.name }}
            </Link>
          </div>
          <div v-if="stats.vehicles.withoutPrice > 0" class="pt-1">
            <p class="text-xs font-medium text-amber-600 bg-amber-50 rounded-lg px-3 py-2">⚠️ {{ stats.vehicles.withoutPrice }} véhicule(s) sans prix</p>
          </div>
          <Link
            :href="route('admin.vehicles.create')"
            class="flex items-center justify-center w-full mt-1 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold text-center rounded-xl hover:bg-blue-700 active:scale-[.99] transition-all duration-150 shadow-sm shadow-blue-200"
          >
            + Ajouter un véhicule
          </Link>
        </div>
      </section>
    </div>

    <!-- Revenus & Performance -->
    <section class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
      <h2 class="text-sm font-bold text-slate-900 mb-5 flex items-center gap-2 tracking-tight">
        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
          <DollarSign class="w-4 h-4" />
        </span>
        Revenus & Performance
      </h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-slate-50/80 rounded-xl p-3.5">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Aujourd'hui</p>
          <p class="text-lg font-bold text-slate-900 tracking-tight leading-tight">{{ formatPrice(stats.revenue.today) }}</p>
          <p class="text-[10px] text-slate-400 mt-0.5 font-medium">CFA</p>
        </div>
        <div class="bg-emerald-50/60 rounded-xl p-3.5">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Ce mois</p>
          <p class="text-lg font-bold text-emerald-600 tracking-tight leading-tight">{{ formatPrice(stats.revenue.month) }}</p>
          <p class="text-[10px] text-slate-400 mt-0.5 font-medium">CFA</p>
        </div>
        <div class="bg-blue-50/60 rounded-xl p-3.5">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Cumulé</p>
          <p class="text-lg font-bold text-blue-600 tracking-tight leading-tight">{{ formatPrice(stats.revenue.total) }}</p>
          <p class="text-[10px] text-slate-400 mt-0.5 font-medium">CFA</p>
        </div>
        <div class="bg-emerald-50/60 rounded-xl p-3.5">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Payées</p>
          <p class="text-lg font-bold text-emerald-600 tracking-tight">{{ formatNumber(stats.revenue.paidBookings) }}</p>
          <p class="text-[10px] text-slate-400 mt-0.5 font-medium">réservations</p>
        </div>
        <div class="bg-amber-50/60 rounded-xl p-3.5">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Non payées</p>
          <p class="text-lg font-bold text-amber-600 tracking-tight">{{ formatNumber(stats.revenue.unpaidBookings) }}</p>
          <p class="text-[10px] text-slate-400 mt-0.5 font-medium">réservations</p>
        </div>
      </div>
    </section>

    <!-- Alertes & Actions requises -->
    <section v-if="alerts.length > 0" class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
      <h2 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2 tracking-tight">
        <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
          <AlertCircle class="w-4 h-4" />
        </span>
        Alertes & Actions requises
      </h2>
      <div class="space-y-2.5">
        <div
          v-for="(alert, index) in alerts"
          :key="index"
          class="flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border"
          :class="getAlertClass(alert.type)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <AlertCircle class="w-4 h-4 flex-shrink-0" :class="getAlertIconClass(alert.type)" />
            <p class="text-sm font-medium truncate">{{ alert.message }}</p>
          </div>
          <Link
            :href="alert.href"
            class="flex-shrink-0 px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-xs font-semibold text-slate-700 transition-colors duration-150 shadow-sm"
          >
            {{ alert.action }}
          </Link>
        </div>
      </div>
    </section>

    <!-- Raccourcis rapides -->
    <section class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
      <h2 class="text-sm font-bold text-slate-900 mb-4 tracking-tight">Raccourcis rapides</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <Link
          :href="route('admin.residences.create')"
          class="group flex flex-col items-center justify-center gap-2.5 p-4 border border-slate-200/80 rounded-xl
                 hover:border-blue-200 hover:bg-blue-50/50 hover:-translate-y-px hover:shadow-sm
                 transition-all duration-150"
        >
          <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-colors duration-150">
            <Building2 class="w-5 h-5" />
          </div>
          <span class="text-xs font-semibold text-slate-600 text-center leading-tight group-hover:text-slate-800">Ajouter une résidence</span>
        </Link>
        <Link
          :href="route('admin.vehicles.create')"
          class="group flex flex-col items-center justify-center gap-2.5 p-4 border border-slate-200/80 rounded-xl
                 hover:border-brand/30 hover:bg-brand/5 hover:-translate-y-px hover:shadow-sm
                 transition-all duration-150"
        >
          <div class="w-9 h-9 rounded-xl bg-brand/10 text-brand flex items-center justify-center group-hover:bg-brand/15 transition-colors duration-150">
            <Truck class="w-5 h-5" />
          </div>
          <span class="text-xs font-semibold text-slate-600 text-center leading-tight group-hover:text-slate-800">Ajouter un véhicule</span>
        </Link>
        <Link
          :href="route('admin.combo-offers.create')"
          class="group flex flex-col items-center justify-center gap-2.5 p-4 border border-slate-200/80 rounded-xl
                 hover:border-blue-200 hover:bg-blue-50/50 hover:-translate-y-px hover:shadow-sm
                 transition-all duration-150"
        >
          <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-colors duration-150">
            <Package class="w-5 h-5" />
          </div>
          <span class="text-xs font-semibold text-slate-600 text-center leading-tight group-hover:text-slate-800">Créer une offre</span>
        </Link>
        <Link
          :href="route('admin.bookings.index')"
          class="group flex flex-col items-center justify-center gap-2.5 p-4 border border-slate-200/80 rounded-xl
                 hover:border-blue-200 hover:bg-blue-50/50 hover:-translate-y-px hover:shadow-sm
                 transition-all duration-150"
        >
          <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-colors duration-150">
            <Calendar class="w-5 h-5" />
          </div>
          <span class="text-xs font-semibold text-slate-600 text-center leading-tight group-hover:text-slate-800">Voir les réservations</span>
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
  return new Intl.NumberFormat('fr-FR').format(Math.round(num));
};

const formatRevenue = (value: number | string): string => {
  const num = typeof value === 'string' ? parseFloat(value.replace(/[^\d.,]/g, '').replace(',', '.')) || 0 : value;
  const n = Math.round(num);
  if (n >= 1_000_000_000) return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(n / 1_000_000_000)} G CFA`;
  if (n >= 1_000_000)     return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(n / 1_000_000)} M CFA`;
  if (n >= 10_000)        return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(n / 1_000)} k CFA`;
  return `${new Intl.NumberFormat('fr-FR').format(n)} CFA`;
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
