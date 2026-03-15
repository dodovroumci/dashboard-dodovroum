<template>
  <!-- Overlay mobile (visible uniquement quand menu ouvert sur petit écran) -->
  <Teleport to="body">
    <div
      v-if="isOpen && isMobile"
      class="fixed inset-0 bg-black/50 z-40 lg:hidden transition-opacity"
      aria-hidden="true"
      @click="close"
    />
  </Teleport>

  <!-- Sidebar : drawer sur mobile, fixe sur desktop -->
  <aside
    class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-[#1a202c] text-white min-h-screen flex flex-col transform transition-transform duration-300 ease-out lg:transform-none lg:sticky lg:top-0"
    :class="isOpen && isMobile ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    role="navigation"
    aria-label="Navigation principale"
  >
    <div class="flex items-center justify-between px-4 py-4 border-b border-slate-700 lg:px-6 lg:py-5">
      <div class="min-w-0">
        <p class="text-lg font-semibold truncate">{{ sidebarTitle }}</p>
        <p class="text-xs text-slate-400 truncate">{{ sidebarSubtitle }}</p>
      </div>
      <button
        type="button"
        class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors touch-manipulation"
        aria-label="Fermer le menu"
        @click="close"
      >
        <X class="w-5 h-5" />
      </button>
    </div>
    <nav class="flex-1 p-4 space-y-1 overflow-y-auto overflow-x-hidden">
      <Link
        v-for="item in props.items"
        :key="item.href"
        :href="item.href"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors cursor-pointer min-h-[44px] touch-manipulation"
        :class="[
          isActive(item.href)
            ? 'bg-[#374151] text-white'
            : 'text-white hover:bg-slate-700/50',
        ]"
        @click="handleLinkClick"
      >
        <component :is="item.icon" class="w-5 h-5 flex-shrink-0" aria-hidden="true" />
        <span class="truncate">{{ item.label }}</span>
      </Link>
    </nav>
    <div class="p-4 border-t border-slate-700 bg-slate-800/50 shrink-0">
      <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center">
        <div class="w-4 h-4 bg-white rounded-sm" />
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import type { Component } from 'vue';

const props = withDefaults(
  defineProps<{
    items: { label: string; href: string; icon: Component }[];
    open?: boolean;
  }>(),
  { open: false }
);

const emit = defineEmits<{
  close: [];
}>();

// Interface pour les props Inertia partagées
interface InertiaSharedProps extends Record<string, unknown> {
  auth?: {
    user?: {
      id?: string | number;
      name?: string;
      email?: string;
      role?: string;
    } | null;
  };
}

const page = usePage<InertiaSharedProps>();
const currentPath = computed(() => page.url);
const user = computed(() => (page.props as InertiaSharedProps).auth?.user);

const isOpen = computed(() => props.open);

const isMobile = ref(false);
function updateMobile() {
  isMobile.value = typeof window !== 'undefined' && window.innerWidth < 1024;
}

function close() {
  emit('close');
}

function handleLinkClick() {
  if (isMobile.value) close();
}

let mq: MediaQueryList | null = null;
function onMediaChange() {
  updateMobile();
  if (mq?.matches) close();
}

onMounted(() => {
  updateMobile();
  window.addEventListener('resize', updateMobile);
  mq = window.matchMedia('(min-width: 1024px)');
  mq.addEventListener('change', onMediaChange);
});

onUnmounted(() => {
  window.removeEventListener('resize', updateMobile);
  mq?.removeEventListener('change', onMediaChange);
});

const sidebarTitle = computed(() => {
  const r = (user.value?.role ?? '').toString().toLowerCase();
  if (r === 'admin' || r === 'administrator') return 'DodoVroum Admin';
  return 'Espace Propriétaire';
});

const sidebarSubtitle = computed(() => {
  const r = (user.value?.role ?? '').toString().toLowerCase();
  if (r === 'admin' || r === 'administrator') return 'Gestion centralisée';
  return 'Gestion de mes biens';
});

const isActive = (path: string): boolean => {
  if (path === '/admin/dashboard' || path === '/owner/dashboard') {
    return currentPath.value === path;
  }
  return currentPath.value.startsWith(path);
};
</script>
