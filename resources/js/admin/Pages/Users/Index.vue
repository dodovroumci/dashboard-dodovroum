<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Utilisateurs</h1>
        <p class="text-sm text-slate-500 mt-1 truncate">Gérer les utilisateurs de la plateforme</p>
      </div>
      <Link
        :href="route('admin.users.create')"
        class="flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 min-h-[44px] bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm whitespace-nowrap w-full sm:w-auto shrink-0"
      >
        <span class="sm:hidden">Créer</span>
        <span class="hidden sm:inline">Créer un propriétaire</span>
      </Link>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- Filtres -->
    <form @submit.prevent="applyFilters" class="bg-white border border-slate-200 rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Rechercher par nom, email..."
          class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select v-model="filters.role" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          <option value="">Tous les rôles</option>
          <option value="admin">Admin</option>
          <option value="proprietaire">Propriétaire</option>
          <option value="client">Client</option>
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

    <!-- Tableau des utilisateurs -->
    <div class="bg-white border border-slate-200 rounded-xl" style="overflow-x: auto; overflow-y: visible;">
      <div v-if="users.length === 0" class="p-12 text-center">
        <p class="text-slate-500">Aucun utilisateur trouvé</p>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Utilisateur
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Email
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Téléphone
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
              Rôle
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
          <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                  <span class="text-blue-600 font-medium text-sm">
                    {{ user.name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-slate-900">{{ user.name }}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">{{ user.email }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-900">{{ user.phone || 'N/A' }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getRoleClass(user.role)"
              >
                {{ formatRole(user.role) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="getStatusClass(user.isActive, user.isVerified)"
              >
                {{ getStatusLabel(user.isActive, user.isVerified) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="position: relative; overflow: visible !important;">
              <div class="relative inline-block text-left">
                <button
                  :ref="el => setButtonRef(user.id, el)"
                  @click="toggleActionMenu(user.id)"
                  class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                  </svg>
                </button>
                <Teleport to="body">
                  <div
                    v-if="activeMenuId === user.id"
                    class="fixed w-48 bg-white rounded-lg shadow-xl border border-slate-200"
                    :style="getMenuStyle(user.id)"
                    @click.stop
                    style="z-index: 999999 !important;"
                  >
                    <div class="py-1">
                      <Link
                        :href="route('admin.users.show', user.id)"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                        @click="closeActionMenu"
                      >
                        Voir les détails
                      </Link>
                      <div class="border-t border-slate-200 my-1"></div>
                      <Link
                        :href="route('admin.users.edit', user.id)"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors"
                        @click="closeActionMenu"
                      >
                        Modifier
                      </Link>
                      <div class="border-t border-slate-200 my-1"></div>
                      <button
                        @click="confirmDelete(user.id)"
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
    </div>

    <!-- Pagination -->
    <Pagination
      v-if="pagination && pagination.total > 0"
      :pagination="pagination"
      route-name="admin.users.index"
      :filters="filters"
    />
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue';
import { Teleport } from 'vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps<{
  users: Array<{
    id: string;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    isVerified: boolean;
    isActive: boolean;
    createdAt: string | null;
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
    role: string;
  };
}>();

const activeMenuId = ref<string | null>(null);
const buttonRefs = ref<Record<string, HTMLElement | null>>({});
const menuPositions = ref<Record<string, { top: number; right: number }>>({});

const filters = reactive({
  search: props.filters.search || '',
  role: props.filters.role || '',
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
  if (!target.closest('.relative') && !target.closest('.fixed')) {
    closeActionMenu();
  }
};

// Mettre à jour la position lors du scroll
const handleScroll = () => {
  if (activeMenuId.value) {
    updateMenuPosition(activeMenuId.value);
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('scroll', handleScroll, true);
  window.addEventListener('resize', handleScroll);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('scroll', handleScroll, true);
  window.removeEventListener('resize', handleScroll);
});

const applyFilters = () => {
  router.get(route('admin.users.index'), filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  filters.search = '';
  filters.role = '';
  applyFilters();
};

const formatRole = (role: string): string => {
  const roleMap: Record<string, string> = {
    admin: 'Admin',
    proprietaire: 'Propriétaire',
    owner: 'Propriétaire',
    user: 'Client',
  };
  return roleMap[role.toLowerCase()] || role;
};

const getRoleClass = (role: string): string => {
  const roleLower = role.toLowerCase();
  if (roleLower === 'admin') {
    return 'bg-purple-100 text-purple-800';
  } else if (roleLower === 'proprietaire' || roleLower === 'owner') {
    return 'bg-blue-100 text-blue-800';
  }
  return 'bg-slate-100 text-slate-800';
};

const getStatusClass = (isActive: boolean, isVerified: boolean): string => {
  if (!isActive) {
    return 'bg-red-100 text-red-800';
  }
  if (isVerified) {
    return 'bg-emerald-100 text-emerald-800';
  }
  return 'bg-yellow-100 text-yellow-800';
};

const getStatusLabel = (isActive: boolean, isVerified: boolean): string => {
  if (!isActive) {
    return 'Inactif';
  }
  if (isVerified) {
    return 'Vérifié';
  }
  return 'Non vérifié';
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.users.index': '/admin/users',
    'admin.users.create': '/admin/users/create',
    'admin.users.show': (id: string) => `/admin/users/${id}`,
    'admin.users.edit': (id: string) => `/admin/users/${id}/edit`,
    'admin.users.destroy': (id: string) => `/admin/users/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};

const confirmDelete = (id: string) => {
  if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
    router.delete(route('admin.users.destroy', id), {
      onSuccess: () => {
        // Redirection gérée par le contrôleur
      },
    });
  }
  // Fermer le menu après confirmation
  activeMenuId.value = null;
};
</script>

