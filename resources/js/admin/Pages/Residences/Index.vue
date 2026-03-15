<template>
  <div class="space-y-4 sm:space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Résidences</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gérer vos résidences et logements</p>
      </div>
      <Link
        :href="route('admin.residences.create')"
        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shrink-0"
      >
        + Ajouter une résidence
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
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6">
        <div class="flex items-center justify-between mb-2">
          <Building2 class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Total résidences</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.totalResidences || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Calendar class="w-5 h-5 text-amber-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Réservations en cours</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.activeBookings || 0) }}</p>
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
          <CheckCircle class="w-5 h-5 text-green-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Résidences disponibles</p>
        <p class="text-2xl font-semibold text-green-600">{{ formatNumber(stats?.availableResidences || 0) }}</p>
      </div>
    </div>

    <!-- Filtres -->
    <form @submit.prevent="applyFilters" class="bg-white border border-slate-200 rounded-xl p-4 sm:p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select v-model="filters.type" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les types</option>
          <option value="villa">Villa</option>
          <option value="appartement">Appartement</option>
          <option value="maison">Maison</option>
          <option value="studio">Studio</option>
        </select>
        <select v-model="filters.status" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les statuts</option>
          <option value="available">Disponible</option>
          <option value="occupied">Occupé</option>
          <option value="maintenance">Maintenance</option>
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

    <!-- Tableau des résidences -->
    <div class="bg-white border border-slate-200 rounded-xl table-scroll-wrap">
      <div v-if="residences.length === 0" class="p-8 sm:p-12 text-center">
        <p class="text-slate-500">Aucune résidence trouvée</p>
        <Link
          :href="route('admin.residences.create')"
          class="mt-4 inline-block px-4 py-2 text-blue-600 hover:text-blue-700"
        >
          Ajouter votre première résidence
        </Link>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Résidence
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Type
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Localisation
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Prix/Nuit
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
            v-for="residence in residences"
            :key="residence.id"
            class="hover:bg-slate-50 cursor-pointer transition-colors"
            @click="goToResidence(residence)"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img
                    v-if="getResidenceImage(residence) && !imageErrors[residence.id]"
                    :src="getStorageImageUrl(getResidenceImage(residence), 'residences')"
                    :alt="residence.title || residence.name || 'Résidence'"
                    class="w-full h-full object-cover"
                    @error="() => handleImageError(residence.id)"
                    @load="() => imageErrors[residence.id] = false"
                  />
                  <Building2
                    v-else
                    class="w-6 h-6 text-slate-400"
                  />
                </div>
                <div>
                  <div class="text-sm font-medium text-slate-900">
                    {{ residence.title || residence.name || 'Résidence sans nom' }}
                  </div>
                  <div class="text-sm text-slate-500">
                    {{ residence.bedrooms ?? residence.nombreChambres ?? 0 }} chambres • {{ residence.capacity ?? residence.capacite ?? 0 }} personnes
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="text-sm text-slate-900">
                {{ formatType(residence.type || residence.typeResidence) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">{{ (residence.address ?? residence.adresse) || (residence.city ?? residence.ville) || '—' }}</div>
              <div class="text-sm text-slate-500">{{ (residence.address ?? residence.adresse) && (residence.city ?? residence.ville) ? (residence.city ?? residence.ville) : '' }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="text-sm font-medium text-slate-900">
                {{ formatPrice(residence.pricePerNight || residence.price || 0) }} CFA
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getStatusClass(residence.available ?? residence.status ?? 'available')"
              >
                {{ getStatusLabel(residence.available ?? residence.status ?? 'available') }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="position: relative; overflow: visible !important;" @click.stop>
              <div class="relative inline-block text-left">
                <button
                  :ref="el => setButtonRef(residence.id, el)"
                  @click.stop="toggleMenu(residence.id)"
                  class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                  </svg>
                </button>
                <Teleport to="body">
                  <div
                    v-if="openMenuId === residence.id"
                    class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200"
                    :style="getMenuStyle(residence.id)"
                    @click.stop
                    style="z-index: 999999 !important;"
                  >
                  <div class="py-1">
                    <Link
                      :href="route('admin.residences.show', residence.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeMenu"
                    >
                      Voir les détails
                    </Link>
                    <div class="border-t border-slate-200 my-1"></div>
                    <Link
                      :href="route('admin.residences.edit', residence.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeMenu"
                    >
                      Modifier
                    </Link>
                    <div class="border-t border-slate-200 my-1"></div>
                    <button
                      @click="confirmDelete(residence); closeMenu()"
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
        route-name="admin.residences.index"
        :filters="filters"
      />
    </div>

    <!-- Modal de confirmation de suppression - Téléporté directement dans <body> -->
    <Teleport to="body">
      <div
        v-if="residenceToDelete"
        id="modal-suppression-residence-final"
        class="fixed inset-0 z-[999999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
        @click.self="residenceToDelete = null"
        style="pointer-events: auto !important;"
      >
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all scale-100 border border-slate-100">
          <div class="mb-6 text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900">Supprimer cette résidence ?</h3>
            
            <!-- Message d'avertissement si des réservations existent -->
            <div v-if="residenceHasBookings" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-amber-800">Attention !</p>
                  <p class="text-sm text-amber-700 mt-1">{{ residenceBookingsMessage || `Cette résidence a ${residenceBookingsCount} réservation(s) liée(s).` }}</p>
                  <p class="text-sm text-amber-700 mt-1">La suppression n'est pas possible tant que des réservations sont actives.</p>
                </div>
              </div>
            </div>
            
            <p v-else class="text-slate-500 mt-2">
              Êtes-vous sûr de vouloir supprimer la résidence
              <strong class="text-slate-900">{{ residenceToDelete.title || residenceToDelete.name }}</strong> ?
              Cette action est irréversible. La résidence sera retirée du catalogue.
            </p>
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click.stop="residenceToDelete = null"
              class="flex-1 py-3 px-4 rounded-xl bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200 transition-all"
            >
              Annuler
            </button>
            <button
              type="button"
              @click.stop.prevent="deleteResidence"
              :disabled="processing || residenceHasBookings || checkingBookings"
              class="flex-1 py-3 px-4 rounded-xl bg-red-600 text-white font-semibold shadow-lg shadow-red-200 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2"
            >
              <span v-if="checkingBookings">Vérification...</span>
              <span v-else-if="processing">Suppression...</span>
              <span v-else-if="residenceHasBookings">Impossible</span>
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
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Calendar, DollarSign, CheckCircle } from 'lucide-vue-next';
import Pagination from '../../Components/Pagination.vue';
import AdminLayout from '../../Components/Layouts/AdminLayout.vue';
import { getStorageImageUrl } from '../../utils/imageUrl';

defineOptions({
  layout: AdminLayout,
});

const props = defineProps<{
  residences: Array<{
    id: number;
    title?: string;
    name?: string;
    type?: string;
    typeResidence?: string;
    address?: string;
    city?: string;
    pricePerNight?: number;
    price?: number;
    bedrooms?: number;
    capacity?: number;
    available?: boolean;
    status?: string;
  }>;
  filters?: {
    search?: string;
    type?: string;
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
    totalResidences: number;
    availableResidences: number;
    activeBookings: number;
    monthRevenue: number;
  };
}>();

// Gestion des images
const imageErrors = ref<Record<string | number, boolean>>({});

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

/**
 * Normaliser les images : version simplifiée alignée sur le Mapper PHP
 * Le mapper PHP fait déjà le travail, on ne fait qu'un fallback de sécurité
 */
const normalizeResidenceImages = (rawImages: string | string[] | undefined): string[] => {
  if (!rawImages) return [];
  
  // Si c'est déjà un tableau (merci le Mapper PHP)
  if (Array.isArray(rawImages)) {
    return rawImages.filter(url => url && typeof url === 'string' && url.length > 0);
  }
  
  // Si c'est une string (fallback sécurité)
  if (typeof rawImages === 'string') {
    if (rawImages.trim().startsWith('[') || rawImages.trim().startsWith('{')) {
      try {
        const parsed = JSON.parse(rawImages);
        if (Array.isArray(parsed)) {
          return parsed.filter(url => url && typeof url === 'string' && url.length > 0);
        }
      } catch (e) {
        console.error("Erreur de parsing des images:", e);
        return [];
      }
    }
    return [rawImages];
  }
  
  return [];
};

const getResidenceImage = (residence: any): string | null => {
  // Le mapper PHP a déjà normalisé les images, on récupère simplement la première
  const normalized = normalizeResidenceImages(residence.images);
  if (normalized.length > 0) {
    return normalized[0];
  }
  // Fallback sur les autres formats
  if (residence.imageUrl) {
    return residence.imageUrl;
  }
  if (residence.image) {
    return residence.image;
  }
  return null;
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

const filters = reactive({
  search: props.filters?.search || '',
  type: props.filters?.type || '',
  status: props.filters?.status || '',
});

const residenceToDelete = ref<typeof props.residences[0] | null>(null);
const residenceHasBookings = ref(false);
const residenceBookingsCount = ref(0);
const residenceBookingsMessage = ref<string | null>(null);
const checkingBookings = ref(false);
const processing = ref(false);
const openMenuId = ref<number | null>(null);
const buttonRefs = ref<Record<number, HTMLElement | null>>({});
const menuPositions = ref<Record<number, { top: number; right: number }>>({});

const goToResidence = (residence: (typeof props.residences)[0]) => {
  router.visit(route('admin.residences.show', residence.id));
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
  router.get(route('admin.residences.index'), { ...filters, page: 1 }, {
    preserveState: false,
    preserveScroll: false,
  });
};

const resetFilters = () => {
  filters.search = '';
  filters.type = '';
  filters.status = '';
  applyFilters();
};

const confirmDelete = async (residence: typeof props.residences[0]) => {
  console.log('🔵 confirmDelete résidence appelé', { residence, residenceId: residence?.id });
  
  if (!residence || !residence.id) {
    console.error('❌ Résidence invalide', { residence });
    return;
  }
  
  // Vérifier les réservations avant d'afficher le modal
  checkingBookings.value = true;
  residenceHasBookings.value = false;
  residenceBookingsCount.value = 0;
  residenceBookingsMessage.value = null;
  
  try {
    const response = await fetch(route('admin.residences.check-bookings', residence.id));
    const data = await response.json();
    
    residenceHasBookings.value = data.hasBookings || false;
    residenceBookingsCount.value = data.bookingsCount || 0;
    residenceBookingsMessage.value = data.message || null;
    
    console.log('🔵 Vérification réservations résidence', {
      hasBookings: residenceHasBookings.value,
      count: residenceBookingsCount.value,
      message: residenceBookingsMessage.value,
    });
  } catch (error) {
    console.error('❌ Erreur lors de la vérification des réservations', error);
    // En cas d'erreur, on continue quand même (on ne bloque pas)
  } finally {
    checkingBookings.value = false;
  }
  
  // Sauvegarder la résidence dans le ref
  residenceToDelete.value = residence;
  console.log('🔵 Modal ouvert - residenceToDelete défini', { residenceToDelete: residenceToDelete.value });
};

const deleteResidence = () => {
  if (!residenceToDelete.value || processing.value) {
    console.warn('⚠️ Suppression impossible : résidence non définie ou déjà en cours');
    return;
  }

  const residenceId = residenceToDelete.value.id;
  
  if (!residenceId) {
    console.error('❌ Aucun ID de résidence', { residenceToDelete: residenceToDelete.value });
    alert('Erreur : Aucun ID de résidence trouvé. Veuillez réessayer.');
    return;
  }

  router.delete(route('admin.residences.destroy', residenceId), {
    onBefore: () => {
      processing.value = true;
      console.log('🚀 Requête DELETE envoyée pour ID:', residenceId);
    },
    onSuccess: (page) => {
      residenceToDelete.value = null; // Ferme le modal
      processing.value = false;
      
      // Vérifier si un message d'erreur est présent dans les flash messages
      if (page.props.flash?.error) {
        console.error('❌ Échec de la suppression:', page.props.flash.error);
        // Le message d'erreur sera affiché automatiquement par le template
      } else {
        console.log('✅ Résidence supprimée avec succès');
      }
    },
    onError: (errors) => {
      processing.value = false;
      residenceToDelete.value = null; // Ferme le modal même en cas d'erreur
      console.error('❌ Échec de la suppression:', errors);
    },
    preserveScroll: true
  });
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatType = (type: string): string => {
  const typeMap: Record<string, string> = {
    villa: 'Villa',
    appartement: 'Appartement',
    maison: 'Maison',
    studio: 'Studio',
  };
  return typeMap[type?.toLowerCase()] || type || 'N/A';
};

const getStatusClass = (status: string | boolean): string => {
  if (typeof status === 'boolean') {
    return status
      ? 'bg-emerald-100 text-emerald-700'
      : 'bg-red-100 text-red-700';
  }

  const statusLower = status.toLowerCase();
  if (statusLower === 'available' || statusLower === 'disponible') {
    return 'bg-emerald-100 text-emerald-700';
  }
  if (statusLower === 'occupied' || statusLower === 'occupé') {
    return 'bg-amber-100 text-amber-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getStatusLabel = (status: string | boolean): string => {
  if (typeof status === 'boolean') {
    return status ? 'Disponible' : 'Indisponible';
  }

  const statusMap: Record<string, string> = {
    available: 'Disponible',
    disponible: 'Disponible',
    occupied: 'Occupé',
    occupé: 'Occupé',
    maintenance: 'Maintenance',
  };

  return statusMap[status.toLowerCase()] || status;
};

// Helper pour les routes
const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.residences.index': '/admin/residences',
    'admin.residences.create': '/admin/residences/create',
    'admin.residences.show': (id: number) => `/admin/residences/${id}`,
    'admin.residences.edit': (id: number) => `/admin/residences/${id}/edit`,
    'admin.residences.destroy': (id: number) => `/admin/residences/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};
</script>
