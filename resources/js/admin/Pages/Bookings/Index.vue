<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Réservations</h1>
        <p class="text-sm text-slate-500 mt-1">Gérer toutes les réservations</p>
      </div>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ error }}
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Calendar class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Total réservations</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.totalBookings || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <CheckCircle class="w-5 h-5 text-emerald-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Confirmées</p>
        <p class="text-2xl font-semibold text-emerald-600">{{ formatNumber(stats?.confirmedBookings || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <DollarSign class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Revenus du mois</p>
        <p class="text-2xl font-semibold text-blue-600">{{ formatPrice(stats?.monthRevenue || 0) }} CFA</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <TrendingUp class="w-5 h-5 text-amber-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Revenus totaux</p>
        <p class="text-2xl font-semibold text-amber-600">{{ formatPrice(stats?.totalRevenue || 0) }} CFA</p>
      </div>
    </div>

    <!-- Filtres -->
    <form @submit.prevent="applyFilters" class="bg-white border border-slate-200 rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher par client, propriété..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select v-model="filters.status" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="awaiting_payment">En attente de paiement</option>
          <option value="confirmed">Confirmée</option>
          <option value="cancelled">Annulée</option>
          <option value="completed">Terminée</option>
        </select>
        <select v-model="filters.bookingType" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les types</option>
          <option value="residence">Résidence</option>
          <option value="vehicle">Véhicule</option>
          <option value="package">Offre combinée</option>
        </select>
        <input
          v-model="filters.startDate"
          type="date"
          placeholder="Date de début"
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <input
          v-model="filters.endDate"
          type="date"
          placeholder="Date de fin"
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <div class="flex gap-2">
          <button
            type="submit"
            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            Filtrer
          </button>
          <button
            type="button"
            @click="resetFilters"
            class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
          >
            Réinitialiser
          </button>
        </div>
      </div>
    </form>

    <!-- Tableau des réservations -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto" style="overflow-y: visible;">
      <div v-if="bookings.length === 0" class="p-12 text-center">
        <p class="text-slate-500">Aucune réservation trouvée</p>
      </div>

      <table v-else class="w-full relative">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Client
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Type
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Dates
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Prix unitaire
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Paiement
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              À verser au propriétaire
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Commission DodoVroum
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Statut
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider w-16">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200" style="overflow: visible;">
          <tr
            v-for="booking in bookings"
            :key="booking.id"
            class="hover:bg-slate-50 cursor-pointer transition-colors active:bg-slate-100 min-h-[48px]"
            role="button"
            tabindex="0"
            @click="goToBooking(booking.id)"
            @keydown.enter="goToBooking(booking.id)"
            @keydown.space.prevent="goToBooking(booking.id)"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-slate-900">{{ booking.customerName }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="booking.bookingType === 'residence' ? 'bg-indigo-100 text-indigo-700'
                      : booking.bookingType === 'vehicle' ? 'bg-emerald-100 text-emerald-700'
                      : booking.bookingType === 'package' ? 'bg-purple-100 text-purple-700'
                      : 'bg-slate-100 text-slate-600'">
                {{ formatBookingType(booking.bookingType) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">
                <div>{{ formatDate(booking.startDate) }}</div>
                <div class="text-slate-500 text-xs">→ {{ formatDate(booking.endDate) }}</div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">
                <template v-if="booking.unitPriceAmount">
                  <span class="font-medium">{{ formatPrice(booking.unitPriceAmount) }} CFA</span>
                  <span class="text-xs text-slate-500 ml-1">{{ formatUnitSuffix(booking.unitPriceLabel) }}</span>
                </template>
                <span v-else class="text-slate-400">-</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm space-y-1">
                <div class="text-slate-900">
                  <span class="text-slate-500">Total à payer :</span>
                  <span class="font-medium ml-1">{{ formatPrice(booking.totalPrice) }} CFA</span>
                </div>
                <div class="text-slate-900">
                  <span class="text-slate-500">Acompte versé :</span>
                  <span class="font-medium ml-1">
                    {{ formatPrice(getCollectedPaid(booking)) }} CFA
                  </span>
                </div>
                <div class="text-slate-900">
                  <span class="text-slate-500">Reste à payer :</span>
                  <span class="font-medium ml-1 text-emerald-600">
                    {{ formatPrice(getRemainingToPay(booking)) }} CFA
                  </span>
                </div>
                <div v-if="getCollectedPaid(booking) > 0" class="pt-1">
                  <div class="w-32 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                    <div
                      class="h-full transition-all duration-300"
                      :class="isBookingSettled(booking) ? 'bg-emerald-500' : 'bg-indigo-500'"
                      :style="{ width: `${getPaymentProgressPercent(booking)}%` }"
                    ></div>
                  </div>
                </div>
                <div v-if="isBookingSettled(booking)" class="pt-1">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                    Soldé
                  </span>
                </div>
                <div v-else-if="getCollectedPaid(booking) > 0" class="pt-1">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                    Acompte versé : {{ formatPrice(getCollectedPaid(booking)) }} CFA
                  </span>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm">
                <div class="font-semibold text-white px-3 py-1.5 rounded-lg inline-block" style="background: rgb(26, 51, 101);">
                  {{ formatPrice(getOwnerPaymentAmount(booking.totalPrice)) }} CFA
                </div>
                <div class="text-xs text-slate-500 mt-2">
                  (90% du total)
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm">
                <div class="font-semibold text-white px-3 py-1.5 rounded-lg inline-block bg-emerald-600">
                  {{ formatPrice(getDodoVroumCommission(booking.totalPrice)) }} CFA
                </div>
                <div class="text-xs text-slate-500 mt-2">
                  (10% du total)
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getStatusClass(booking.status)"
              >
                {{ formatStatus(booking.status) }}
              </span>
            </td>
            <td
              class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
              style="position: relative; overflow: visible !important;"
              @click.stop
            >
              <div class="relative inline-block text-left">
                <button
                  :ref="el => setButtonRef(booking.id, el)"
                  type="button"
                  @click.stop="toggleActionMenu(booking.id)"
                  class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                  </svg>
                </button>
                <Teleport to="body">
                  <div
                    v-if="activeMenuId === booking.id"
                    class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200"
                    :style="getMenuStyle(booking.id)"
                    @click.stop
                    style="z-index: 999999 !important;"
                  >
                  <div class="py-1">
                    <!-- Action principale : Voir -->
                    <Link
                      :href="route('admin.bookings.show', booking.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeActionMenu"
                    >
                      Voir les détails
                    </Link>
                    
                    <!-- Actions d'approbation/rejet : utilise isPendingApproval (backend) ou statut pending -->
                    <template v-if="canApproveOrReject(booking)">
                      <div class="border-t border-slate-200 my-1"></div>
                      <button
                        @click="approveBooking(booking.id)"
                        class="w-full text-left px-4 py-2 text-sm text-emerald-600 hover:bg-emerald-50 transition-colors font-medium"
                      >
                        Approuver
                      </button>
                      <div class="border-t border-slate-200 my-1"></div>
                      <button
                        @click="rejectBooking(booking.id)"
                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors font-medium"
                      >
                        Rejeter
                      </button>
                      <!-- Séparateur avant Supprimer si pending -->
                      <div class="border-t border-slate-200 my-1"></div>
                      <button
                        @click="confirmDelete(booking.id)"
                        class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors"
                      >
                        Supprimer
                      </button>
                    </template>
                    
                    <!-- Action de suppression (si pas pending) -->
                    <template v-else>
                      <div class="border-t border-slate-200 my-1"></div>
                      <button
                        @click="confirmDelete(booking.id)"
                        class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors"
                      >
                        Supprimer
                      </button>
                    </template>
                  </div>
                </div>
                </Teleport>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <Pagination
      v-if="pagination && pagination.total > 0"
      :pagination="pagination"
      route-name="admin.bookings.index"
      :filters="filters"
    />
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue';
import { Teleport } from 'vue';
import { Calendar, CheckCircle, DollarSign, TrendingUp, Building2, Truck, Package } from 'lucide-vue-next';
import Pagination from '../../Components/Pagination.vue';
import AdminLayout from '../../Components/Layouts/AdminLayout.vue';

defineOptions({
  layout: AdminLayout,
});

const props = defineProps<{
  bookings: Array<{
    id: string;
    bookingType?: 'residence' | 'vehicle' | 'package' | 'unknown';
    customerName: string;
    propertyName: string;
    propertyImage?: string | null;
    vehicleName: string | null;
    vehicleDriverOption?: 'with_driver' | 'without_driver' | null;
    offerName: string | null;
    startDate: string | null;
    endDate: string | null;
    totalPrice: number;
    unitPriceAmount?: number | null;
    unitPriceLabel?: string | null;
    downPayment?: number;
    downPaymentPercentage?: number | null;
    totalPaid?: number;
    remainingBalance?: number;
    isFullyPaid?: boolean;
    paymentType?: 'NONE' | 'DOWN_PAYMENT' | 'FULL_PAYMENT';
    status: string;
    isPendingApproval?: boolean;
    createdAt: string | null;
    keyRetrievedAt: string | null;
    ownerConfirmedAt: string | null;
    checkOutAt: string | null;
  }>;
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  filters: {
    search: string;
    status: string;
    bookingType?: string;
    ownerId?: string;
    startDate?: string;
    endDate?: string;
  };
  error?: string;
  stats?: {
    totalBookings: number;
    confirmedBookings: number;
    pendingBookings: number;
    cancelledBookings: number;
    totalRevenue: number;
    monthRevenue: number;
  };
}>();

const error = ref(props.error || '');
const activeMenuId = ref<string | null>(null);
const buttonRefs = ref<Record<string, HTMLElement | null>>({});
const menuPositions = ref<Record<string, { top: number; right: number }>>({});

// Gestion des images
const imageErrors = ref<Record<string | number, boolean>>({});

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

const getPropertyIcon = (type?: string) => {
  if (type === 'residence') return Building2;
  if (type === 'vehicle') return Truck;
  if (type === 'package') return Package;
  return Package;
};

const filters = reactive({
  search: props.filters.search || '',
  status: props.filters.status || '',
  bookingType: props.filters.bookingType || '',
  ownerId: props.filters.ownerId || '',
  startDate: props.filters.startDate || '',
  endDate: props.filters.endDate || '',
});

const setButtonRef = (id: string, el: HTMLElement | null) => {
  if (el) {
    buttonRefs.value[id] = el;
  }
};

const updateMenuPosition = (id: string) => {
  nextTick(() => {
    const button = buttonRefs.value[id];
    if (button) {
      const rect = button.getBoundingClientRect();
      const menuWidth = 192; // w-48 = 192px
      const menuHeight = 200; // Estimation de la hauteur du menu
      const padding = 2; // Espacement entre le bouton et le menu
      
      // Aligner le menu verticalement avec le bouton (centré)
      const buttonCenter = rect.top + (rect.height / 2);
      let top = buttonCenter - (menuHeight / 2);
      let right = window.innerWidth - rect.right + padding;
      
      // Vérifier si le menu dépasse en bas de l'écran
      if (top + menuHeight > window.innerHeight) {
        // Si oui, aligner le menu en bas de l'écran
        top = window.innerHeight - menuHeight - padding;
      }
      
      // Vérifier si le menu dépasse à droite de l'écran
      if (right < menuWidth) {
        // Si oui, aligner le menu à droite avec un petit padding
        right = 8;
      }
      
      // Vérifier si le menu dépasse en haut de l'écran
      if (top < 0) {
        top = padding;
      }
      
      menuPositions.value[id] = {
        top,
        right,
      };
    }
  });
};

const toggleActionMenu = (id: string) => {
  if (activeMenuId.value === id) {
    activeMenuId.value = null;
  } else {
    activeMenuId.value = id;
    updateMenuPosition(id);
  }
};

const getMenuStyle = (id: string) => {
  const pos = menuPositions.value[id];
  if (!pos) return { visibility: 'hidden', top: '0px', right: '0px' };
  return {
    top: `${pos.top}px`,
    right: `${pos.right}px`,
  };
};

const closeActionMenu = () => {
  activeMenuId.value = null;
};

// Fermer le menu quand on clique ailleurs
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.relative')) {
    closeActionMenu();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

const applyFilters = () => {
  router.get('/admin/bookings', filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  filters.search = '';
  filters.status = '';
  filters.bookingType = '';
  filters.ownerId = '';
  filters.startDate = '';
  filters.endDate = '';
  applyFilters();
};

const formatDate = (date: string | null): string => {
  if (!date) return 'N/A';
  try {
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return date;
  }
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const getOwnerPaymentAmount = (totalPrice: number | null | undefined): number => {
  if (!totalPrice || totalPrice === 0) return 0;
  return Math.round(totalPrice * 0.9);
};

const getDodoVroumCommission = (totalPrice: number | null | undefined): number => {
  if (!totalPrice || totalPrice === 0) return 0;
  return Math.round(totalPrice * 0.1);
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

const formatStatus = (status: string): string => {
  const statusLower = status.toLowerCase();
  const statusMap: Record<string, string> = {
    awaiting_payment: 'En attente de paiement',
    awaitingpayment: 'En attente de paiement',
    pending: 'En attente',
    'en attente': 'En attente',
    confirmed: 'Confirmée',
    confirmee: 'Confirmée',
    'confirmée': 'Confirmée',
    cancelled: 'Annulée',
    canceled: 'Annulée',
    'annulée': 'Annulée',
    completed: 'Terminée',
    terminee: 'Terminée',
    'terminée': 'Terminée',
  };
  return statusMap[statusLower] || status;
};

const isPaidStatus = (status?: string): boolean => {
  if (!status) return false;
  const normalized = status.toLowerCase().trim();
  return normalized === 'paid' || normalized === 'payé' || normalized === 'paye';
};

const getCollectedPaid = (booking: {
  totalPaid?: number;
  downPayment?: number;
}): number => {
  if (typeof booking.totalPaid === 'number') {
    return Math.max(0, booking.totalPaid);
  }
  if (typeof booking.downPayment === 'number') {
    return Math.max(0, booking.downPayment);
  }
  return 0;
};

const getRemainingToPay = (booking: {
  totalPrice: number;
  downPayment?: number;
  totalPaid?: number;
  status: string;
  isFullyPaid?: boolean;
  remainingBalance?: number;
}): number => {
  // Le statut paid (ou full paid) doit toujours afficher 0 en UI.
  if (booking.isFullyPaid || isPaidStatus(booking.status)) {
    return 0;
  }

  if (typeof booking.remainingBalance === 'number') {
    return Math.max(0, booking.remainingBalance);
  }

  // Fallback: total - paiements encaissés.
  return Math.max(0, (booking.totalPrice || 0) - getCollectedPaid(booking));
};

const isBookingSettled = (booking: {
  totalPrice: number;
  downPayment?: number;
  totalPaid?: number;
  status: string;
  isFullyPaid?: boolean;
  remainingBalance?: number;
}): boolean => {
  return getRemainingToPay(booking) <= 0;
};

const getPaymentProgressPercent = (booking: {
  totalPrice: number;
  downPayment?: number;
  totalPaid?: number;
  status: string;
  isFullyPaid?: boolean;
  remainingBalance?: number;
}): number => {
  const total = Math.max(0, booking.totalPrice || 0);
  if (total <= 0) return 0;
  const paid = Math.max(0, total - getRemainingToPay(booking));
  return Math.max(0, Math.min(100, (paid / total) * 100));
};

const isPending = (status: string): boolean => {
  if (!status) return false;
  const statusLower = status.toLowerCase();
  const isPendingStatus = statusLower === 'pending' ||
                         statusLower === 'en attente' ||
                         statusLower === 'p';
  return isPendingStatus;
};

/** Afficher Approuver/Rejeter : priorité au flag backend isPendingApproval, sinon au statut "pending". */
const canApproveOrReject = (booking: { status: string; isPendingApproval?: boolean }): boolean => {
  if (booking.isPendingApproval === true) return true;
  if (booking.isPendingApproval === false) return false;
  return isPending(booking.status);
};

const getStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'awaiting_payment' || statusLower === 'awaitingpayment') {
    return 'bg-orange-100 text-orange-900';
  }
  if (statusLower === 'confirmed' || statusLower === 'confirmee' || statusLower === 'confirmée') {
    return 'bg-emerald-100 text-emerald-800';
  } else if (statusLower === 'pending' || statusLower === 'en attente') {
    return 'bg-yellow-100 text-yellow-800';
  } else if (statusLower === 'cancelled' || statusLower === 'canceled' || statusLower === 'annulee' || statusLower === 'annulée') {
    return 'bg-red-100 text-red-800';
  } else if (statusLower === 'completed' || statusLower === 'terminee' || statusLower === 'terminée') {
    return 'bg-blue-100 text-blue-800';
  }
  return 'bg-slate-100 text-slate-800';
};

const getPaymentTypeLabel = (paymentType: string | undefined): string => {
  if (!paymentType) return 'Non payé';
  switch (paymentType) {
    case 'NONE':
      return 'Non payé';
    case 'DOWN_PAYMENT':
      return 'Acompte';
    case 'FULL_PAYMENT':
      return 'Payé en totalité';
    default:
      return paymentType;
  }
};

const getPaymentTypeClass = (paymentType: string | undefined): string => {
  if (!paymentType) return 'bg-slate-100 text-slate-600';
  switch (paymentType) {
    case 'NONE':
      return 'bg-red-100 text-red-700';
    case 'DOWN_PAYMENT':
      return 'bg-yellow-100 text-yellow-700';
    case 'FULL_PAYMENT':
      return 'bg-emerald-100 text-emerald-700';
    default:
      return 'bg-slate-100 text-slate-600';
  }
};

const formatBookingType = (bookingType?: string): string => {
  switch (bookingType) {
    case 'residence':
      return 'Résidence';
    case 'vehicle':
      return 'Véhicule';
    case 'package':
      return 'Offre combinée';
    default:
      return 'Inconnu';
  }
};

const formatUnitSuffix = (label: string | null | undefined): string => {
  if (!label) return '';
  switch (label) {
    case 'night':
      return '/ nuit';
    case 'day':
      return '/ jour';
    case 'pack':
      return 'par pack';
    default:
      return '/ unité';
  }
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.bookings.index': '/admin/bookings',
    'admin.bookings.show': (id: string) => `/admin/bookings/${id}`,
    'admin.bookings.approve': (id: string) => `/admin/bookings/${id}/approve`,
    'admin.bookings.reject': (id: string) => `/admin/bookings/${id}/reject`,
    'admin.bookings.destroy': (id: string) => `/admin/bookings/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};

const goToBooking = (id: string) => {
  router.visit(route('admin.bookings.show', id));
};

const approveBooking = (id: string) => {
  closeActionMenu();
  if (confirm('Es-tu sûr de vouloir approuver cette réservation ?')) {
    router.visit(route('admin.bookings.approve', id), {
      method: 'patch',
      preserveScroll: true,
      onSuccess: () => {
        // Le message de succès sera affiché via flash
      },
      onError: (errors) => {
        console.error('Erreur lors de l\'approbation:', errors);
      },
    });
  }
};

const rejectBooking = (id: string) => {
  closeActionMenu();
  const reason = prompt('Raison du rejet (optionnel) :');
  if (reason !== null) { // L'utilisateur n'a pas annulé
    router.visit(route('admin.bookings.reject', id), {
      method: 'patch',
      data: { reason: reason || null },
      preserveScroll: true,
      onSuccess: () => {
        // Le message de succès sera affiché via flash
      },
      onError: (errors) => {
        console.error('Erreur lors du rejet:', errors);
      },
    });
  }
};

const confirmDelete = (id: string) => {
  closeActionMenu();
  if (confirm('Es-tu sûr de vouloir supprimer cette réservation ? Cette action est irréversible.')) {
    router.delete(route('admin.bookings.destroy', id), {
      preserveScroll: true,
    });
  }
};
</script>

