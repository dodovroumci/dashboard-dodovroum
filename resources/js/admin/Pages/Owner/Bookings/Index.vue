<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Mes réservations</h1>
      <p class="text-sm text-slate-500 mt-1">Vos réservations</p>
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
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher par client, propriété..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select v-model="filters.status" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="confirmed">Confirmée</option>
          <option value="cancelled">Annulée</option>
          <option value="completed">Terminée</option>
        </select>
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
    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
      <div v-if="bookings.length === 0" class="p-12 text-center">
        <p class="text-slate-500">Aucune réservation trouvée</p>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Client
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Propriété / Véhicule
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Dates
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Total
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Statut
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
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
              <div class="text-sm font-medium text-slate-900">{{ booking.customer || booking.customerName || 'Client inconnu' }}</div>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img
                    v-if="booking.propertyImage && !imageErrors[booking.id]"
                    :src="getStorageImageUrl(booking.propertyImage)"
                    :alt="booking.property || booking.propertyName || 'Propriété'"
                    class="w-full h-full object-cover"
                    @error="() => handleImageError(booking.id)"
                    @load="() => imageErrors[booking.id] = false"
                  />
                  <component
                    v-else
                    :is="getPropertyIcon(booking.bookingType)"
                    class="w-6 h-6 text-slate-400"
                  />
                </div>
                <div>
                  <div class="text-sm text-slate-900 font-medium">
                    {{ booking.property || booking.propertyName || 'Non spécifié' }}
                  </div>
                  <div v-if="booking.bookingType" class="text-xs text-slate-500">
                    {{ getBookingTypeLabel(booking.bookingType) }}
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">
                {{ booking.dates || formatDateRange(booking.startDate, booking.endDate) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-slate-900">
                {{ formatPrice(booking.totalPrice || booking.total || 0) }} CFA
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
                      :href="`/owner/bookings/${booking.id}`"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeActionMenu"
                    >
                      Voir les détails
                    </Link>
                    
                    <!-- Actions d'approbation/rejet (si pas confirmée/annulée/terminée) -->
                    <template v-if="canApproveOrReject(booking.status)">
                      <div class="border-t border-slate-200 my-1"></div>
                      <button
                        @click="approveBooking(booking.id)"
                        class="w-full text-left px-4 py-2 text-sm text-emerald-600 hover:bg-emerald-50 transition-colors font-medium"
                      >
                        ✅ Approuver
                      </button>
                      <button
                        @click="rejectBooking(booking.id)"
                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors font-medium"
                      >
                        ❌ Rejeter
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
      
      <!-- Pagination -->
      <Pagination
        v-if="pagination && pagination.total > 0"
        :pagination="pagination"
        route-name="owner.bookings.index"
        :filters="filters"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Teleport } from 'vue';
import { Calendar, CheckCircle, DollarSign, TrendingUp, Building2, Truck, Package } from 'lucide-vue-next';
import Pagination from '../../../Components/Pagination.vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  bookings: Array<{
    id: number | string;
    customer?: string;
    customerName?: string;
    property?: string;
    propertyName?: string;
    propertyImage?: string;
    bookingType?: string;
    vehicle?: string;
    vehicleName?: string;
    dates?: string;
    startDate?: string;
    endDate?: string;
    totalPrice?: number;
    total?: number;
    status: string;
  }>;
  filters?: {
    search?: string;
    status?: string;
  };
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  } | null;
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

const error = props.error || '';

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

