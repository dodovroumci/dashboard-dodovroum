<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="min-w-0 flex-1">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Mes offres combinées</h1>
        <p class="text-sm text-slate-500 mt-1">Voir toutes vos offres résidence + véhicule</p>
      </div>
      <Link
        href="/owner/combo-offers/create"
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
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ error }}
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
    <div class="bg-white border border-slate-200 rounded-xl" style="overflow-x: auto;">
      <div v-if="comboOffers.length === 0" class="p-12 text-center">
        <p class="text-slate-500">Aucune offre combinée trouvée</p>
        <p class="text-sm text-slate-400 mt-2">Vous n'avez pas encore d'offres combinées.</p>
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
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
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
              <div v-if="offer.originalPrice && offer.discountedPrice && offer.originalPrice > offer.discountedPrice" class="text-xs text-slate-500 line-through">
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
                :class="getStatusClass(offer.status ?? (offer.available ? 'active' : 'inactive'))"
              >
                {{ getStatusLabel(offer.status ?? (offer.available ? 'active' : 'inactive')) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="position: relative;" @click.stop>
              <div class="flex items-center justify-end">
                <div class="relative inline-block text-left">
                  <button
                    :ref="el => setButtonRef(offer.id, el)"
                    @click.stop="toggleMenu(offer.id)"
                    class="p-1 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition"
                    :class="{ 'bg-slate-100 text-slate-900': openMenus.has(offer.id) }"
                  >
                    <MoreVertical class="w-5 h-5" />
                  </button>
                  <Teleport to="body">
                    <div
                      v-if="openMenus.has(offer.id)"
                      class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200 z-50"
                      :style="getMenuStyle(offer.id)"
                      @click.stop
                    >
                      <div class="py-1">
                        <Link
                          :href="`/owner/combo-offers/${offer.id}`"
                          @click="closeMenu(offer.id)"
                          class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                        >
                          <Eye class="w-4 h-4" />
                          Voir
                        </Link>
                        <Link
                          v-if="offer.canEdit !== false"
                          :href="`/owner/combo-offers/${offer.id}/edit`"
                          @click="closeMenu(offer.id)"
                          class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                        >
                          <Pencil class="w-4 h-4" />
                          Modifier
                        </Link>
                        <button
                          v-else
                          disabled
                          class="block w-full text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
                          title="Vous n'avez pas les droits pour modifier cette offre"
                        >
                          <Pencil class="w-4 h-4" />
                          Modifier (non autorisé)
                        </button>
                        <button
                          v-if="offer.canEdit !== false"
                          @click="confirmDelete(offer); closeMenu(offer.id)"
                          class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                        >
                          <Trash2 class="w-4 h-4" />
                          Supprimer
                        </button>
                        <button
                          v-else
                          disabled
                          class="w-full text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
                          title="Vous n'avez pas les droits pour supprimer cette offre"
                        >
                          <Trash2 class="w-4 h-4" />
                          Supprimer (non autorisé)
                        </button>
                      </div>
                    </div>
                  </Teleport>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <Pagination
      v-if="pagination && pagination.last_page > 1"
      :pagination="pagination"
      route-name="owner.combo-offers.index"
      :filters="filters"
    />

    <!-- Modal de confirmation de suppression -->
    <div
      v-if="offerToDelete"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="offerToDelete = null"
    >
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Confirmer la suppression</h3>
        <p class="text-slate-600 mb-6">
          Êtes-vous sûr de vouloir supprimer l'offre combinée
          <strong>{{ offerToDelete.title || offerToDelete.name || 'cette offre' }}</strong> ?
          Cette action est irréversible.
        </p>
        <div class="flex justify-end gap-3">
          <button
            @click="offerToDelete = null"
            class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
          >
            Annuler
          </button>
          <button
            @click="deleteOffer"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive, computed, ref, onMounted, onUnmounted, Teleport } from 'vue';
import { Eye, Pencil, Trash2, MoreVertical, Plus, Package, Calendar, DollarSign, TrendingUp } from 'lucide-vue-next';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  comboOffers: Array<{
    id: number | string;
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
    canEdit?: boolean;
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
    totalOffers: number;
    activeOffers: number;
    totalBookings: number;
    confirmedBookings: number;
    monthRevenue: number;
    conversionRate: number;
  };
}>();

