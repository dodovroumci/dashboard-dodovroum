<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <div class="flex items-center gap-2 mb-1">
          <a href="/owner/vehicles" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1 transition-colors">
            <ChevronLeft class="w-4 h-4" />
            Mes véhicules
          </a>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Véhicules archivés</h1>
        <p class="text-sm text-slate-500 mt-0.5">Véhicules désactivés — réactivez-les pour les remettre en ligne</p>
      </div>
    </div>

    <!-- Flash messages -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ error }}
    </div>

    <!-- Table -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <!-- État vide -->
      <div v-if="vehicles.length === 0 && !error" class="p-12 text-center">
        <div class="mx-auto w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
          <Archive class="w-8 h-8 text-slate-400" />
        </div>
        <h3 class="text-lg font-medium text-slate-900 mb-2">Aucun véhicule archivé</h3>
        <p class="text-slate-500 mb-4">Vos véhicules désactivés apparaîtront ici.</p>
        <a
          href="/owner/vehicles"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition inline-block text-sm font-medium"
        >
          Voir mes véhicules actifs
        </a>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Véhicule</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Prix/Jour</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Statut</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr
            v-for="vehicle in vehicles"
            :key="vehicle.id"
            class="hover:bg-slate-50 transition-colors opacity-80"
          >
            <!-- Véhicule -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img
                    v-if="getImage(vehicle) && !imageErrors[vehicle.id]"
                    :src="getImage(vehicle)!"
                    :alt="vehicle.name || 'Véhicule'"
                    class="w-full h-full object-cover grayscale"
                    @error="() => imageErrors[vehicle.id] = true"
                  />
                  <component v-else :is="getIcon(vehicle.type)" class="w-6 h-6 text-slate-400" />
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

            <!-- Type -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center gap-2">
                <component :is="getIcon(vehicle.type)" class="w-4 h-4 text-slate-400" />
                <span class="text-sm text-slate-600">{{ formatType(vehicle.type) }}</span>
              </div>
            </td>

            <!-- Prix -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-baseline gap-1">
                <span class="text-sm font-medium text-slate-700">{{ formatPrice(vehicle.pricePerDay || vehicle.price || 0) }} CFA</span>
                <span class="text-xs text-slate-400">/jour</span>
              </div>
            </td>

            <!-- Badge Archivé -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">
                <Archive class="w-3 h-3" />
                Archivé
              </span>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 whitespace-nowrap text-right" @click.stop>
              <div class="flex items-center justify-end gap-2">
                <a
                  :href="`/owner/vehicles/${vehicle.id}`"
                  class="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                  title="Voir"
                >
                  <Eye class="w-4 h-4" />
                </a>
                <form :action="`/owner/vehicles/${vehicle.id}/reactivate`" method="POST">
                  <input type="hidden" name="_token" :value="csrfToken()" />
                  <input type="hidden" name="_method" value="PATCH" />
                  <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors"
                  >
                    <RotateCcw class="w-3.5 h-3.5" />
                    Réactiver
                  </button>
                </form>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <Pagination
        v-if="pagination && vehicles.length > 0"
        :pagination="pagination"
        route-name="owner.vehicles.archived"
        :filters="{}"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Car, Bike, Eye, Archive, RotateCcw, ChevronLeft } from 'lucide-vue-next';
import Pagination from '../../../Components/Pagination.vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

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
    price?: number;
    images?: string[];
    canEdit?: boolean;
  }>;
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  } | null;
  error?: string;
}>();

const page = usePage();
const csrfToken = () => (page.props as any).csrf_token as string;

const imageErrors = ref<Record<string | number, boolean>>({});

const getImage = (vehicle: any): string | null => {
  if (vehicle.images?.length > 0) return vehicle.images[0];
  return vehicle.imageUrl ?? vehicle.image ?? null;
};

const getIcon = (type?: string) => {
  if (!type) return Car;
  const t = type.toLowerCase();
  if (t === 'moto' || t === 'motorcycle' || t === 'scooter') return Bike;
  return Car;
};

const formatType = (type?: string): string => {
  if (!type) return 'N/A';
  const map: Record<string, string> = {
    berline: 'Berline', suv: 'SUV', '4x4': '4x4',
    utilitaire: 'Utilitaire', moto: 'Moto', motorcycle: 'Moto',
  };
  return map[type.toLowerCase()] ?? type;
};

const formatPrice = (price: number): string => new Intl.NumberFormat('fr-FR').format(price);
</script>
