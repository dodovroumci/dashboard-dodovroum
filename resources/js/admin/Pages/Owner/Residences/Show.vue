<template>
  <div class="space-y-6">
    <!-- Lien de retour -->
    <Link
      href="/owner/residences"
      class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors min-w-0"
    >
      <ArrowLeft class="w-4 h-4 shrink-0" />
      <span class="truncate">Retour à la liste des résidences</span>
    </Link>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- 1. En-tête de la résidence -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between min-w-0">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
            <h1 class="text-xl sm:text-3xl font-bold text-slate-900 truncate min-w-0">
              {{ residence?.title || residence?.name || 'Résidence sans nom' }}
            </h1>
            <span
              class="px-3 py-1 text-sm font-medium rounded-full shrink-0"
              :class="getStatusClass(residence?.status || residence?.isActive)"
            >
              {{ getStatusLabel(residence?.status || residence?.isActive) }}
            </span>
          </div>
          <div class="flex items-center gap-2 text-slate-600 min-w-0">
            <MapPin class="w-4 h-4 shrink-0" />
            <span class="truncate">{{ getLocation() }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2 relative shrink-0 flex-wrap">
          <Link
            :href="`/owner/residences/${residence?.id}/edit`"
            class="px-4 py-2.5 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 min-h-[44px] text-sm font-medium"
          >
            <Pencil class="w-4 h-4 shrink-0" />
            Modifier
          </Link>
          <button
            type="button"
            @click="toggleActionsMenu"
            class="min-h-[44px] min-w-[44px] p-2 border border-slate-300 rounded-lg hover:bg-slate-50 flex items-center justify-center touch-manipulation relative"
            aria-label="Actions"
          >
            <MoreVertical class="w-5 h-5 text-slate-600" />
            <div
              v-if="showActionsMenu"
              class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-xl border border-slate-200 z-50 py-1"
            >
              <a
                :href="`/residences/${residence?.id}`"
                target="_blank"
                class="block px-4 py-2.5 min-h-[44px] text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
              >
                <Eye class="w-4 h-4 shrink-0" />
                Voir côté client
              </a>
              <button
                @click="toggleDisable"
                class="w-full text-left px-4 py-2.5 min-h-[44px] text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2 touch-manipulation"
              >
                <Power class="w-4 h-4 shrink-0" />
                {{ residence?.isActive ? 'Désactiver' : 'Activer' }}
              </button>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- 2. Galerie (aperçu) -->
    <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4 min-w-0">
        <h2 class="text-base sm:text-lg font-semibold text-slate-900 truncate">Galerie</h2>
        <Link
          :href="`/owner/residences/${residence?.id}/edit`"
          class="text-sm text-blue-600 hover:text-blue-700 font-medium"
        >
          Modifier les photos →
        </Link>
      </div>
      <div v-if="residence?.images && residence.images.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div
          v-for="(image, index) in residence.images.slice(0, 5)"
          :key="index"
          role="button"
          tabindex="0"
          class="aspect-video bg-slate-200 rounded-lg overflow-hidden cursor-pointer relative group touch-manipulation"
          @click="openImageModal(getStorageImageUrl(image, 'residences'))"
          @keydown.enter.prevent="openImageModal(getStorageImageUrl(image, 'residences'))"
          @keydown.space.prevent="openImageModal(getStorageImageUrl(image, 'residences'))"
        >
          <img
            v-if="!imageErrors[index]"
            :src="getStorageImageUrl(image, 'residences')"
            :alt="`Image ${index + 1}`"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform"
            @error="() => handleImageError(index)"
          />
          <div v-else class="w-full h-full flex items-center justify-center">
            <ImageIcon class="w-8 h-8 text-slate-400" />
          </div>
          <div v-if="index === 0" class="absolute top-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
            Principale
          </div>
        </div>
      </div>
      <div v-else class="aspect-video bg-slate-200 rounded-lg flex items-center justify-center">
        <div class="text-center">
          <ImageIcon class="w-12 h-12 text-slate-400 mx-auto mb-2" />
          <p class="text-slate-500">Aucune image disponible</p>
          <Link
            :href="`/owner/residences/${residence?.id}/edit`"
            class="text-sm text-blue-600 hover:text-blue-700 font-medium mt-2 inline-block"
          >
            Ajouter des photos →
          </Link>
        </div>
      </div>
    </section>

    <!-- 3. Résumé rapide (KPIs) -->
    <section class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Calendar class="w-5 h-5 text-slate-400" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Réservations totales</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.totalBookings || 0) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <DollarSign class="w-5 h-5 text-slate-400" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Revenus générés</p>
        <p class="text-2xl font-semibold text-emerald-600">{{ formatPrice(stats?.totalRevenue || 0) }} CFA</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Star class="w-5 h-5 text-amber-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Note moyenne</p>
        <p class="text-2xl font-semibold text-slate-900">
          ⭐ {{ stats?.averageRating || 0 }}
          <span class="text-sm text-slate-500 font-normal">({{ stats?.totalReviews || 0 }} avis)</span>
        </p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <TrendingUp class="w-5 h-5 text-slate-400" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Taux d'occupation</p>
        <p class="text-2xl font-semibold text-blue-600">{{ stats?.occupationRate || 0 }}%</p>
      </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Colonne principale -->
      <div class="lg:col-span-2 space-y-6">
        <!-- 4. Informations de la résidence -->
        <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4 min-w-0">
            <h2 class="text-base sm:text-lg font-semibold text-slate-900 truncate">Informations de la résidence</h2>
            <Link
              :href="`/owner/residences/${residence?.id}/edit`"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium shrink-0"
            >
              Modifier les informations →
            </Link>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Type de résidence</p>
              <p class="font-medium text-slate-900 break-words">{{ formatType(residence?.type || residence?.typeResidence) }}</p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Prix / nuit</p>
              <p class="font-medium text-slate-900 break-words">
                {{ formatPrice(residence?.pricePerNight || residence?.price || 0) }} CFA
              </p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Capacité</p>
              <p class="font-medium text-slate-900 break-words">
                {{ residence?.capacity || residence?.capacite || 0 }} personnes
              </p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Nombre de chambres</p>
              <p class="font-medium text-slate-900 break-words">
                {{ residence?.bedrooms || residence?.nombreChambres || 0 }}
              </p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Salles de bain</p>
              <p class="font-medium text-slate-900 break-words">
                {{ residence?.bathrooms || residence?.nombreSallesBain || 0 }}
              </p>
            </div>
            <div v-if="residence?.amenities && residence.amenities.length > 0" class="min-w-0 sm:col-span-2">
              <p class="text-sm text-slate-500 mb-1">Services disponibles</p>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="(amenity, index) in residence.amenities.slice(0, 3)"
                  :key="index"
                  class="text-xs px-2 py-1 bg-slate-100 text-slate-700 rounded"
                >
                  {{ amenity }}
                </span>
                <span v-if="residence.amenities.length > 3" class="text-xs text-slate-500">
                  +{{ residence.amenities.length - 3 }} autres
                </span>
              </div>
            </div>
          </div>
          <div v-if="residence?.description" class="mt-4 pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500 mb-2">Description</p>
            <p class="text-slate-700 whitespace-pre-line">{{ residence.description }}</p>
          </div>
        </section>

        <!-- 6. Réservations liées -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Réservations liées</h2>
            <Link
              href="/owner/bookings"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              Voir toutes →
            </Link>
          </div>
          <div v-if="bookings.length === 0" class="text-center py-8">
            <Calendar class="w-12 h-12 text-slate-300 mx-auto mb-2" />
            <p class="text-slate-500">Aucune réservation pour cette résidence</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Client</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Dates</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Montant</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="booking in bookings" :key="booking.id" class="hover:bg-slate-50">
                  <td class="px-4 py-3 text-sm font-medium text-slate-900">
                    {{ booking.customer }}
                  </td>
                  <td class="px-4 py-3 text-sm text-slate-600">
                    {{ booking.dates }}
                  </td>
                  <td class="px-4 py-3 text-sm font-medium text-slate-900">
                    {{ formatPrice(booking.amount) }} CFA
                  </td>
                  <td class="px-4 py-3">
                    <span
                      class="text-xs px-3 py-1 rounded-full font-medium"
                      :class="getBookingStatusClass(normalizeStatus(booking))"
                    >
                      {{ getBookingStatusLabel(normalizeStatus(booking)) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <Link
                      :href="`/owner/bookings/${booking.id}`"
                      class="text-blue-600 hover:text-blue-700 text-sm font-medium"
                    >
                      Voir détails →
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 7. Avis clients -->
        <section v-if="stats?.totalReviews > 0" class="bg-white border border-slate-200 rounded-2xl p-6">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Avis clients</h2>
          <div class="space-y-4">
            <div class="flex items-center gap-2">
              <div class="flex items-center">
                <Star
                  v-for="i in 5"
                  :key="i"
                  class="w-5 h-5"
                  :class="i <= Math.round(stats.averageRating) ? 'text-amber-500 fill-amber-500' : 'text-slate-300'"
                />
              </div>
              <span class="text-lg font-semibold text-slate-900">{{ stats.averageRating }}</span>
              <span class="text-slate-500">({{ stats.totalReviews }} avis)</span>
            </div>
            <p class="text-sm text-slate-500">
              Les avis détaillés seront disponibles prochainement.
            </p>
          </div>
        </section>
      </div>

      <!-- Colonne latérale -->
      <div class="space-y-6">
        <!-- 5. Gestion des disponibilités -->
        <section class="bg-white border border-slate-200 rounded-2xl p-4">
          <div class="mb-3">
            <h2 class="text-base font-semibold text-slate-900">Disponibilités</h2>
            <p class="text-xs text-slate-500 mt-1">Cliquez sur une date pour la bloquer/débloquer</p>
          </div>
          <AvailabilityCalendar 
            :bookings="bookingsForCalendar"
            :property-id="residence?.id"
            property-type="residence"
            :blocked-dates="residence?.blockedDates || []"
            :editable="true"
          />
        </section>

        <!-- 8. Localisation -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Localisation</h2>
            <Link
              :href="`/owner/residences/${residence?.id}/edit`"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              Modifier →
            </Link>
          </div>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-slate-500 mb-1">Adresse</p>
              <p class="text-sm text-slate-900">{{ residence?.address || residence?.adresse || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Ville</p>
              <p class="text-sm text-slate-900">{{ residence?.city || residence?.ville || 'N/A' }}</p>
            </div>
            <div v-if="residence?.latitude && residence?.longitude" class="mt-4">
              <div class="aspect-video bg-slate-200 rounded-lg flex items-center justify-center">
                <MapPin class="w-8 h-8 text-slate-400" />
              </div>
              <p class="text-xs text-slate-500 mt-2 text-center">
                {{ residence.latitude }}, {{ residence.longitude }}
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- 9. Actions sensibles -->
    <section class="bg-red-50 border border-red-200 rounded-2xl p-6">
      <h2 class="text-lg font-semibold text-red-900 mb-4">Actions sensibles</h2>
      <div class="flex flex-col sm:flex-row gap-4">
        <button
          @click="toggleDisable"
          class="px-4 py-2 bg-white border border-red-300 text-red-700 rounded-lg hover:bg-red-50 flex items-center justify-center gap-2"
        >
          <Power class="w-4 h-4" />
          {{ residence?.isActive ? 'Désactiver la résidence' : 'Activer la résidence' }}
        </button>
        <button
          @click="confirmDelete"
          class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center justify-center gap-2"
        >
          <Trash2 class="w-4 h-4" />
          Supprimer la résidence
        </button>
      </div>
    </section>

    <!-- Modal de confirmation de suppression -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showDeleteModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Confirmer la suppression</h3>
        <p class="text-slate-600 mb-6">
          Êtes-vous sûr de vouloir supprimer la résidence
          <strong>{{ residence?.title || residence?.name }}</strong> ?
          Cette action est irréversible.
        </p>
        <div class="flex justify-end gap-3">
          <button
            @click="showDeleteModal = false"
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

    <!-- Modal d'image -->
    <div
      v-if="selectedImage"
      class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 touch-manipulation"
      role="dialog"
      aria-modal="true"
      aria-label="Image agrandie"
    >
      <img
        :src="selectedImage"
        alt="Image agrandie"
        class="max-w-full max-h-full object-contain"
        @click.stop
      />
      <button
        type="button"
        @click="selectedImage = null"
        class="absolute top-4 right-4 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 text-white hover:text-slate-300 touch-manipulation"
        aria-label="Fermer"
      >
        <X class="w-6 h-6" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import AvailabilityCalendar from '../../../Components/AvailabilityCalendar.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';
import {
  MapPin,
  Pencil,
  Eye,
  MoreVertical,
  Power,
  Calendar,
  DollarSign,
  Star,
  TrendingUp,
  ImageIcon,
  Trash2,
  X,
  ArrowLeft,
} from 'lucide-vue-next';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  residence?: {
    id: number | string;
    title?: string;
    name?: string;
    type?: string;
    typeResidence?: string;
    address?: string;
    adresse?: string;
    city?: string;
    ville?: string;
    country?: string;
    pricePerNight?: number;
    price?: number;
    capacity?: number;
    capacite?: number;
    bedrooms?: number;
    nombreChambres?: number;
    bathrooms?: number;
    nombreSallesBain?: number;
    description?: string;
    images?: string[];
    amenities?: string[];
    latitude?: number;
    longitude?: number;
    isActive?: boolean;
    status?: string;
    canEdit?: boolean;
    blockedDates?: string[];
  };
  stats?: {
    totalBookings: number;
    totalRevenue: number;
    averageRating: number;
    totalReviews: number;
    occupationRate: number;
    confirmedBookings: number;
    cancelledBookings: number;
    completedBookings: number;
  };
  bookings?: Array<{
    id: number | string;
    customer: string;
    dates: string;
    startDate?: string;
    endDate?: string;
    amount: number;
    status: string;
    statusRaw: string;
    ownerConfirmedAt?: string | null;
    createdAt?: string | null;
  }>;
}>();

const showActionsMenu = ref(false);
const showDeleteModal = ref(false);
const selectedImage = ref<string | null>(null);
const imageErrors = ref<Record<number, boolean>>({});

const toggleActionsMenu = () => {
  showActionsMenu.value = !showActionsMenu.value;
};

const toggleDisable = () => {
  if (!props.residence?.id) return;
  showActionsMenu.value = false;
  router.patch(`/owner/residences/${props.residence.id}/toggle-active`, {}, {
    preserveScroll: true,
  });
};

const confirmDelete = () => {
  showDeleteModal.value = true;
  showActionsMenu.value = false;
};

const deleteResidence = () => {
  if (!props.residence?.id) return;
  showDeleteModal.value = false;
  router.delete(`/owner/residences/${props.residence.id}`, {
    preserveScroll: true,
  });
};

const openImageModal = (image: string) => {
  selectedImage.value = image;
};

const getLocation = (): string => {
  const city = props.residence?.city || props.residence?.ville || '';
  const address = props.residence?.address || props.residence?.adresse || '';
  if (city && address) {
    return `${address}, ${city}`;
  }
  return city || address || 'Localisation non spécifiée';
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatNumber = (value: number): string => {
  return new Intl.NumberFormat('fr-FR').format(value);
};

const formatType = (type: string): string => {
  const typeMap: Record<string, string> = {
    villa: 'Villa',
    appartement: 'Appartement',
    maison: 'Maison',
    studio: 'Studio',
    penthouse: 'Penthouse',
  };
  return typeMap[type?.toLowerCase()] || type || 'N/A';
};

const getStatusClass = (status: string | boolean | undefined): string => {
  if (status === undefined || status === null) {
    return 'bg-slate-100 text-slate-700';
  }
  if (typeof status === 'boolean') {
    return status
      ? 'bg-emerald-100 text-emerald-700'
      : 'bg-red-100 text-red-700';
  }
  const statusLower = status.toLowerCase();
  if (statusLower === 'active' || statusLower === 'available' || statusLower === 'disponible') {
    return 'bg-emerald-100 text-emerald-700';
  }
  if (statusLower === 'inactive' || statusLower === 'unavailable') {
    return 'bg-red-100 text-red-700';
  }
  return 'bg-amber-100 text-amber-700';
};

const getStatusLabel = (status: string | boolean | undefined): string => {
  if (status === undefined || status === null) {
    return 'Inconnu';
  }
  if (typeof status === 'boolean') {
    return status ? 'Active' : 'Inactive';
  }
  const statusMap: Record<string, string> = {
    active: 'Active',
    inactive: 'Inactive',
    available: 'Disponible',
    unavailable: 'Indisponible',
    pending: 'En attente',
  };
  return statusMap[status.toLowerCase()] || status;
};

const normalizeStatus = (booking: { status?: string; statusRaw?: string; ownerConfirmedAt?: string | null; createdAt?: string | null }): string => {
  const raw = (booking.statusRaw || booking.status || 'pending').toLowerCase();

  // 1. Priorité aux statuts finaux.
  if (['confirmed', 'cancelled', 'paid', 'expired'].includes(raw)) {
    return raw;
  }

  // 2. Si le propriétaire a confirmé.
  if (booking.ownerConfirmedAt && String(booking.ownerConfirmedAt).trim() !== '' && String(booking.ownerConfirmedAt).toLowerCase() !== 'null') {
    return 'confirmed';
  }

  // 3. Règle des 5 minutes pour pending.
  if (raw === 'pending' && booking.createdAt) {
    const createdAtMs = new Date(booking.createdAt).getTime();
    if (!Number.isNaN(createdAtMs)) {
      const ageMs = Date.now() - createdAtMs;
      if (ageMs > 5 * 60 * 1000) {
        return 'expired';
      }
    }
  }

  return 'pending';
};

const getBookingStatusClass = (status: string): string => {
  const statusLower = (status || '').toLowerCase().trim();
  if (statusLower === 'confirmed') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'paid') {
    return 'bg-blue-100 text-blue-700';
  } else if (statusLower === 'pending') {
    return 'bg-amber-100 text-amber-700';
  } else if (statusLower === 'expired') {
    return 'bg-red-100 text-red-700';
  } else if (statusLower === 'cancelled') {
    return 'bg-slate-100 text-slate-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getBookingStatusLabel = (status: string): string => {
  const statusLower = (status || '').toLowerCase().trim();
  const statusMap: Record<string, string> = {
    pending: 'En attente',
    expired: 'Expirée',
    confirmed: 'Confirmée',
    paid: 'Payée',
    cancelled: 'Annulée',
  };
  return statusMap[statusLower] || status || 'Inconnu';
};

const handleImageError = (index: number) => {
  imageErrors.value[index] = true;
};

// Préparer les données pour le calendrier
const bookingsForCalendar = computed(() => {
  return (props.bookings || []).map(booking => ({
    id: booking.id,
    startDate: booking.startDate || null,
    endDate: booking.endDate || null,
    status: booking.status,
    statusRaw: booking.statusRaw || booking.status?.toLowerCase(),
  })).filter(b => b.startDate && b.endDate);
});
</script>
