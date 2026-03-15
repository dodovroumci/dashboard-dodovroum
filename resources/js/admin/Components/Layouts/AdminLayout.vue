<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 flex min-w-0">
    <Sidebar :items="navItems" :open="sidebarOpen" @close="sidebarOpen = false" />
    <div class="flex-1 flex flex-col min-h-screen min-w-0 w-full">
      <Header :on-toggle-sidebar="() => (sidebarOpen = !sidebarOpen)" />
      <main class="flex-1 p-4 sm:p-6 space-y-4 sm:space-y-6 overflow-x-hidden">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import Sidebar from '../Sidebar.vue';
import Header from '../Header.vue';
import { usePage } from '@inertiajs/vue3';
import {
  Home,
  Building2,
  Truck,
  Link as LinkIcon,
  Users,
  Calendar,
  Settings,
  Wallet,
} from 'lucide-vue-next';

const sidebarOpen = ref(false);
const page = usePage();
const user = computed(() => page.props.auth?.user);

const navItems = computed(() => [
  { label: 'Tableau de bord', href: '/admin/dashboard', icon: Home },
  { label: 'Résidences', href: '/admin/residences', icon: Building2 },
  { label: 'Véhicules', href: '/admin/vehicles', icon: Truck },
  { label: 'Offres combinées', href: '/admin/combo-offers', icon: LinkIcon },
  { label: 'Utilisateurs', href: '/admin/users', icon: Users },
  { label: 'Réservations', href: '/admin/bookings', icon: Calendar },
  { label: 'Chiffre d\'affaires', href: '/admin/revenue', icon: Wallet },
  { label: 'Paramètres', href: '/admin/settings', icon: Settings },
]);
</script>
