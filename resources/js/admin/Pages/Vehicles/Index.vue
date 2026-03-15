<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Véhicules</h1>
        <p class="text-sm text-slate-500 mt-1 truncate">Gérer votre flotte de véhicules</p>
      </div>
      <Link
        :href="route('admin.vehicles.create')"
        class="flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 min-h-[44px] bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm whitespace-nowrap w-full sm:w-auto shrink-0"
      >
        <span aria-hidden="true">+</span>
        <span><span class="sm:hidden">Ajouter</span><span class="hidden sm:inline">Ajouter un véhicule</span></span>
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
          <Truck class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Total véhicules</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.totalVehicles || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Calendar class="w-5 h-5 text-amber-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Réservations en cours</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.totalBookings || 0) }}</p>
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
        <p class="text-sm text-slate-500 mb-1">Véhicules disponibles</p>
        <p class="text-2xl font-semibold text-green-600">{{ formatNumber(stats?.availableVehicles || 0) }}</p>
      </div>
    </div>

    <!-- Filtres -->
    <form @submit.prevent="applyFilters" class="bg-white border border-slate-200 rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select v-model="filters.type" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les types</option>
          <option
            v-for="vehicleType in vehicleTypes"
            :key="vehicleType.value"
            :value="vehicleType.value"
          >
            {{ vehicleType.label }}
          </option>
        </select>
        <select v-model="filters.status" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les statuts</option>
          <option value="available">Disponible</option>
          <option value="rented">En location</option>
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

    <!-- Tableau des véhicules -->
    <div class="bg-white border border-slate-200 rounded-xl" style="overflow-x: auto; overflow-y: visible;">
      <div v-if="vehicles.length === 0" class="p-12 text-center">
        <p class="text-slate-500">Aucun véhicule trouvé</p>
        <Link
          :href="route('admin.vehicles.create')"
          class="mt-4 inline-block px-4 py-2 text-blue-600 hover:text-blue-700"
        >
          Ajouter votre premier véhicule
        </Link>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Véhicule
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Type
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Prix/Jour
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
            v-for="vehicle in vehicles"
            :key="vehicle.id"
            class="hover:bg-slate-50 cursor-pointer transition-colors"
            @click="goToVehicle(vehicle)"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img
                    v-if="getVehicleImage(vehicle) && !imageErrors[vehicle.id]"
                    :src="getStorageImageUrl(getVehicleImage(vehicle), 'vehicles')"
                    :alt="vehicle.name || 'Véhicule'"
                    class="w-full h-full object-cover"
                    @error="() => handleImageError(vehicle.id)"
                    @load="() => imageErrors[vehicle.id] = false"
                  />
                  <component
                    v-else
                    :is="getVehicleIcon(vehicle.type)"
                    class="w-6 h-6 text-slate-400"
                  />
                </div>
                <div>
                  <div class="text-sm font-medium text-slate-900">
                    {{ vehicle.name || `${vehicle.brand} ${vehicle.model}` || 'Véhicule sans nom' }}
                  </div>
                  <div class="text-sm text-slate-500">
                    {{ vehicle.year || 'N/A' }} • {{ vehicle.seats || 0 }} places
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="text-sm text-slate-900">
                {{ formatType(vehicle.type) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="text-sm font-medium text-slate-900">
                {{ formatPrice(vehicle.pricePerDay || vehicle.price_per_day || vehicle.price || 0) }} CFA
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getStatusClass(vehicle.available ?? vehicle.status ?? 'available')"
              >
                {{ getStatusLabel(vehicle.available ?? vehicle.status ?? 'available') }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="position: relative; overflow: visible !important;" @click.stop>
              <div class="relative inline-block text-left">
                <button
                  :ref="el => setButtonRef(vehicle.id, el)"
                  @click.stop="toggleMenu(vehicle.id)"
                  class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                  </svg>
                </button>
                <Teleport to="body">
                  <div
                    v-if="openMenuId === vehicle.id"
                    class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200"
                    :style="getMenuStyle(vehicle.id)"
                    @click.stop
                    style="z-index: 999999 !important;"
                  >
                  <div class="py-1">
                    <Link
                      :href="route('admin.vehicles.show', vehicle.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeMenu"
                    >
                      Voir les détails
                    </Link>
                    <div class="border-t border-slate-200 my-1"></div>
                    <Link
                      :href="route('admin.vehicles.edit', vehicle.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                      @click="closeMenu"
                    >
                      Modifier
                    </Link>
                    <div class="border-t border-slate-200 my-1"></div>
                    <button
                      type="button"
                      @click.stop.prevent="() => { console.log('🔴 Clic sur Supprimer dans le menu', { vehicle }); confirmDelete(vehicle); closeMenu(); }"
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
        route-name="admin.vehicles.index"
        :filters="filters"
      />
    </div>

    <!-- Modal de confirmation de suppression - Téléporté directement dans <body> pour éviter les scripts -->
    <Teleport to="body">
      <div 
        v-if="vehicleToDelete" 
        id="modal-suppression-final"
        class="fixed inset-0 z-[999999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
        @click.self="vehicleToDelete = null"
        style="pointer-events: auto !important;"
      >
        <div 
          class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all scale-100 border border-slate-100"
          @click.stop
        >
          <div class="mb-6 text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900">Supprimer ce véhicule ?</h3>
            
            <!-- Message d'avertissement si des réservations existent -->
            <div v-if="vehicleHasBookings" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-amber-800">Attention !</p>
                  <p class="text-sm text-amber-700 mt-1">{{ vehicleBookingsMessage || `Ce véhicule a ${vehicleBookingsCount} réservation(s) liée(s).` }}</p>
                  <p v-if="vehicleComboOffersCount > 0" class="text-sm text-amber-700 mt-1">
                    Ce véhicule est également utilisé dans {{ vehicleComboOffersCount }} offre(s) combinée(s).
                  </p>
                  <p class="text-sm text-amber-700 mt-1">La suppression n'est pas possible tant que des données sont liées.</p>
                </div>
              </div>
            </div>
            
            <p v-else class="text-slate-500 mt-2">
              Êtes-vous sûr de vouloir supprimer le véhicule
              <strong class="text-slate-900">{{ vehicleToDelete.name || `${vehicleToDelete.brand} ${vehicleToDelete.model}` }}</strong> ?
              Cette action est irréversible. Le bolide sera retiré du garage.
            </p>
          </div>

          <div class="flex gap-3">
            <button 
              type="button"
              @click.stop="vehicleToDelete = null" 
              class="flex-1 py-3 px-4 rounded-xl bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200 transition-all"
            >
              Annuler
            </button>
            <button 
              type="button"
              @click.stop.prevent="handleConfirmDelete" 
              :disabled="processing || vehicleHasBookings || checkingBookings"
              class="flex-1 py-3 px-4 rounded-xl bg-red-600 text-white font-semibold shadow-lg shadow-red-200 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2"
            >
              <span v-if="checkingBookings">Vérification...</span>
              <span v-else-if="processing">Suppression...</span>
              <span v-else-if="vehicleHasBookings">Impossible</span>
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
import { Truck, Car, Bike, Calendar, DollarSign, CheckCircle } from 'lucide-vue-next';
import Pagination from '../../Components/Pagination.vue';
import AdminLayout from '../../Components/Layouts/AdminLayout.vue';
import { getStorageImageUrl } from '../../utils/imageUrl';

defineOptions({
  layout: AdminLayout,
});

const props = defineProps<{
  vehicles: Array<{
    id: number;
    name?: string;
    brand?: string;
    model?: string;
    year?: number;
    type?: string;
    seats?: number;
    plateNumber?: string;
    plate_number?: string;
    pricePerDay?: number;
    price_per_day?: number;
    price?: number;
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
  vehicleTypes?: Array<{ value: string; label: string }>;
  stats?: {
    totalVehicles: number;
    availableVehicles: number;
    totalBookings: number;
    monthRevenue: number;
  };
}>();

// Gestion des images
const imageErrors = ref<Record<string | number, boolean>>({});

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

const getVehicleImage = (vehicle: any): string | null => {
  if (vehicle.images && Array.isArray(vehicle.images) && vehicle.images.length > 0) {
    return vehicle.images[0];
  }
  if (vehicle.imageUrl) {
    return vehicle.imageUrl;
  }
  if (vehicle.image) {
    return vehicle.image;
  }
  return null;
};

const getVehicleIcon = (type?: string) => {
  if (!type) return Car;
  
  const typeLower = type.toLowerCase();
  if (typeLower === 'moto' || typeLower === 'motorcycle' || typeLower === 'scooter') {
    return Bike;
  }
  return Car;
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

// Utiliser les types depuis l'API ou les types par défaut
const vehicleTypes = props.vehicleTypes || [
  { value: 'berline', label: 'Berline' },
  { value: 'suv', label: 'SUV' },
  { value: '4x4', label: '4x4' },
  { value: 'utilitaire', label: 'Utilitaire' },
  { value: 'moto', label: 'Moto' },
];

const filters = reactive({
  search: props.filters?.search || '',
  type: props.filters?.type || '',
  status: props.filters?.status || '',
});

const vehicleToDelete = ref<typeof props.vehicles[0] | null>(null);
const vehicleHasBookings = ref(false);
const vehicleBookingsCount = ref(0);
const vehicleComboOffersCount = ref(0);
const vehicleBookingsMessage = ref<string | null>(null);
const checkingBookings = ref(false);
const openMenuId = ref<number | null>(null);
const buttonRefs = ref<Record<number, HTMLElement | null>>({});
const menuPositions = ref<Record<number, { top: number; right: number }>>({});
const processing = ref(false);

const goToVehicle = (vehicle: (typeof props.vehicles)[0]) => {
  router.visit(route('admin.vehicles.show', vehicle.id));
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

// Le onUnmounted pour handleClickOutside est maintenant fusionné avec celui du modal (voir ligne ~730)

const applyFilters = () => {
  router.get(route('admin.vehicles.index'), { ...filters, page: 1 }, {
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

const confirmDelete = async (vehicle: typeof props.vehicles[0]) => {
  console.log('🔵 confirmDelete appelé', { vehicle, vehicleId: vehicle?.id });
  
  if (!vehicle || !vehicle.id) {
    console.error('❌ Véhicule invalide', { vehicle });
    return;
  }
  
  // Vérifier les réservations et offres combinées avant d'afficher le modal
  checkingBookings.value = true;
  vehicleHasBookings.value = false;
  vehicleBookingsCount.value = 0;
  vehicleComboOffersCount.value = 0;
  vehicleBookingsMessage.value = null;
  
  try {
    const response = await fetch(route('admin.vehicles.check-bookings', vehicle.id));
    const data = await response.json();
    
    vehicleHasBookings.value = data.hasBookings || false;
    vehicleBookingsCount.value = data.bookingsCount || 0;
    vehicleComboOffersCount.value = data.comboOffersCount || 0;
    vehicleBookingsMessage.value = data.message || null;
    
    console.log('🔵 Vérification réservations et offres combinées', {
      hasBookings: vehicleHasBookings.value,
      bookingsCount: vehicleBookingsCount.value,
      comboOffersCount: vehicleComboOffersCount.value,
      message: vehicleBookingsMessage.value,
    });
  } catch (error) {
    console.error('❌ Erreur lors de la vérification des réservations', error);
    // En cas d'erreur, on continue quand même (on ne bloque pas)
  } finally {
    checkingBookings.value = false;
  }
  
  // Sauvegarder le véhicule dans le ref - le Teleport s'occupe du reste
  vehicleToDelete.value = vehicle;
  console.log('🔵 Modal ouvert - vehicleToDelete défini', { vehicleToDelete: vehicleToDelete.value });
};

/**
 * Exécute la suppression définitive via Inertia
 */
const handleConfirmDelete = (): void => {
  if (!vehicleToDelete.value || processing.value) {
    console.warn('⚠️ Suppression impossible : véhicule non défini ou déjà en cours');
    return;
  }

  const vehicleId = vehicleToDelete.value.id;
  
  if (!vehicleId) {
    console.error('❌ Aucun ID de véhicule', { vehicleToDelete: vehicleToDelete.value });
    alert('Erreur : Aucun ID de véhicule trouvé. Veuillez réessayer.');
    return;
  }

  router.delete(route('admin.vehicles.destroy', vehicleId), {
    onBefore: () => {
      processing.value = true;
      console.log('🚀 Requête DELETE envoyée pour ID:', vehicleId);
    },
    onSuccess: (page) => {
      vehicleToDelete.value = null; // Ferme le modal
      processing.value = false;
      
      // Vérifier si un message d'erreur est présent dans les flash messages
      if (page.props.flash?.error) {
        console.error('❌ Échec de la suppression:', page.props.flash.error);
        // Le message d'erreur sera affiché automatiquement par le template
      } else {
        console.log('✅ Véhicule supprimé avec succès');
      }
    },
    onError: (errors) => {
      processing.value = false;
      vehicleToDelete.value = null; // Ferme le modal même en cas d'erreur
      console.error('❌ Échec de la suppression:', errors);
    },
    preserveScroll: true
  });
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatType = (type: string): string => {
  if (!type) return 'N/A';
  
  // Chercher le type dans la liste des types de l'API
  const vehicleType = vehicleTypes.find(vt => vt.value.toLowerCase() === type.toLowerCase());
  if (vehicleType) {
    return vehicleType.label;
  }
  
  // Fallback sur un mapping par défaut si le type n'est pas trouvé
  const typeMap: Record<string, string> = {
    berline: 'Berline',
    suv: 'SUV',
    '4x4': '4x4',
    utilitaire: 'Utilitaire',
    moto: 'Moto',
    sedan: 'Berline',
    truck: 'Camion',
    car: 'Voiture',
    motorcycle: 'Moto',
    bicycle: 'Vélo',
    scooter: 'Scooter',
    van: 'Van',
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
  if (statusLower === 'rented' || statusLower === 'en_location' || statusLower === 'loué') {
    return 'bg-amber-100 text-amber-700';
  }
  if (statusLower === 'maintenance') {
    return 'bg-red-100 text-red-700';
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
    rented: 'En location',
    en_location: 'En location',
    loué: 'En location',
    maintenance: 'Maintenance',
  };

  return statusMap[status.toLowerCase()] || status;
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.vehicles.index': '/admin/vehicles',
    'admin.vehicles.create': '/admin/vehicles/create',
    'admin.vehicles.show': (id: string | number) => `/admin/vehicles/${id}`,
    'admin.vehicles.edit': (id: string | number) => `/admin/vehicles/${id}/edit`,
    'admin.vehicles.destroy': (id: string | number) => `/admin/vehicles/${String(id)}`,
  };

  if (typeof routes[name] === 'function') {
    const result = routes[name](params);
    console.log('Route générée', { name, params, result });
    return result;
  }
  const result = routes[name] || '#';
  console.log('Route statique', { name, result });
  return result;
};

// Debug: vérifier les données reçues
onMounted(() => {
  console.log('Vehicles:', props.vehicles);
  console.log('Pagination:', props.pagination);
  console.log('Filters:', props.filters);
});

// Nettoyer lors du démontage
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