const getBookingTypeLabel = (type?: string): string => {
  if (type === 'residence') return 'Résidence';
  if (type === 'vehicle') return 'Véhicule';
  if (type === 'package') return 'Offre combinée';
  return '';
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

// Gestion du menu d'actions
const activeMenuId = ref<string | number | null>(null);
const buttonRefs = ref<Record<string | number, HTMLElement | null>>({});

const setButtonRef = (id: string | number, el: HTMLElement | null) => {
  if (el) {
    buttonRefs.value[id] = el;
  }
};

const getMenuStyle = (id: string | number): Record<string, string> => {
  const button = buttonRefs.value[id];
  if (!button) {
    return { display: 'none' };
  }
  
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
  
  return {
    top: `${top}px`,
    right: `${right}px`,
  };
};

const toggleActionMenu = (id: string | number) => {
  if (activeMenuId.value === id) {
    activeMenuId.value = null;
  } else {
    activeMenuId.value = id;
  }
};

const closeActionMenu = () => {
  activeMenuId.value = null;
};

// Fermer le menu quand on clique ailleurs
const handleClickOutside = (event: MouseEvent) => {
  if (activeMenuId.value && !(event.target as Element).closest('.relative')) {
    closeActionMenu();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Fonctions d'approbation et de rejet
const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'owner.bookings.index': '/owner/bookings',
    'owner.bookings.show': (id: string) => `/owner/bookings/${id}`,
    'owner.bookings.approve': (id: string) => `/owner/bookings/${id}/approve`,
    'owner.bookings.reject': (id: string) => `/owner/bookings/${id}/reject`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};

const goToBooking = (id: string | number) => {
  router.visit(route('owner.bookings.show', id));
};

const approveBooking = (id: string | number) => {
  closeActionMenu();
  if (confirm('Es-tu sûr de vouloir approuver cette réservation ?')) {
    router.patch(route('owner.bookings.approve', id), {}, {
      preserveScroll: true,
      preserveState: false, // Forcer le rechargement pour voir le nouveau statut
      onSuccess: () => {
        // Le message de succès sera affiché via flash
        console.log('Réservation approuvée avec succès');
      },
      onError: (errors) => {
        console.error('Erreur lors de l\'approbation:', errors);
        alert('Une erreur est survenue lors de l\'approbation de la réservation.');
      },
      onFinish: () => {
        // Optionnel : callback après la fin de la requête
      },
    });
  }
};

const rejectBooking = (id: string | number) => {
  closeActionMenu();
  const reason = prompt('Raison du rejet (optionnel) :');
  if (reason !== null) { // L'utilisateur n'a pas annulé
    router.patch(route('owner.bookings.reject', id), { reason: reason || null }, {
      preserveScroll: true,
      preserveState: false, // Forcer le rechargement pour voir le nouveau statut
      onSuccess: () => {
        // Le message de succès sera affiché via flash
        console.log('Réservation rejetée avec succès');
      },
      onError: (errors) => {
        console.error('Erreur lors du rejet:', errors);
        alert('Une erreur est survenue lors du rejet de la réservation.');
      },
    });
  }
};

const filters = reactive({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
});

const applyFilters = () => {
  router.get('/owner/bookings', { ...filters, page: 1 }, {
    preserveState: false,
    preserveScroll: false,
  });
};

const resetFilters = () => {
  filters.search = '';
  filters.status = '';
  applyFilters();
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatDateRange = (startDate?: string, endDate?: string): string => {
  if (!startDate || !endDate) return 'Dates non définies';
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return `${start.toLocaleDateString('fr-FR')} - ${end.toLocaleDateString('fr-FR')}`;
  } catch {
    return `${startDate} - ${endDate}`;
  }
};

const isPending = (status: string): boolean => {
  if (!status) return false;
  const statusLower = status.toLowerCase().trim();
  // Accepter plusieurs variations du statut "pending"
  return statusLower === 'pending' || 
         statusLower === 'en attente' || 
         statusLower === 'en_attente' ||
         statusLower === 'p' ||
         statusLower === 'attente';
};

// Fonction pour vérifier si on peut approuver/rejeter une réservation
// On peut approuver/rejeter si ce n'est pas confirmée, annulée ou terminée
const canApproveOrReject = (status: string): boolean => {
  if (!status) return true; // Si pas de statut, permettre l'approbation par défaut
  
  const statusLower = status.toLowerCase().trim();
  const finalStatuses = ['confirmed', 'confirmee', 'confirmée', 'cancelled', 'annulee', 'annulée', 'completed', 'terminee', 'terminée', 'canceled'];
  
  // Si le statut est l'un des statuts finaux, on ne peut plus approuver/rejeter
  return !finalStatuses.includes(statusLower);
};

const formatStatus = (status?: string): string => {
  if (!status) return 'Inconnu';
  const statusLower = status.toLowerCase();

  if (statusLower === 'awaiting_payment' || statusLower === 'awaitingpayment') {
    return 'En attente de paiement';
  }
  if (statusLower === 'pending' || statusLower === 'en_attente') {
    return 'En attente';
  } else if (statusLower === 'confirmed' || statusLower === 'confirmee' || statusLower === 'confirmée') {
    return 'Confirmée';
  } else if (statusLower === 'cancelled' || statusLower === 'annulee' || statusLower === 'annulée') {
    return 'Annulée';
  } else if (statusLower === 'completed' || statusLower === 'terminee' || statusLower === 'terminée') {
    return 'Terminée';
  }
  return status;
};

const getStatusClass = (status?: string): string => {
  if (!status) return 'bg-slate-100 text-slate-700';
  const statusLower = status.toLowerCase();

  if (statusLower === 'awaiting_payment' || statusLower === 'awaitingpayment') {
    return 'bg-orange-100 text-orange-900';
  }
  if (statusLower === 'pending' || statusLower === 'en_attente') {
    return 'bg-amber-100 text-amber-700';
  } else if (statusLower === 'confirmed' || statusLower === 'confirmee' || statusLower === 'confirmée') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'cancelled' || statusLower === 'annulee' || statusLower === 'annulée') {
    return 'bg-red-100 text-red-700';
  } else if (statusLower === 'completed' || statusLower === 'terminee' || statusLower === 'terminée') {
    return 'bg-blue-100 text-blue-700';
  }
  return 'bg-slate-100 text-slate-700';
};
</script>

