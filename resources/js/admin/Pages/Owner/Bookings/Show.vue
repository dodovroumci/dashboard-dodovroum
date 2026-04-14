<template>
  <div class="space-y-6">
    <!-- Header avec navigation -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="flex items-center gap-3 sm:gap-4 min-w-0">
        <Link
          :href="route('owner.bookings.index')"
          class="p-2 hover:bg-slate-100 rounded-lg transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center touch-manipulation"
        >
          <ArrowLeft class="w-5 h-5 text-slate-600 shrink-0" />
        </Link>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Détails de Réservation</h1>
      </div>
      <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
        <button
          @click="window.print()"
          class="min-h-[44px] min-w-[44px] p-2 hover:bg-slate-100 rounded-lg transition-colors flex items-center justify-center touch-manipulation"
          title="Imprimer"
        >
          <Printer class="w-5 h-5 text-slate-600" />
        </button>
        <button
          v-if="canCancelBooking()"
          @click="handleCancel"
          class="px-4 py-2.5 sm:py-2 min-h-[44px] bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium text-sm"
        >
          Annuler
        </button>
      </div>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- Grande boîte bleue avec résumé -->
    <div class="rounded-2xl p-4 sm:p-8 text-white shadow-lg overflow-hidden" style="background: rgb(26, 51, 101);">
      <div class="flex items-start justify-between mb-4 sm:mb-6 min-w-0">
        <div class="min-w-0 flex-1">
          <div class="mb-3 sm:mb-4">
            <span
              class="inline-flex items-center px-3 sm:px-4 py-1.5 sm:py-2 rounded-full text-xs sm:text-sm font-semibold uppercase"
              :class="getStatusBadgeClass(booking.status)"
            >
              {{ formatStatus(booking.status).toUpperCase() }}
            </span>
          </div>
          <h2 class="text-2xl sm:text-4xl font-bold mb-2 truncate">
            #{{ booking.id?.substring(0, 8) || 'N/A' }}
          </h2>
          <p class="text-blue-100 text-base sm:text-lg truncate">
            {{ formatDateRange(booking.startDate, booking.endDate) }}
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-3 sm:gap-6 pt-4 border-t" style="border-color: rgba(255, 255, 255, 0.2);">
        <div class="flex items-center gap-2">
          <Calendar class="w-5 h-5" />
          <span class="text-sm font-medium">{{ getNightsCount() }} Nuit{{ getNightsCount() > 1 ? 's' : '' }}</span>
        </div>
        <div class="flex items-center gap-2">
          <Users class="w-5 h-5" />
          <span class="text-sm font-medium">
            {{ getGuestsCount() }} Invité{{ getGuestsCount() > 1 ? 's' : '' }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <DollarSign class="w-5 h-5" />
          <span class="text-sm font-medium">{{ formatPrice(booking.totalPrice) }} CFA</span>
        </div>
      </div>
    </div>

    <!-- Timeline de suivi -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
      <div class="flex items-center justify-between">
        <!-- Propriétaire confirmé (premier) -->
        <div class="flex items-center gap-3 flex-1">
          <div 
            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            :style="booking.ownerConfirmedAt ? 'background: rgb(26, 51, 101);' : 'background: rgb(203, 213, 225);'"
          >
            <CheckCircle v-if="booking.ownerConfirmedAt" class="w-6 h-6 text-white" />
            <Clock v-else class="w-6 h-6 text-slate-500" />
          </div>
          <div>
            <p class="text-sm font-medium" :class="booking.ownerConfirmedAt ? 'text-slate-900' : 'text-slate-900'">
              Propriétaire confirmé
            </p>
            <p v-if="booking.ownerConfirmedAt" class="text-xs text-slate-600">
              {{ formatShortDate(booking.ownerConfirmedAt) }}
            </p>
            <p v-else class="text-xs text-slate-500">En attente</p>
          </div>
        </div>
        <div 
          class="flex-1 h-0.5 mx-4"
          :style="booking.ownerConfirmedAt ? 'background: rgb(26, 51, 101);' : 'background: rgb(226, 232, 240);'"
        ></div>
        
        <!-- Clé récupérée (deuxième) -->
        <div class="flex items-center gap-3 flex-1">
          <div 
            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            :style="booking.keyRetrievedAt ? 'background: rgb(26, 51, 101);' : 'background: rgb(203, 213, 225);'"
          >
            <CheckCircle v-if="booking.keyRetrievedAt" class="w-6 h-6 text-white" />
            <Clock v-else class="w-6 h-6 text-slate-500" />
          </div>
          <div>
            <p class="text-sm font-medium" :class="booking.keyRetrievedAt ? 'text-slate-900' : 'text-slate-900'">
              Clé récupérée
            </p>
            <p v-if="booking.keyRetrievedAt" class="text-xs text-slate-600">
              {{ formatShortDate(booking.keyRetrievedAt) }}
            </p>
            <p v-else class="text-xs text-slate-500">En attente</p>
          </div>
        </div>
        <div 
          class="flex-1 h-0.5 mx-4"
          :style="booking.keyRetrievedAt ? 'background: rgb(26, 51, 101);' : 'background: rgb(226, 232, 240);'"
        ></div>
        
        <!-- Check-out effectué (troisième) -->
        <div class="flex items-center gap-3 flex-1">
          <div 
            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            :style="isCheckOutCompleted ? 'background: rgb(26, 51, 101);' : 'background: rgb(203, 213, 225);'"
          >
            <CheckCircle v-if="isCheckOutCompleted" class="w-6 h-6 text-white" />
            <Clock v-else class="w-6 h-6 text-slate-500" />
          </div>
          <div>
            <p class="text-sm font-medium" :class="isCheckOutCompleted ? 'text-slate-900' : 'text-slate-900'">
              Check-out effectué
            </p>
            <p v-if="isCheckOutCompleted" class="text-xs text-slate-600">
              {{ booking.checkOutAt ? formatShortDate(booking.checkOutAt) : (booking.endDate ? formatShortDate(booking.endDate) : 'Terminé') }}
            </p>
            <p v-else class="text-xs text-slate-500">En attente</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmer le départ (checkout manuel) -->
    <div
      v-if="canShowManualCheckOut"
      class="mt-6 p-4 sm:p-6 bg-blue-50 border border-blue-100 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm"
    >
      <div>
        <p class="text-blue-800 font-bold">Le client a fini son séjour ?</p>
        <p class="text-blue-600 text-sm mt-1">
          Si le client a oublié de valider son départ, vous pouvez clôturer la réservation manuellement.
        </p>
      </div>
      <button
        type="button"
        @click="handleManualCheckOut"
        class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2"
      >
        <CheckCircle class="w-4 h-4" />
        Confirmer le départ
      </button>
    </div>

    <!-- Deux colonnes principales -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Colonne gauche : INTERVENANTS -->
      <div class="space-y-6">
        <h2 class="text-xl font-bold text-slate-900">INTERVENANTS</h2>

        <!-- Carte Client -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold" style="background: rgb(26, 51, 101);">
              {{ getInitials(booking.customerName || booking.customer) }}
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-slate-900 mb-1">{{ booking.customerName || booking.customer || 'Client inconnu' }}</h3>
              <p class="text-sm text-slate-500 mb-4">Client / Locataire</p>
              <div class="space-y-2">
                <div v-if="booking.customerEmail" class="flex items-center gap-2">
                  <Mail class="w-4 h-4 text-slate-400" />
                  <a :href="'mailto:' + booking.customerEmail" class="text-sm text-slate-700 hover:text-blue-600">
                    {{ booking.customerEmail }}
                  </a>
                </div>
                <div v-if="booking.customerPhone" class="flex items-center gap-2">
                  <Phone class="w-4 h-4 text-slate-400" />
                  <a :href="'tel:' + booking.customerPhone" class="text-sm text-slate-700 hover:text-blue-600">
                    {{ booking.customerPhone }}
                  </a>
                  <button class="ml-2 p-1 hover:bg-slate-100 rounded">
                    <MessageCircle class="w-4 h-4 text-slate-400" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Colonne droite : LA RÉSERVATION -->
      <div class="space-y-6">
        <h2 class="text-xl font-bold text-slate-900">LA RÉSERVATION</h2>

        <!-- Infos Clés -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex items-center gap-2 mb-4">
            <Info class="w-5 h-5 text-blue-600" />
            <h3 class="text-lg font-semibold text-slate-900">Infos Clés</h3>
          </div>
          <dl class="space-y-4">
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">STATUT ACTUEL</dt>
              <dd>
                <span
                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">CRÉÉE LE</dt>
              <dd class="text-sm text-slate-900">{{ formatShortDate(booking.createdAt) }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">DÉBUT</dt>
              <dd class="text-sm text-slate-900">{{ formatShortDate(booking.startDate) }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">FIN</dt>
              <dd class="text-sm text-slate-900">{{ formatShortDate(booking.endDate) }}</dd>
            </div>
            <div v-if="booking.unitPriceAmount">
              <dt class="text-sm font-medium text-slate-500 mb-1">PRIX UNITAIRE</dt>
              <dd class="text-sm text-slate-900 font-semibold">
                {{ formatPrice(booking.unitPriceAmount) }} CFA {{ formatUnitSuffix(booking.unitPriceLabel) }}
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">PRIX TOTAL</dt>
              <dd class="text-sm text-slate-900 font-semibold">{{ formatPrice(booking.totalPrice || booking.total || 0) }} CFA</dd>
            </div>
          </dl>
        </div>

        <!-- Bien Réservé (Véhicule, Résidence ou Offre) -->
        <div v-if="booking.vehicleName || (booking.propertyName && booking.propertyName !== 'Propriété inconnue') || booking.offerName" class="bg-white border border-slate-200 rounded-xl p-6">
          <!-- Véhicule -->
          <div v-if="booking.vehicleName || booking.vehicleDetails">
            <div class="flex items-center gap-2 mb-4">
              <Truck class="w-5 h-5" style="color: rgb(26, 51, 101);" />
              <h3 class="text-lg font-semibold text-slate-900">Véhicule Réservé</h3>
            </div>
            <div v-if="getVehicleImage()" class="mb-4">
              <img
                :src="getStorageImageUrl(getVehicleImage(), 'vehicles')"
                :alt="booking.vehicleName"
                class="w-full h-48 object-cover rounded-lg"
                @error="handleImageError"
              />
            </div>
            <div v-else class="mb-4 w-full h-48 bg-slate-100 rounded-lg flex items-center justify-center">
              <Truck class="w-16 h-16 text-slate-400" />
            </div>
            <div class="space-y-2">
              <h4 class="font-semibold text-slate-900">{{ booking.vehicleName }}</h4>
              <div v-if="booking.vehicleDetails" class="space-y-1 text-sm text-slate-600">
                <div v-if="booking.vehicleDetails.type || booking.vehicleDetails.categorie">
                  {{ booking.vehicleDetails.type || booking.vehicleDetails.categorie }}
                  <span v-if="booking.vehicleDetails.transmission"> - {{ booking.vehicleDetails.transmission }}</span>
                </div>
                <div v-if="booking.vehicleDetails.adresse || booking.vehicleDetails.location" class="flex items-center gap-1">
                  <MapPin class="w-4 h-4" />
                  {{ booking.vehicleDetails.adresse || booking.vehicleDetails.location }}
                </div>
                <div v-if="booking.vehicleDetails.plaque || booking.vehicleDetails.licensePlate" class="flex items-center gap-1">
                  <span class="text-xs">Plaque:</span>
                  {{ booking.vehicleDetails.plaque || booking.vehicleDetails.licensePlate }}
                </div>
              </div>
              <button
                v-if="booking.vehicleId"
                @click="viewVehicle(booking.vehicleId)"
                class="mt-3 px-4 py-2 text-white rounded-lg transition-colors text-sm font-medium hover:opacity-90"
                style="background: rgb(26, 51, 101);"
              >
                Voir la fiche
              </button>
            </div>
          </div>

          <!-- Résidence -->
          <div v-else-if="booking.propertyName && booking.propertyName !== 'Propriété inconnue'">
            <div class="flex items-center gap-2 mb-4">
              <Building2 class="w-5 h-5" style="color: rgb(26, 51, 101);" />
              <h3 class="text-lg font-semibold text-slate-900">Résidence Réservée</h3>
            </div>
            <div v-if="getResidenceImage()" class="mb-4">
              <img
                :src="getStorageImageUrl(getResidenceImage(), 'residences')"
                :alt="booking.propertyName"
                class="w-full h-48 object-cover rounded-lg"
                @error="handleImageError"
              />
            </div>
            <div v-else class="mb-4 w-full h-48 bg-slate-100 rounded-lg flex items-center justify-center">
              <Building2 class="w-16 h-16 text-slate-400" />
            </div>
            <div class="space-y-2">
              <h4 class="font-semibold text-slate-900">{{ booking.propertyName }}</h4>
              <div v-if="booking.residenceDetails" class="space-y-1 text-sm text-slate-600">
                <div v-if="booking.residenceDetails.ville" class="flex items-center gap-1">
                  <MapPin class="w-4 h-4" />
                  {{ booking.residenceDetails.ville }}
                  <span v-if="booking.residenceDetails.adresse">, {{ booking.residenceDetails.adresse }}</span>
                </div>
                <div v-if="booking.residenceDetails.capacite">
                  Capacité: {{ booking.residenceDetails.capacite }} personnes
                </div>
              </div>
              <button
                v-if="booking.residenceId"
                @click="viewResidence(booking.residenceId)"
                class="mt-3 px-4 py-2 text-white rounded-lg transition-colors text-sm font-medium hover:opacity-90"
                style="background: rgb(26, 51, 101);"
              >
                Voir la fiche
              </button>
            </div>
          </div>

          <!-- Offre Combinée -->
          <div v-else-if="booking.offerName">
            <div class="flex items-center gap-2 mb-4">
              <Package class="w-5 h-5" style="color: rgb(26, 51, 101);" />
              <h3 class="text-lg font-semibold text-slate-900">Offre Combinée Réservée</h3>
            </div>
            <div v-if="getOfferImage()" class="mb-4">
              <img
                :src="getStorageImageUrl(getOfferImage(), 'offers')"
                :alt="booking.offerName"
                class="w-full h-48 object-cover rounded-lg"
                @error="handleImageError"
              />
            </div>
            <div v-else class="mb-4 w-full h-48 bg-slate-100 rounded-lg flex items-center justify-center">
              <Package class="w-16 h-16 text-slate-400" />
            </div>
            <div class="space-y-2">
              <h4 class="font-semibold text-slate-900">{{ booking.offerName }}</h4>
              <div v-if="booking.offerDetails" class="space-y-1 text-sm text-slate-600">
                <div v-if="booking.offerDetails.description">
                  {{ booking.offerDetails.description }}
                </div>
                <div v-if="booking.offerDetails.prixPack">
                  Prix pack: {{ formatPrice(booking.offerDetails.prixPack) }} CFA
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Informations techniques (collapsible) -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <button
        @click="showTechnicalInfo = !showTechnicalInfo"
        class="w-full px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors"
      >
        <div class="flex items-center gap-2">
          <Settings class="w-5 h-5 text-slate-600" />
          <span class="font-semibold text-slate-900">Informations Techniques</span>
        </div>
        <ChevronDown
          class="w-5 h-5 text-slate-600 transition-transform"
          :class="{ 'rotate-180': showTechnicalInfo }"
        />
      </button>
      <div v-if="showTechnicalInfo" class="px-6 py-4 border-t border-slate-200">
        <dl class="space-y-2">
          <div>
            <dt class="text-xs font-medium text-slate-500">ID Réservation</dt>
            <dd class="text-xs text-slate-500 font-mono break-all">{{ booking.id }}</dd>
          </div>
          <div v-if="booking.clientId">
            <dt class="text-xs font-medium text-slate-500">ID Client</dt>
            <dd class="text-xs text-slate-500 font-mono break-all">{{ booking.clientId }}</dd>
          </div>
          <div v-if="booking.ownerId">
            <dt class="text-xs font-medium text-slate-500">ID Propriétaire</dt>
            <dd class="text-xs text-slate-500 font-mono break-all">{{ booking.ownerId }}</dd>
          </div>
          <div v-if="booking.reviewId">
            <dt class="text-xs font-medium text-slate-500">ID Avis</dt>
            <dd class="text-xs text-slate-500 font-mono break-all">{{ booking.reviewId }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';
import {
  ArrowLeft,
  Printer,
  Calendar,
  Users,
  DollarSign,
  CheckCircle,
  Clock,
  Mail,
  Phone,
  MessageCircle,
  MapPin,
  Info,
  Truck,
  Building2,
  Package,
  Settings,
  ChevronDown,
} from 'lucide-vue-next';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  booking: {
    id: string;
    bookingType?: 'residence' | 'vehicle' | 'package' | 'unknown';
    customerName?: string;
    customer?: string;
    customerEmail?: string | null;
    customerPhone?: string | null;
    clientId?: string | null;
    propertyName?: string;
    residenceId?: string | null;
    residenceDetails?: any | null;
    vehicleName?: string | null;
    vehicleId?: string | null;
    vehicleDetails?: any | null;
    vehicleDriverOption?: 'with_driver' | 'without_driver' | null;
    offerName?: string | null;
    offerId?: string | null;
    offerDetails?: any | null;
    startDate?: string | null;
    endDate?: string | null;
    totalPrice?: number;
    total?: number;
    unitPriceAmount?: number | null;
    unitPriceLabel?: string | null;
    status: string;
    createdAt?: string | null;
    keyRetrievedAt?: string | null;
    ownerConfirmedAt?: string | null;
    checkOutAt?: string | null;
    isStayInProgress?: boolean;
    ownerId?: string | null;
    ownerName?: string | null;
    ownerPhone?: string | null;
    ownerAddress?: string | null;
    reviewId?: string | null;
    reviews?: Array<{
      id: string;
      rating?: number;
      note?: number;
      comment?: string;
      commentaire?: string;
      text?: string;
      createdAt?: string;
      user?: {
        firstName?: string;
        prenom?: string;
        lastName?: string;
        nom?: string;
        name?: string;
      };
      client?: {
        name?: string;
      };
    }>;
  };
}>();

const showTechnicalInfo = ref(false);

const formatShortDate = (date: string | null | undefined): string => {
  if (!date) return 'Non définie';
  try {
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: '2-digit',
    });
  } catch {
    return date;
  }
};

const formatDateRange = (startDate: string | null | undefined, endDate: string | null | undefined): string => {
  if (!startDate || !endDate) return 'Dates non définies';
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} - ${end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}`;
  } catch {
    return `${startDate} - ${endDate}`;
  }
};

const formatPrice = (price: number | null | undefined): string => {
  if (!price) return '0';
  return new Intl.NumberFormat('fr-FR').format(price);
};

const imageErrors = ref<Record<string, boolean>>({});

const handleImageError = (event: Event) => {
  const target = event.target as HTMLImageElement;
  if (target) {
    imageErrors.value[target.src] = true;
  }
};

const getVehicleImage = (): string | null => {
  if (!props.booking.vehicleDetails) return null;
  const details = props.booking.vehicleDetails;
  return details.image || details.photo || details.images?.[0] || null;
};

const getResidenceImage = (): string | null => {
  if (!props.booking.residenceDetails) return null;
  const details = props.booking.residenceDetails;
  return details.image || details.photo || details.images?.[0] || details.photos?.[0] || null;
};

const getOfferImage = (): string | null => {
  if (!props.booking.offerDetails) return null;
  const details = props.booking.offerDetails;
  return details.image || details.photo || details.images?.[0] || null;
};

const formatStatus = (status: string): string => {
  const statusLower = status.toLowerCase();
  const statusMap: Record<string, string> = {
    awaiting_payment: 'En attente de paiement',
    awaitingpayment: 'En attente de paiement',
    pending: 'En attente',
    'en attente': 'En attente',
    confirmed: 'Confirmée',
    confirmee: 'Confirmée',
    'confirmée': 'Confirmée',
    cancelled: 'Annulée',
    canceled: 'Annulée',
    'annulée': 'Annulée',
    completed: 'Terminée',
    terminee: 'Terminée',
    'terminée': 'Terminée',
  };
  return statusMap[statusLower] || status;
};

const getStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'awaiting_payment' || statusLower === 'awaitingpayment') {
    return 'bg-orange-100 text-orange-900';
  }
  if (statusLower === 'confirmed' || statusLower === 'confirmee' || statusLower === 'confirmée') {
    return 'bg-emerald-100 text-emerald-800';
  } else if (statusLower === 'pending' || statusLower === 'en attente') {
    return 'bg-yellow-100 text-yellow-800';
  } else if (statusLower === 'cancelled' || statusLower === 'canceled' || statusLower === 'annulee' || statusLower === 'annulée') {
    return 'bg-red-100 text-red-800';
  } else if (statusLower === 'completed' || statusLower === 'terminee' || statusLower === 'terminée') {
    return 'bg-blue-100 text-blue-800';
  }
  return 'bg-slate-100 text-slate-800';
};

const getStatusBadgeClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'awaiting_payment' || statusLower === 'awaitingpayment') {
    return 'bg-orange-500 text-white';
  }
  if (statusLower === 'confirmed' || statusLower === 'confirmee' || statusLower === 'confirmée') {
    return 'bg-emerald-500 text-white';
  } else if (statusLower === 'pending' || statusLower === 'en attente') {
    return 'bg-yellow-500 text-white';
  } else if (statusLower === 'cancelled' || statusLower === 'canceled' || statusLower === 'annulee' || statusLower === 'annulée') {
    return 'bg-red-500 text-white';
  } else if (statusLower === 'completed' || statusLower === 'terminee' || statusLower === 'terminée') {
    return 'bg-blue-500 text-white';
  }
  return 'bg-slate-500 text-white';
};

const formatUnitSuffix = (label: string | null | undefined): string => {
  if (!label) return '';
  switch (label) {
    case 'night':
      return '/ nuit';
    case 'day':
      return '/ jour';
    case 'pack':
      return 'par pack';
    default:
      return '/ unité';
  }
};

const getNightsCount = (): number => {
  if (!props.booking.startDate || !props.booking.endDate) return 0;
  try {
    const start = new Date(props.booking.startDate);
    const end = new Date(props.booking.endDate);
    const diffTime = Math.abs(end.getTime() - start.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  } catch {
    return 0;
  }
};

const getGuestsCount = (): number => {
  // Essayer de récupérer depuis les détails de résidence
  if (props.booking.residenceDetails?.capacite) {
    return props.booking.residenceDetails.capacite;
  }
  // Essayer depuis les détails de véhicule (places)
  if (props.booking.vehicleDetails?.places) {
    return props.booking.vehicleDetails.places;
  }
  // Valeur par défaut
  return 2;
};

const getInitials = (name: string | null | undefined): string => {
  if (!name) return '?';
  const parts = name.trim().split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

const isEndDatePassed = (): boolean => {
  if (!props.booking.endDate) return false;
  try {
    const end = new Date(props.booking.endDate);
    end.setHours(23, 59, 59, 999); // Fin de journée
    const today = new Date();
    // La date de fin est passée si elle est < aujourd'hui (pas <= car on peut être le jour de fin)
    return end < today;
  } catch {
    return false;
  }
};

// Vérifier si le check-out est complété (soit via checkOutAt, soit si la date de fin est passée)
const isCheckOutCompleted = computed(() => {
  // Si checkOutAt existe, le check-out est complété
  if (props.booking.checkOutAt) {
    return true;
  }
  // Sinon, si la date de fin est passée, considérer le check-out comme complété
  return isEndDatePassed();
});

const canCancelBooking = (): boolean => {
  const status = props.booking.status?.toLowerCase() || '';
  // Ne pas permettre l'annulation si la réservation est terminée, annulée ou complétée
  const finalStatuses = ['completed', 'terminee', 'terminée', 'cancelled', 'canceled', 'annulée', 'annulee'];
  return !finalStatuses.includes(status);
};

const handleCancel = () => {
  if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
    // TODO: Implémenter l'annulation
    console.log('Annulation de la réservation', props.booking.id);
  }
};

const viewVehicle = (vehicleId: string) => {
  window.location.href = `/owner/vehicles/${vehicleId}`;
};

const viewResidence = (residenceId: string) => {
  window.location.href = `/owner/residences/${residenceId}`;
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'owner.bookings.index': '/owner/bookings',
    'owner.bookings.show': (id: string) => `/owner/bookings/${id}`,
    'owner.bookings.confirm-checkout': (id: string) => `/owner/bookings/${id}/confirm-checkout`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params ?? props.booking.id);
  }
  return routes[name] || '#';
};

const canShowManualCheckOut = computed(() => {
  const b = props.booking;
  if (b.checkOutAt) return false;
  if (b.isStayInProgress === true) return true;
  if (b.keyRetrievedAt && b.endDate) {
    try {
      const end = new Date(b.endDate);
      return new Date() <= end;
    } catch {
      return false;
    }
  }
  return false;
});

const handleManualCheckOut = () => {
  if (!confirm('Confirmer le départ ? Cela clôturera officiellement la réservation.')) return;
  router.patch(route('owner.bookings.confirm-checkout'), {}, {
    preserveScroll: true,
    onSuccess: () => {},
  });
};
</script>
