<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Mes véhicules</h1>
        <p class="text-sm text-slate-500 mt-1 truncate">Votre flotte de véhicules</p>
      </div>
      <Link
        :href="route('owner.vehicles.create')"
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
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ error }}
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
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
        />
        <select v-model="filters.type" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 transition">
          <option value="">Tous les types</option>
          <option value="berline">Berline</option>
          <option value="suv">SUV</option>
          <option value="4x4">4x4</option>
          <option value="utilitaire">Utilitaire</option>
          <option value="moto">Moto</option>
        </select>
        <div class="flex gap-2">
          <button
            type="submit"
            :disabled="isFiltering"
            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center gap-2"
          >
            <span v-if="isFiltering" class="animate-spin">⟳</span>
            Filtrer
          </button>
          <button
            type="button"
            @click="resetFilters"
            class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition"
          >
            Réinitialiser
          </button>
        </div>
      </div>
    </form>

    <!-- Tableau des véhicules -->
    <div class="bg-white border border-slate-200 rounded-xl table-scroll-wrap">
      <!-- État vide amélioré -->
      <div v-if="vehicles.length === 0" class="p-8 sm:p-12 text-center">
        <div class="mx-auto w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
          <Truck class="w-8 h-8 text-slate-400" />
        </div>
        <h3 class="text-lg font-medium text-slate-900 mb-2">Aucun véhicule trouvé</h3>
        <p class="text-slate-500 mb-4">
          {{ filters.search || filters.type ? 'Aucun véhicule ne correspond à vos critères de recherche.' : 'Ajoutez votre premier véhicule pour commencer.' }}
        </p>
        <Link
          v-if="!filters.search && !filters.type"
          :href="route('owner.vehicles.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition inline-block"
        >
          Ajouter un véhicule
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
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr 
            v-for="vehicle in vehicles" 
            :key="vehicle.id" 
            class="hover:bg-slate-50 transition-colors duration-150 cursor-pointer"
            @click="goToVehicle(vehicle.id)"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <!-- Image ou icône du véhicule -->
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img 
                    v-if="getVehicleImage(vehicle) && !imageErrors[vehicle.id]"
                    :src="getStorageImageUrl(getVehicleImage(vehicle), 'vehicles')"
                    :alt="vehicle.name || vehicle.titre || 'Véhicule'"
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
                    {{ vehicle.name || `${vehicle.brand || ''} ${vehicle.model || ''}`.trim() || 'Véhicule sans nom' }}
                  </div>
                  <div class="text-sm text-slate-500">
                    {{ vehicle.year || 'N/A' }} • {{ vehicle.seats || 0 }} places
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center gap-2">
                <component :is="getVehicleIcon(vehicle.type)" class="w-4 h-4 text-slate-400" />
                <span class="text-sm text-slate-900">
                  {{ formatType(vehicle.type) }}
                </span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-baseline gap-1">
                <span class="text-sm font-medium text-slate-900">
                  {{ formatPrice(vehicle.pricePerDay || vehicle.price_per_day || vehicle.price || 0) }} CFA
                </span>
                <span class="text-xs text-slate-500">/jour</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getStatusClass(vehicle.available ?? vehicle.status ?? 'available')"
              >
                {{ getStatusLabel(vehicle.available ?? vehicle.status ?? 'available') }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right">
              <div class="flex items-center justify-end gap-2" @click.stop>
                <!-- Menu d'actions -->
                <div class="relative">
                  <button
                    @click="toggleMenu(vehicle.id)"
                    class="p-1 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition"
                    :class="{ 'bg-slate-100 text-slate-900': openMenus.has(vehicle.id) }"
                  >
                    <MoreVertical class="w-5 h-5" />
                  </button>
                  
                  <!-- Dropdown menu -->
                  <div
                    v-if="openMenus.has(vehicle.id)"
                    class="absolute right-0 mt-1 w-40 bg-white border border-slate-200 rounded-lg shadow-lg z-10 py-1"
                  >
                    <Link
                      :href="`/owner/vehicles/${vehicle.id}`"
                      @click="closeMenu(vehicle.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                    >
                      <Eye class="w-4 h-4" />
                      Voir
                    </Link>
                    <Link
                      v-if="vehicle.canEdit !== false"
                      :href="`/owner/vehicles/${vehicle.id}/edit`"
                      @click="closeMenu(vehicle.id)"
                      class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                    >
                      <Pencil class="w-4 h-4" />
                      Modifier
                    </Link>
                    <button
                      v-else
                      disabled
                      class="block w-full text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
                      title="Vous n'avez pas les droits pour modifier ce véhicule"
                    >
                      <Pencil class="w-4 h-4" />
                      Modifier (non autorisé)
                    </button>
                    <button
                      v-if="vehicle.canEdit !== false"
                      @click="confirmDelete(vehicle); closeMenu(vehicle.id)"
                      class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                    >
                      <Trash2 class="w-4 h-4" />
                      Supprimer
                    </button>
                    <button
                      v-else
                      disabled
                      class="w-full text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
                      title="Vous n'avez pas les droits pour supprimer ce véhicule"
                    >
                      <Trash2 class="w-4 h-4" />
                      Supprimer (non autorisé)
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Pagination -->
      <Pagination
        v-if="pagination && vehicles.length > 0"
        :pagination="pagination"
        route-name="owner.vehicles.index"
        :filters="filters"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Pagination from '../../../Components/Pagination.vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';
import { Truck, Car, Bike, MoreVertical, Eye, Pencil, Trash2, Calendar, DollarSign, CheckCircle } from 'lucide-vue-next';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  vehicles: Array<{
    id: number | string;
    name?: string;
    brand?: string;
    model?: string;
    year?: number;
    type?: string;
    seats?: number;
    pricePerDay?: number;
    price_per_day?: number;
    price?: number;
    available?: boolean;
    status?: string;
    images?: string[];
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
  error?: string;
  stats?: {
    totalVehicles: number;
    availableVehicles: number;
    totalBookings: number;
    monthRevenue: number;
  };
}>();

const error = props.error || '';
const isFiltering = ref(false);
const openMenus = ref(new Set<string | number>());

const filters = reactive({
  search: props.filters?.search || '',
  type: props.filters?.type || '',
  status: props.filters?.status || '',
});

const applyFilters = () => {
  isFiltering.value = true;
  router.get('/owner/vehicles', { ...filters, page: 1 }, {
    preserveState: false,
    preserveScroll: false,
    onFinish: () => {
      isFiltering.value = false;
    },
  });
};

const resetFilters = () => {
  filters.search = '';
  filters.type = '';
  filters.status = '';
  applyFilters();
};

const goToVehicle = (vehicleId: string | number) => {
  router.visit(`/owner/vehicles/${vehicleId}`);
};

const toggleMenu = (vehicleId: string | number) => {
  if (openMenus.value.has(vehicleId)) {
    openMenus.value.delete(vehicleId);
  } else {
    openMenus.value.clear();
    openMenus.value.add(vehicleId);
  }
};

const closeMenu = (vehicleId: string | number) => {
  openMenus.value.delete(vehicleId);
};

const confirmDelete = (vehicle: { id: string | number; name?: string }) => {
  if (confirm(`Êtes-vous sûr de vouloir supprimer "${vehicle.name || 'ce véhicule'}" ? Cette action est irréversible.`)) {
    router.delete(`/owner/vehicles/${vehicle.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        // Le message de succès sera affiché via flash
      },
      onError: () => {
        // Le message d'erreur sera affiché via flash
      },
    });
  }
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

const imageErrors = ref<Record<string | number, boolean>>({});

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

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

const getVehicleIcon = (type?: string) => {
  if (!type) return Car;
  
  const typeLower = type.toLowerCase();
  if (typeLower === 'moto' || typeLower === 'motorcycle' || typeLower === 'scooter') {
    return Bike;
  }
  return Car;
};

const formatType = (type?: string): string => {
  if (!type) return 'N/A';
  const types: Record<string, string> = {
    'berline': 'Berline',
    'suv': 'SUV',
    '4x4': '4x4',
    'utilitaire': 'Utilitaire',
    'moto': 'Moto',
    'motorcycle': 'Moto',
    'scooter': 'Scooter',
    'sedan': 'Berline',
  };
  return types[type.toLowerCase()] || type;
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const getStatusClass = (status: string | boolean): string => {
  const statusStr = typeof status === 'boolean' ? (status ? 'available' : 'unavailable') : status;
  const statusLower = statusStr.toLowerCase();
  
  if (statusLower === 'available' || statusLower === 'disponible') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'rented' || statusLower === 'en_location') {
    return 'bg-amber-100 text-amber-700';
  } else if (statusLower === 'maintenance') {
    return 'bg-red-100 text-red-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getStatusLabel = (status: string | boolean): string => {
  const statusStr = typeof status === 'boolean' ? (status ? 'available' : 'unavailable') : status;
  const statusLower = statusStr.toLowerCase();
  
  if (statusLower === 'available' || statusLower === 'disponible') {
    return 'Disponible';
  } else if (statusLower === 'rented' || statusLower === 'en_location') {
    return 'En location';
  } else if (statusLower === 'maintenance') {
    return 'Maintenance';
  }
  return 'Inconnu';
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'owner.vehicles.index': '/owner/vehicles',
    'owner.vehicles.create': '/owner/vehicles/create',
    'owner.vehicles.show': (id: string | number) => `/owner/vehicles/${id}`,
    'owner.vehicles.edit': (id: string | number) => `/owner/vehicles/${id}/edit`,
  };
  
  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  
  return routes[name] || '#';
};
</script>