const page = usePage();
const error = computed(() => props.error || page.props.error);

// Gestion des images
const imageErrors = ref<Record<string | number, boolean>>({});

const getOfferImage = (offer: any): string | null => {
  if (offer.images && Array.isArray(offer.images) && offer.images.length > 0) {
    return offer.images[0];
  }
  if (offer.imageUrl) {
    return offer.imageUrl;
  }
  // Essayer d'obtenir l'image de la résidence
  if (offer.residence) {
    if (offer.residence.images && Array.isArray(offer.residence.images) && offer.residence.images.length > 0) {
      return offer.residence.images[0];
    }
    if (offer.residence.imageUrl) {
      return offer.residence.imageUrl;
    }
  }
  // Essayer d'obtenir l'image du véhicule
  if (offer.vehicle || offer.voiture) {
    const vehicle = offer.vehicle || offer.voiture;
    if (vehicle.images && Array.isArray(vehicle.images) && vehicle.images.length > 0) {
      return vehicle.images[0];
    }
    if (vehicle.imageUrl) {
      return vehicle.imageUrl;
    }
  }
  return null;
};

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

const filters = reactive({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
});

const applyFilters = () => {
  router.get('/owner/combo-offers', filters, {
    preserveState: true,
    preserveScroll: true,
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

const formatDates = (startDate?: string, endDate?: string): string => {
  if (!startDate || !endDate) return 'Non spécifié';
  
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })} - ${end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}`;
  } catch {
    return `${startDate} - ${endDate}`;
  }
};

const getResidenceName = (offer: any): string => {
  if (offer.residence) {
    return offer.residence.nom || offer.residence.name || offer.residence.title || 'Résidence inconnue';
  }
  return 'Résidence inconnue';
};

const getVehicleName = (offer: any): string => {
  // L'API peut retourner 'voiture' ou 'vehicle'
  const vehicle = offer.vehicle || offer.voiture;
  
  if (vehicle) {
    // Prioriser le titre, puis le nom, puis marque + modèle
    const titre = vehicle.titre || vehicle.title || '';
    if (titre) return titre;
    
    const name = vehicle.nom || vehicle.name || '';
    if (name) return name;
    
    const brand = vehicle.marque || vehicle.brand || '';
    const model = vehicle.modele || vehicle.model || '';
    if (brand || model) {
      const fullName = `${brand} ${model}`.trim();
      if (fullName) return fullName;
    }
  }
  
  // Si aucun nom trouvé, retourner un message plus informatif
  return 'Véhicule non spécifié';
};

const getStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'active') {
    return 'bg-emerald-100 text-emerald-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getStatusLabel = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'active') {
    return 'Active';
  }
  return 'Inactive';
};

// Gestion du menu d'actions
const openMenus = ref(new Set<string | number>());
const buttonRefs = ref<Map<string | number, HTMLElement>>(new Map());

const goToOffer = (offer: (typeof props.comboOffers)[0]) => {
  router.visit(`/owner/combo-offers/${offer.id}`);
};

const setButtonRef = (id: string | number, el: HTMLElement | null) => {
  if (el) {
    buttonRefs.value.set(id, el);
  } else {
    buttonRefs.value.delete(id);
  }
};

const getMenuStyle = (id: string | number) => {
  const button = buttonRefs.value.get(id);
  if (!button) return { display: 'none' };
  
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

const toggleMenu = (id: number | string) => {
  if (openMenus.value.has(id)) {
    openMenus.value.delete(id);
  } else {
    openMenus.value.clear();
    openMenus.value.add(id);
  }
};

const closeMenu = (id: number | string) => {
  openMenus.value.delete(id);
};

// Fermer les menus au clic extérieur
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.relative')) {
    openMenus.value.clear();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Gestion de la suppression
const offerToDelete = ref<typeof props.comboOffers[0] | null>(null);

const confirmDelete = (offer: typeof props.comboOffers[0]) => {
  offerToDelete.value = offer;
};

const deleteOffer = () => {
  if (!offerToDelete.value) return;
  
  router.delete(`/owner/combo-offers/${offerToDelete.value.id}`, {
    onSuccess: () => {
      offerToDelete.value = null;
    },
    onError: () => {
      offerToDelete.value = null;
    },
  });
};
</script>

