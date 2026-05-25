<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <div class="flex items-center gap-2 mb-1">
          <a href="/owner/residences" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1 transition-colors">
            <ChevronLeft class="w-4 h-4" />
            Mes résidences
          </a>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Résidences archivées</h1>
        <p class="text-sm text-slate-500 mt-0.5">Résidences désactivées — réactivez-les pour les remettre en ligne</p>
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
      <div v-if="residences.length === 0 && !error" class="p-12 text-center">
        <div class="mx-auto w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
          <Archive class="w-8 h-8 text-slate-400" />
        </div>
        <h3 class="text-lg font-medium text-slate-900 mb-2">Aucune résidence archivée</h3>
        <p class="text-slate-500 mb-4">Vos résidences désactivées apparaîtront ici.</p>
        <a
          href="/owner/residences"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition inline-block text-sm font-medium"
        >
          Voir mes résidences actives
        </a>
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Résidence</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Localisation</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Prix/Nuit</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Statut</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr
            v-for="residence in residences"
            :key="residence.id"
            class="hover:bg-slate-50 transition-colors opacity-80"
          >
            <!-- Résidence -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-lg mr-3 flex-shrink-0 overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                  <img
                    v-if="getImage(residence) && !imageErrors[residence.id]"
                    :src="getImage(residence)!"
                    :alt="residence.title || residence.name || 'Résidence'"
                    class="w-full h-full object-cover grayscale"
                    @error="() => imageErrors[residence.id] = true"
                  />
                  <Building2 v-else class="w-6 h-6 text-slate-400" />
                </div>
                <div>
                  <div class="text-sm font-medium text-slate-900">
                    {{ residence.title || residence.name || 'Résidence sans nom' }}
                  </div>
                  <div class="text-sm text-slate-500">
                    {{ residence.bedrooms ?? 0 }} chambres • {{ residence.capacity ?? 0 }} personnes
                  </div>
                </div>
              </div>
            </td>

            <!-- Type -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="text-sm text-slate-600">{{ formatType(residence.type || residence.typeResidence) }}</span>
            </td>

            <!-- Localisation -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-slate-700">{{ residence.address || residence.city || '—' }}</div>
              <div v-if="residence.address && residence.city" class="text-sm text-slate-400">{{ residence.city }}</div>
            </td>

            <!-- Prix -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="text-sm font-medium text-slate-700">{{ formatPrice(residence.pricePerNight || residence.price || 0) }} CFA</span>
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
                  :href="`/owner/residences/${residence.id}`"
                  class="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                  title="Voir"
                >
                  <Eye class="w-4 h-4" />
                </a>
                <form :action="`/owner/residences/${residence.id}/reactivate`" method="POST">
                  <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''" />
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
        v-if="pagination && residences.length > 0"
        :pagination="pagination"
        route-name="owner.residences.archived"
        :filters="{}"
      />
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Building2, Eye, Archive, RotateCcw, ChevronLeft } from 'lucide-vue-next';
import Pagination from '../../../Components/Pagination.vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

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

const imageErrors = ref<Record<string | number, boolean>>({});

const getImage = (residence: any): string | null => {
  if (residence.images?.length > 0) return residence.images[0];
  return residence.imageUrl ?? residence.image ?? null;
};

const formatType = (type?: string): string => {
  if (!type) return 'N/A';
  const map: Record<string, string> = {
    villa: 'Villa', appartement: 'Appartement', apartment: 'Appartement',
    maison: 'Maison', house: 'Maison', studio: 'Studio',
  };
  return map[type.toLowerCase()] ?? type;
};

const formatPrice = (price: number): string => new Intl.NumberFormat('fr-FR').format(price);
</script>
