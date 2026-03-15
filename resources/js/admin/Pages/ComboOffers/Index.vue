<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="min-w-0 flex-1">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Offres combinées</h1>
        <p class="text-sm text-slate-500 mt-1">Gérer vos offres résidence + véhicule</p>
      </div>
      <Link
        :href="route('admin.combo-offers.create')"
        class="flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 min-h-[44px] bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm whitespace-nowrap w-full sm:w-auto shrink-0"
      >
        <span aria-hidden="true">+</span>
        <span><span class="sm:hidden">Ajouter</span><span class="hidden sm:inline">Ajouter une offre</span></span>
      </Link>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Package class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Total offres</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.totalOffers || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Calendar class="w-5 h-5 text-amber-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Réservations confirmées</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.confirmedBookings || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <DollarSign class="w-5 h-5 text-emerald-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Revenus du mois</p>
        <p class="text-2xl font-semibold text-emerald-600">{{ formatPrice(stats?.monthRevenue || 0) }} CFA</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <TrendingUp class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Taux de conversion</p>
        <p class="text-2xl font-semibold text-blue-600">{{ stats?.conversionRate || 0 }}%</p>
      </div>
    </div>

    <!-- Filtres -->
    <form @submit.prevent="applyFilters" class="bg-white border border-slate-200 rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select v-model="filters.status" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les statuts</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
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

    <!-- Tableau des offres -->
    <div class="bg-white border border-slate-200 rounded-xl" style="overflow-x: auto; overflow-y: visible;">
      <div v-if="comboOffers.length === 0" class="p-12 text-center">
        <p class="text-slate-500">Aucune offre combinée trouvée</p>
        <Link
          :href="route('admin.combo-offers.create')"
          class="mt-4 inline-block px-4 py-2 text-blue-600 hover:text-blue-700"
        >
          Ajouter votre première offre combinée
        </Link>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Offre
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Résidence
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Véhicule
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Prix
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Dates
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
            v-for="offer in comboOffers"
            :key="offer.id"
            class="hover:bg-slate-50 cursor-pointer transition-colors"
            @click="goToOffer(offer)"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img
                    v-if="getOfferImage(offer) && !imageErrors[offer.id]"
                    :src="getStorageImageUrl(getOfferImage(offer))"
                    :alt="offer.title || offer.name || 'Offre'"
                    class="w-full h-full object-cover"
                    @error="() => handleImageError(offer.id)"
                    @load="() => imageErrors[offer.id] = false"
                  />
                  <Package
                    v-else
                    class="w-6 h-6 text-slate-400"
                  />
                </div>
                <div>
              <div class="text-sm font-medium text-slate-900">
                {{ offer.title || offer.name || 'Offre sans nom' }}
              </div>
              <div v-if="offer.description" class="text-sm text-slate-500 truncate max-w-xs">
                {{ offer.description }}
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">
                {{ getResidenceName(offer) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">
                {{ getVehicleName(offer) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-slate-900">
                {{ formatPrice(offer.discountedPrice || offer.price || 0) }} CFA
              </div>
              <div v-if="offer.originalPrice && offer.discountedPrice" class="text-xs text-slate-500 line-through">
                {{ formatPrice(offer.originalPrice) }} CFA
              </div>
              <div v-if="offer.discount || offer.discountPercentage" class="text-xs text-emerald-600">
                -{{ offer.discount || offer.discountPercentage }}%
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">
                {{ formatDates(offer.startDate, offer.endDate) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getStatusClass(offer.status ?? offer.available ?? 'active')"
              >
                {{ getStatusLabel(offer.status ?? offer.available ?? 'active') }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="position: relative; overflow: visible !important;" @click.stop>
              <div class="relative inline-block text-left">
                <button
                  :ref="el => setButtonRef(offer.id, el)"
                  @click.stop="toggleMenu(offer.id)"
                  class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                  </svg>
                </button>
                <Teleport to="body">
                  <div
                    v-if="openMenuId === offer.id"
                    class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200"
                    :style="getMenuStyle(offer.id)"
                    @click.stop
                    style="z-index: 999999 !important;"
                  >
                  <div class="py-1">
                    <Link
                      :href="route('admin.combo-offers.show', offer.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeMenu"
                    >
                      Voir les détails
                    </Link>
                    <div class="border-t border-slate-200 my-1"></div>
                    <Link
                      :href="route('admin.combo-offers.edit', offer.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeMenu"
                    >
                      Modifier
                    </Link>
                    <div class="border-t border-slate-200 my-1"></div>
                    <button
                      @click="confirmDelete(offer); closeMenu()"
                      class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors"
                    >
                      Supprimer
                    </button>
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
        v-if="pagination"
        :pagination="pagination"
        route-name="admin.combo-offers.index"
        :filters="filters"
      />
    </div>

    <!-- Modal de confirmation de suppression - Téléporté directement dans <body> -->
    <Teleport to="body">
      <div
        v-if="offerToDelete"
        id="modal-suppression-offer-final"
        class="fixed inset-0 z-[999999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
        @click.self="offerToDelete = null"
        style="pointer-events: auto !important;"
      >
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all scale-100 border border-slate-100">
          <div class="mb-6 text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900">Supprimer cette offre combinée ?</h3>
            
            <!-- Message d'avertissement si des réservations existent -->
            <div v-if="offerHasBookings" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-amber-800">Attention !</p>
                  <p class="text-sm text-amber-700 mt-1">{{ offerBookingsMessage || `Cette offre combinée a ${offerBookingsCount} réservation(s) liée(s).` }}</p>
                  <p class="text-sm text-amber-700 mt-1">La suppression n'est pas possible tant que des réservations sont actives.</p>
                </div>
              </div>
            </div>
            
            <p v-else class="text-slate-500 mt-2">
              Êtes-vous sûr de vouloir supprimer l'offre combinée
              <strong class="text-slate-900">{{ offerToDelete.title || offerToDelete.name }}</strong> ?
              Cette action est irréversible. L'offre sera retirée du catalogue.
            </p>
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click.stop="offerToDelete = null"
              class="flex-1 py-3 px-4 rounded-xl bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200 transition-all"
            >
              Annuler
            </button>
            <button
              type="button"
              @click.stop.prevent="deleteOffer"
              :disabled="processing || offerHasBookings || checkingBookings"
              class="flex-1 py-3 px-4 rounded-xl bg-red-600 text-white font-semibold shadow-lg shadow-red-200 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2"
            >
              <span v-if="checkingBookings">Vérification...</span>
              <span v-else-if="processing">Suppression...</span>
              <span v-else-if="offerHasBookings">Impossible</span>
              <span v-else>Confirmer</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue';
import { Teleport } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Package, Calendar, DollarSign, TrendingUp } from 'lucide-vue-next';
import Pagination from '../../Components/Pagination.vue';
import { getStorageImageUrl } from '../../utils/imageUrl';

const props = defineProps<{
  comboOffers: Array<{
    id: number;
    title?: string;
    name?: string;
    description?: string;
    residenceId?: string;
    residence?: any;
    vehicleId?: string;
    vehicle?: any;
    originalPrice?: number;
    discountedPrice?: number;
    price?: number;
    totalPrice?: number;
    discount?: number;
    discountPercentage?: number;
    nbJours?: number;
    imageUrl?: string;
    startDate?: string;
    endDate?: string;
    status?: string;
    available?: boolean;
    isActive?: boolean;
    isVerified?: boolean;
  }>;
  residences?: Array<any>;
  vehicles?: Array<any>;
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
  stats?: {
    totalOffers: number;
    confirmedBookings: number;
    monthRevenue: number;
    conversionRate: number;
  };
}>();

// Gestion des images
const imageErrors = ref<Record<string | number, boolean>>({});

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

const getOfferImage = (offer: any): string | null => {
  if (offer.images && Array.isArray(offer.images) && offer.images.length > 0) {
    return offer.images[0];
  }
  if (offer.imageUrl) {
    return offer.imageUrl;
  }
  if (offer.image) {
    return offer.image;
  }
  return null;
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

const filters = reactive({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
});

const offerToDelete = ref<typeof props.comboOffers[0] | null>(null);
const offerHasBookings = ref(false);
const offerBookingsCount = ref(0);
const offerBookingsMessage = ref<string | null>(null);
const checkingBookings = ref(false);
const processing = ref(false);
const openMenuId = ref<number | null>(null);
const buttonRefs = ref<Record<number, HTMLElement | null>>({});
const menuPositions = ref<Record<number, { top: number; right: number }>>({});

const goToOffer = (offer: (typeof props.comboOffers)[0]) => {
  router.visit(route('admin.combo-offers.show', offer.id));
};

const setButtonRef = (id: number, el: HTMLElement | null) => {
  if (el) {
    buttonRefs.value[id] = el;
  }
};

const updateMenuPosition = (id: number) => {
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

const toggleMenu = (id: number) => {
  if (openMenuId.value === id) {
    openMenuId.value = null;
  } else {
    openMenuId.value = id;
    updateMenuPosition(id);
  }
};

const getMenuStyle = (id: number) => {
  const pos = menuPositions.value[id];
  if (!pos) return { visibility: 'hidden', top: '0px', right: '0px' };
  return {
    top: `${pos.top}px`,
    right: `${pos.right}px`,
  };
};

const closeMenu = () => {
  openMenuId.value = null;
};

// Fermer le menu quand on clique en dehors
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.relative')) {
    closeMenu();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

const applyFilters = () => {
  router.get(route('admin.combo-offers.index'), { ...filters, page: 1 }, {
    preserveState: false,
    preserveScroll: false,
  });
};

const resetFilters = () => {
  filters.search = '';
  filters.status = '';
  applyFilters();
};

const confirmDelete = async (offer: typeof props.comboOffers[0]) => {
  console.log('🔵 confirmDelete offre combinée appelé', { offer, offerId: offer?.id });
  
  if (!offer || !offer.id) {
    console.error('❌ Offre invalide', { offer });
    return;
  }
  
  // Vérifier les réservations avant d'afficher le modal
  checkingBookings.value = true;
  offerHasBookings.value = false;
  offerBookingsCount.value = 0;
  offerBookingsMessage.value = null;
  
  try {
    const response = await fetch(route('admin.combo-offers.check-bookings', offer.id));
    const data = await response.json();
    
    offerHasBookings.value = data.hasBookings || false;
    offerBookingsCount.value = data.bookingsCount || 0;
    offerBookingsMessage.value = data.message || null;
    
    console.log('🔵 Vérification réservations offre combinée', {
      hasBookings: offerHasBookings.value,
      count: offerBookingsCount.value,
      message: offerBookingsMessage.value,
    });
  } catch (error) {
    console.error('❌ Erreur lors de la vérification des réservations', error);
    // En cas d'erreur, on continue quand même (on ne bloque pas)
  } finally {
    checkingBookings.value = false;
  }
  
  // Sauvegarder l'offre dans le ref
  offerToDelete.value = offer;
  console.log('🔵 Modal ouvert - offerToDelete défini', { offerToDelete: offerToDelete.value });
};

const deleteOffer = () => {
  if (!offerToDelete.value || processing.value) {
    console.warn('⚠️ Suppression impossible : offre non définie ou déjà en cours');
    return;
  }

  const offerId = offerToDelete.value.id;
  
  if (!offerId) {
    console.error('❌ Aucun ID d\'offre', { offerToDelete: offerToDelete.value });
    alert('Erreur : Aucun ID d\'offre trouvé. Veuillez réessayer.');
    return;
  }

  router.delete(route('admin.combo-offers.destroy', offerId), {
    onBefore: () => {
      processing.value = true;
      console.log('🚀 Requête DELETE envoyée pour ID:', offerId);
    },
    onSuccess: (page) => {
      offerToDelete.value = null; // Ferme le modal
      processing.value = false;
      
      // Vérifier si un message d'erreur est présent dans les flash messages
      if (page.props.flash?.error) {
        console.error('❌ Échec de la suppression:', page.props.flash.error);
        // Le message d'erreur sera affiché automatiquement par le template
      } else {
        console.log('✅ Offre combinée supprimée avec succès');
      }
    },
    onError: (errors) => {
      processing.value = false;
      offerToDelete.value = null; // Ferme le modal même en cas d'erreur
      console.error('❌ Échec de la suppression:', errors);
    },
    preserveScroll: true
  });
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatDates = (startDate?: string, endDate?: string): string => {
  if (!startDate || !endDate) {
    return 'Dates non définies';
  }
  
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })} - ${end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}`;
  } catch {
    return `${startDate} - ${endDate}`;
  }
};

const getResidenceName = (offer: typeof props.comboOffers[0]): string => {
  if (offer.residence) {
    return offer.residence.nom || offer.residence.name || offer.residence.title || 'N/A';
  }
  return 'N/A';
};

const getVehicleName = (offer: typeof props.comboOffers[0]): string => {
  if (offer.vehicle) {
    // Prioriser title, puis titre, puis name, puis construire depuis marque + modele
    const vehicle = offer.vehicle;
    if (vehicle.title) return vehicle.title;
    if (vehicle.titre) return vehicle.titre;
    if (vehicle.name) return vehicle.name;
    const constructed = `${vehicle.marque || vehicle.brand || ''} ${vehicle.modele || vehicle.model || ''}`.trim();
    if (constructed) return constructed;
    return 'N/A';
  }
  return 'N/A';
};

const getStatusClass = (status: string | boolean): string => {
  if (typeof status === 'boolean') {
    return status
      ? 'bg-emerald-100 text-emerald-700'
      : 'bg-red-100 text-red-700';
  }

  const statusLower = status.toLowerCase();
  if (statusLower === 'active' || statusLower === 'actif') {
    return 'bg-emerald-100 text-emerald-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getStatusLabel = (status: string | boolean): string => {
  if (typeof status === 'boolean') {
    return status ? 'Active' : 'Inactive';
  }

  const statusMap: Record<string, string> = {
    active: 'Active',
    actif: 'Active',
    inactive: 'Inactive',
    inactif: 'Inactive',
  };

  return statusMap[status.toLowerCase()] || status;
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.combo-offers.index': '/admin/combo-offers',
    'admin.combo-offers.create': '/admin/combo-offers/create',
    'admin.combo-offers.show': (id: number) => `/admin/combo-offers/${id}`,
    'admin.combo-offers.edit': (id: number) => `/admin/combo-offers/${id}/edit`,
    'admin.combo-offers.destroy': (id: number) => `/admin/combo-offers/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};
</script>

