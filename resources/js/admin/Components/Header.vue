<template>
  <header class="bg-white border-b border-slate-200 shrink-0">
    <div class="px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-3 min-h-[56px] sm:min-h-0">
      <div class="flex items-center gap-3 min-w-0 flex-1">
        <button
          v-if="typeof onToggleSidebar === 'function'"
          type="button"
          class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors touch-manipulation shrink-0"
          aria-label="Ouvrir le menu"
          @click="onToggleSidebar"
        >
          <Menu class="w-6 h-6" />
        </button>
        <div class="min-w-0">
          <h1 class="text-base sm:text-xl font-semibold text-slate-900 truncate">{{ headerTitle }}</h1>
          <p class="text-xs sm:text-sm text-slate-500 truncate hidden sm:block">{{ headerSubtitle }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2 sm:gap-4 shrink-0">
        <div class="relative" ref="menuContainer">
          <button
            type="button"
            @click.stop="toggleUserMenu"
            class="flex items-center gap-2 cursor-pointer hover:opacity-80 transition-opacity min-h-[44px] min-w-[44px] justify-end touch-manipulation rounded-lg p-1 -m-1"
            aria-haspopup="true"
            :aria-expanded="isUserMenuOpen"
          >
            <span class="text-right hidden sm:block max-w-[120px]">
              <span class="block text-sm font-medium text-slate-900 truncate">{{ userDisplayName }}</span>
              <span class="block text-xs text-slate-500 truncate">{{ userEmail }}</span>
            </span>
            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-brand-light flex items-center justify-center text-white font-semibold text-sm shrink-0">
              DV
            </div>
          </button>

          <div
            v-if="isUserMenuOpen"
            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-50"
            role="menu"
            @click.stop
          >
            <Link
              href="/profile"
              class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 min-h-[44px] flex items-center"
              @click="closeUserMenu"
            >
              Mon profil
            </Link>
            <a
              href="#"
              class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 min-h-[44px] flex items-center"
              @click.prevent="closeUserMenu"
            >
              Paramètres
            </a>
            <div class="border-t border-slate-200 my-1" />
            <a
              href="#"
              class="block px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 min-h-[44px] flex items-center"
              @click.prevent="handleLogout"
            >
              Déconnexion
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';

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
