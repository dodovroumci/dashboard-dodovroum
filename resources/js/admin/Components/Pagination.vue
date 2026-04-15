<template>
  <div v-if="pagination" class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6">
    <div class="flex flex-1 justify-between sm:hidden">
      <Link
        v-if="pagination.current_page > 1"
        :href="getPageUrl(pagination.current_page - 1)"
        class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
      >
        Précédent
      </Link>
      <span v-else class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-300 cursor-not-allowed">
        Précédent
      </span>
      <Link
        v-if="pagination.current_page < pagination.last_page"
        :href="getPageUrl(pagination.current_page + 1)"
        class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
      >
        Suivant
      </Link>
      <span v-else class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-300 cursor-not-allowed">
        Suivant
      </span>
    </div>
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-slate-700">
          <span v-if="pagination.last_page > 1">
            Affichage de
            <span class="font-medium">{{ pagination.from || 0 }}</span>
            à
            <span class="font-medium">{{ pagination.to || 0 }}</span>
            sur
            <span class="font-medium">{{ pagination.total || 0 }}</span>
            résultats
          </span>
          <span v-else>
            <span class="font-medium">{{ pagination.total || 0 }}</span>
            résultat{{ pagination.total > 1 ? 's' : '' }}
          </span>
        </p>
      </div>
      <div v-if="pagination.last_page > 1">
        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
          <Link
            v-if="pagination.current_page > 1"
            :href="getPageUrl(pagination.current_page - 1)"
            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0"
          >
            <span class="sr-only">Précédent</span>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
            </svg>
          </Link>
          <span v-else class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-300 ring-1 ring-inset ring-slate-300 cursor-not-allowed">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
            </svg>
          </span>
          
          <!-- Pages -->
          <template v-for="page in getPageNumbers()" :key="`page-${page}`">
            <Link
              v-if="page !== '...'"
              :href="getPageUrl(page as number)"
              class="relative inline-flex items-center px-4 py-2 text-sm font-semibold min-w-[2.5rem] justify-center border border-slate-300"
              :class="page === pagination.current_page
                ? 'z-10 bg-blue-600 text-white border-blue-600 focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                : 'text-slate-900 bg-white hover:bg-slate-50 focus:z-20 focus:outline-offset-0'"
            >
              {{ page }}
            </Link>
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-500 bg-white border border-slate-300 focus:outline-offset-0 min-w-[2.5rem] justify-center"
            >
              ...
            </span>
          </template>
          
          <Link
            v-if="pagination.current_page < pagination.last_page"
            :href="getPageUrl(pagination.current_page + 1)"
            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0"
          >
            <span class="sr-only">Suivant</span>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
            </svg>
          </Link>
          <span v-else class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-300 ring-1 ring-inset ring-slate-300 cursor-not-allowed">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
            </svg>
          </span>
        </nav>
      </div>
      <div v-else class="text-sm text-slate-500">
        Page 1 sur 1
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  } | null;
  routeName: string;
  filters?: Record<string, any>;
}>();

const getPageUrl = (page: number): string => {
  const params = new URLSearchParams();
  
  if (props.filters) {
    Object.entries(props.filters).forEach(([key, value]) => {
      if (key === 'page') {
        return;
      }
      if (value !== null && value !== undefined && value !== '') {
        params.set(key, String(value));
      }
    });
  }

  // Toujours fixer la page en dernier pour eviter
  // qu'un filtre `page=1` n'ecrase la navigation.
  params.set('page', page.toString());
  
  const route = (name: string): string => {
    const routes: Record<string, string> = {
      'admin.vehicles.index': '/admin/vehicles',
      'admin.residences.index': '/admin/residences',
      'admin.combo-offers.index': '/admin/combo-offers',
      'admin.bookings.index': '/admin/bookings',
      'admin.users.index': '/admin/users',
      'owner.vehicles.index': '/owner/vehicles',
      'owner.residences.index': '/owner/residences',
      'owner.bookings.index': '/owner/bookings',
      'owner.combo-offers.index': '/owner/combo-offers',
    };
    return routes[name] || '#';
  };
  
  return `${route(props.routeName)}?${params.toString()}`;
};

const getPageNumbers = (): (number | string)[] => {
  if (!props.pagination) return [];
  
  const current = props.pagination.current_page;
  const last = props.pagination.last_page;
  const pages: (number | string)[] = [];
  
  if (last <= 7) {
    // Afficher toutes les pages si 7 ou moins
    for (let i = 1; i <= last; i++) {
      pages.push(i);
    }
  } else {
    // Logique pour afficher les pages avec ellipses
    if (current <= 3) {
      // Début
      for (let i = 1; i <= 4; i++) {
        pages.push(i);
      }
      pages.push('...');
      pages.push(last);
    } else if (current >= last - 2) {
      // Fin
      pages.push(1);
      pages.push('...');
      for (let i = last - 3; i <= last; i++) {
        pages.push(i);
      }
    } else {
      // Milieu
      pages.push(1);
      pages.push('...');
      for (let i = current - 1; i <= current + 1; i++) {
        pages.push(i);
      }
      pages.push('...');
      pages.push(last);
    }
  }
  
  return pages;
};

</script>

