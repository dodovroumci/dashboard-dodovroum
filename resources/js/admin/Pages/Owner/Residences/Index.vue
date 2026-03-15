<template>
  <div class="space-y-4 sm:space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Mes résidences</h1>
        <p class="text-sm text-slate-500 mt-0.5">Vos résidences et logements</p>
      </div>
      <Link
        :href="route('owner.residences.create')"
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
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ error }}
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-6">
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
        <p class="text-sm text-slate-500 mb-1">Résidences disponibles</p>
        <p class="text-2xl font-semibold text-green-600">{{ formatNumber(stats?.availableResidences || 0) }}</p>
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
        <select v-model="filters.type" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les types</option>
          <option value="villa">Villa</option>
          <option value="appartement">Appartement</option>
          <option value="maison">Maison</option>
          <option value="studio">Studio</option>
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
      <div v-if="residences.length === 0 && !error" class="p-12 text-center">
        <p class="text-slate-500">Aucune résidence trouvée</p>
      </div>
      <div v-if="residences.length === 0 && error" class="p-12 text-center">
        <p class="text-red-600 font-medium">{{ error }}</p>
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
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
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
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="position: relative;" @click.stop>
              <div class="flex items-center justify-end">
                <div class="relative inline-block text-left">
                  <button
                    :ref="el => setButtonRef(residence.id, el)"
                    @click.stop="toggleMenu(residence.id)"
                    class="p-1 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition"
                    :class="{ 'bg-slate-100 text-slate-900': openMenus.has(residence.id) }"
                  >
                    <MoreVertical class="w-5 h-5" />
                  </button>
                  <Teleport to="body">
                    <div
                      v-if="openMenus.has(residence.id)"
                      class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200 z-50"
                      :style="getMenuStyle(residence.id)"
                    >
                    <div class="py-1">
                      <Link
                        :href="route('owner.residences.show', residence.id)"
                        @click="closeMenu(residence.id)"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                      >
                        <Eye class="w-4 h-4" />
                        Voir
                      </Link>
                      <Link
                        v-if="residence.canEdit !== false"
                        :href="route('owner.residences.edit', residence.id)"
                        @click="closeMenu(residence.id)"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                      >
                        <Pencil class="w-4 h-4" />
                        Modifier
                      </Link>
                      <button
                        v-else
                        disabled
                        class="block w-full text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
                        title="Vous n'avez pas les droits pour modifier cette résidence"
                      >
                        <Pencil class="w-4 h-4" />
                        Modifier (non autorisé)
                      </button>
                      <button
                        v-if="residence.canEdit !== false"
                        type="button"
                        @click.stop.prevent="confirmDelete(residence)"
                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                      >
                        <Trash2 class="w-4 h-4" />
                        Supprimer
                      </button>
                      <button
                        v-else
                        disabled
                        class="w-full text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
                        title="Vous n'avez pas les droits pour supprimer cette résidence"
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
      
      <!-- Pagination -->
      <Pagination
        v-if="pagination"
        :pagination="pagination"
        route-name="owner.residences.index"
        :filters="filters"
      />
    </div>

    <!-- Modal de confirmation de suppression (Teleport pour être au-dessus du menu) -->
    <Teleport to="body">
      <div
        v-if="residenceToDelete"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100]"
        @click.self="residenceToDelete = null"
      >
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-xl" @click.stop>
          <h3 class="text-lg font-semibold mb-4">Confirmer la suppression</h3>
          <p class="text-slate-600 mb-6">
            Êtes-vous sûr de vouloir supprimer la résidence
            <strong>{{ residenceToDelete.title || residenceToDelete.name || 'cette résidence' }}</strong> ?
            Cette action est irréversible.
          </p>
          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="residenceToDelete = null"
              class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
            >
              Annuler
            </button>
            <button
              type="button"
              @click.stop="deleteResidence"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
            >
              Supprimer
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, onUnmounted, Teleport } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2, MoreVertical, Building2, Calendar, DollarSign, CheckCircle } from 'lucide-vue-next';
import Pagination from '../../../Components/Pagination.vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  residences: Array<{
    id: number | string;
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
  error?: string;
  stats?: {
    totalResidences: number;
    availableResidences: number;
    totalBookings: number;
    monthRevenue: number;
  };
}>();

const error = props.error || '';

const filters = reactive({
  search: props.filters?.search || '',
  type: props.filters?.type || '',
  status: props.filters?.status || '',
});

const applyFilters = () => {
  router.get(route('owner.residences.index'), { ...filters, page: 1 }, {
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

const openMenus = ref(new Set<string | number>());
const residenceToDelete = ref<typeof props.residences[0] | null>(null);
const buttonRefs = ref<Map<string | number, HTMLElement>>(new Map());

const goToResidence = (residence: (typeof props.residences)[0]) => {
  router.visit(`/owner/residences/${residence.id}`);
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

const confirmDelete = (residence: typeof props.residences[0]) => {
  residenceToDelete.value = residence;
  // Fermer le menu après un délai pour éviter que le même clic ne ferme le modal (overlay)
  setTimeout(() => closeMenu(residence.id), 300);
};

const deleteResidence = () => {
  if (!residenceToDelete.value?.id) return;
  const url = `/owner/residences/${residenceToDelete.value.id}`;
  router.delete(url, {
    preserveScroll: true,
    onSuccess: () => { residenceToDelete.value = null; },
    onError: () => { residenceToDelete.value = null; },
    onFinish: () => { residenceToDelete.value = null; },
  });
};

const formatType = (type?: string): string => {
  if (!type) return 'N/A';
  const types: Record<string, string> = {
    'villa': 'Villa',
    'appartement': 'Appartement',
    'apartment': 'Appartement',
    'maison': 'Maison',
    'house': 'Maison',
    'studio': 'Studio',
  };
  return types[type.toLowerCase()] || type;
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

// Gestion des images
const imageErrors = ref<Record<string | number, boolean>>({});

const getResidenceImage = (residence: any): string | null => {
  if (residence.images && Array.isArray(residence.images) && residence.images.length > 0) {
    return residence.images[0];
  }
  if (residence.imageUrl) {
    return residence.imageUrl;
  }
  if (residence.image) {
    return residence.image;
  }
  return null;
};

const handleImageError = (id: string | number) => {
  imageErrors.value[id] = true;
};

const getStatusClass = (status: string | boolean): string => {
  const statusStr = typeof status === 'boolean' ? (status ? 'available' : 'unavailable') : status;
  const statusLower = statusStr.toLowerCase();
  
  if (statusLower === 'available' || statusLower === 'disponible') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'occupied' || statusLower === 'occupé') {
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
  } else if (statusLower === 'occupied' || statusLower === 'occupé') {
    return 'Occupé';
  } else if (statusLower === 'maintenance') {
    return 'Maintenance';
  }
  return 'Inconnu';
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'owner.residences.index': '/owner/residences',
    'owner.residences.create': '/owner/residences/create',
    'owner.residences.show': (id: string | number) => `/owner/residences/${id}`,
    'owner.residences.edit': (id: string | number) => `/owner/residences/${id}/edit`,
    'owner.residences.destroy': (id: string | number) => `/owner/residences/${id}`,
  };
  
  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  
  return routes[name] || '#';
};
</script>

