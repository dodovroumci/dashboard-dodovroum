<template>
  <header class="bg-white border-b border-slate-200/80 shrink-0 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
    <div class="px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-h-[56px] sm:min-h-0">

      <!-- Gauche : toggle + logo + titre -->
      <div class="flex items-center gap-3 min-w-0 flex-1">
        <button
          v-if="typeof onToggleSidebar === 'function'"
          type="button"
          class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors duration-150 touch-manipulation shrink-0"
          aria-label="Ouvrir le menu"
          @click="onToggleSidebar"
        >
          <Menu class="w-5 h-5" />
        </button>

        <!-- Logo (visible uniquement sur desktop, caché quand sidebar présente) -->
        <img
          :src="logoUrl"
          alt="DodoVroum"
          class="hidden lg:block h-8 w-auto flex-shrink-0"
        />

        <!-- Séparateur vertical -->
        <div class="hidden lg:block w-px h-5 bg-slate-200 flex-shrink-0" />

        <div class="min-w-0">
          <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">{{ headerTitle }}</h1>
          <p class="text-xs text-slate-400 truncate hidden sm:block">{{ headerSubtitle }}</p>
        </div>
      </div>

      <!-- Droite : profil utilisateur -->
      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <div class="relative" ref="menuContainer">
          <button
            type="button"
            @click.stop="toggleUserMenu"
            class="flex items-center gap-2.5 cursor-pointer min-h-[44px] min-w-[44px] justify-end touch-manipulation rounded-xl px-2 py-1.5 hover:bg-slate-50 transition-colors duration-150"
            aria-haspopup="true"
            :aria-expanded="isUserMenuOpen"
          >
            <span class="text-right hidden sm:block max-w-[130px]">
              <span class="block text-sm font-semibold text-slate-800 truncate leading-tight">{{ userDisplayName }}</span>
              <span class="block text-xs text-slate-400 truncate leading-tight mt-0.5">{{ userEmail }}</span>
            </span>
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand to-brand-dark flex items-center justify-center text-white font-bold text-sm shrink-0 ring-2 ring-white shadow-sm">
              {{ userDisplayName.charAt(0).toUpperCase() }}
            </div>
          </button>

          <!-- Dropdown -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
          >
            <div
              v-if="isUserMenuOpen"
              class="absolute right-0 mt-1.5 w-52 bg-white rounded-xl shadow-lg shadow-slate-200/80 border border-slate-100 py-1.5 z-50 origin-top-right"
              role="menu"
              @click.stop
            >
              <!-- Info user -->
              <div class="px-4 py-2.5 border-b border-slate-100 mb-1">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ userDisplayName }}</p>
                <p class="text-xs text-slate-400 truncate mt-0.5">{{ userEmail }}</p>
              </div>
              <Link
                href="/profile"
                class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors duration-100 min-h-[36px]"
                @click="closeUserMenu"
              >
                Mon profil
              </Link>
              <div class="border-t border-slate-100 mt-1 pt-1" />
              <button
                type="button"
                class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-500 hover:bg-red-50 hover:text-red-600 transition-colors duration-100 min-h-[36px]"
                @click="handleLogout"
              >
                Déconnexion
              </button>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import logoUrl from '../assets/logo.png';

defineProps<{
  onToggleSidebar?: () => void;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const isAdmin = computed(() => (user.value?.role ?? '').toString().toLowerCase() === 'admin' || (user.value?.role ?? '').toString().toLowerCase() === 'administrator');
const isOwner = computed(() => (user.value?.role ?? '').toString().toLowerCase() === 'owner');

const headerTitle = computed(() => {
  if (isAdmin.value) return 'Administration';
  return 'Mon espace';
});

const headerSubtitle = computed(() => {
  if (isAdmin.value) return 'Vue d\'ensemble des opérations';
  return 'Gestion de mes biens et réservations';
});

const userDisplayName = computed(() => {
  if (isAdmin.value) return 'Admin';
  return user.value?.name || 'Propriétaire';
});

const userEmail = computed(() => user.value?.email ?? '');

const isUserMenuOpen = ref(false);
const menuContainer = ref<HTMLElement | null>(null);

const toggleUserMenu = () => {
  isUserMenuOpen.value = !isUserMenuOpen.value;
};

const closeUserMenu = () => {
  isUserMenuOpen.value = false;
};

const handleLogout = () => {
  closeUserMenu();
  router.post('/logout');
};

function handleClickOutside(event: MouseEvent) {
  if (!isUserMenuOpen.value) return;
  const target = event.target as HTMLElement;
  if (menuContainer.value && !menuContainer.value.contains(target)) {
    closeUserMenu();
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside, true);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside, true);
});
</script>
