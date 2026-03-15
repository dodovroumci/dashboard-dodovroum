<template>
  <div class="space-y-6">
    <!-- Lien de retour -->
    <Link
      href="/owner/combo-offers"
      class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors min-w-0"
    >
      <ArrowLeft class="w-4 h-4 shrink-0" />
      <span class="truncate">Retour à la liste des offres combinées</span>
    </Link>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- 1. En-tête de l'offre combinée -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between min-w-0">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
            <h1 class="text-xl sm:text-3xl font-bold text-slate-900 truncate min-w-0">
              {{ comboOffer?.title || comboOffer?.name || 'Offre combinée sans nom' }}
            </h1>
            <span
              class="px-3 py-1 text-sm font-medium rounded-full shrink-0"
              :class="getStatusClass(comboOffer?.status || comboOffer?.isActive)"
            >
              {{ getStatusLabel(comboOffer?.status || comboOffer?.isActive) }}
            </span>
          </div>
          <div v-if="comboOffer?.description" class="text-slate-600 line-clamp-2 min-w-0">
            {{ comboOffer.description }}
          </div>
        </div>
        <div class="flex items-center gap-2 relative shrink-0 flex-shrink-0">
          <Link
            v-if="comboOffer?.canEdit !== false"
            :href="`/owner/combo-offers/${comboOffer?.id}/edit`"
            class="px-4 py-2.5 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 min-h-[44px] text-sm font-medium"
          >
            <Pencil class="w-4 h-4 shrink-0" />
            Modifier
          </Link>
          <button
            type="button"
            @click="toggleActionsMenu"
            class="min-h-[44px] min-w-[44px] p-2 border border-slate-300 rounded-lg hover:bg-slate-50 flex items-center justify-center touch-manipulation"
            aria-label="Actions"
          >
            <MoreVertical class="w-5 h-5 text-slate-600" />
          </button>
          <div
            v-if="showActionsMenu"
            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-xl border border-slate-200 z-50 py-1"
          >
            <div class="py-1">
              <button
                v-if="comboOffer?.canEdit !== false"
                @click="confirmDelete"
                class="w-full text-left px-4 py-2.5 min-h-[44px] text-sm text-red-600 hover:bg-red-50 flex items-center gap-2 touch-manipulation"
              >
                <Trash2 class="w-4 h-4" />
                Supprimer
              </button>
              <button
                v-else
                disabled
                class="w-full text-left px-4 py-2.5 min-h-[44px] text-sm text-slate-400 cursor-not-allowed flex items-center gap-2"
              >
                <Trash2 class="w-4 h-4" />
                Supprimer (non autorisé)
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Galerie (aperçu) -->
    <section class="bg-white border border-slate-200 rounded-2xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Galerie</h2>
        <Link
          v-if="comboOffer?.canEdit !== false"
          :href="`/owner/combo-offers/${comboOffer?.id}/edit`"
          class="text-sm text-blue-600 hover:text-blue-700 font-medium"
        >
          Modifier les photos →
        </Link>
      </div>
      <div v-if="allImages.length > 0" class="grid grid-cols-4 gap-4">
        <div
          v-for="(image, index) in allImages.slice(0, 5)"
          :key="index"
          role="button"
          tabindex="0"
          class="aspect-video bg-slate-200 rounded-lg overflow-hidden cursor-pointer relative group touch-manipulation"
          @click="openImageModal(image, index)"
          @keydown.enter.prevent="openImageModal(image, index)"
          @keydown.space.prevent="openImageModal(image, index)"
        >
          <img
            v-if="!imageErrors[index]"
            :src="getStorageImageUrl(image)"
            :alt="`Image ${index + 1}`"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform"
            @error="() => handleImageError(index)"
            @load="() => imageErrors[index] = false"
          />
          <div v-else class="w-full h-full flex items-center justify-center bg-slate-100">
            <ImageIcon class="w-8 h-8 text-slate-400" />
          </div>
        </div>
      </div>
      <div v-else class="aspect-video bg-slate-200 rounded-lg flex items-center justify-center">
        <div class="text-center">
          <ImageIcon class="w-12 h-12 text-slate-400 mx-auto mb-2" />
          <p class="text-slate-500">Aucune image disponible</p>
          <Link
            v-if="comboOffer?.canEdit !== false"
            :href="`/owner/combo-offers/${comboOffer?.id}/edit`"
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
          <TrendingUp class="w-5 h-5 text-blue-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Taux de conversion</p>
        <p class="text-2xl font-semibold text-blue-600">{{ stats?.conversionRate || 0 }}%</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-2">
          <Star class="w-5 h-5 text-amber-500" />
        </div>
        <p class="text-sm text-slate-500 mb-1">Réservations confirmées</p>
        <p class="text-2xl font-semibold text-slate-900">{{ formatNumber(stats?.confirmedBookings || 0) }}</p>
      </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Colonne principale -->
      <div class="lg:col-span-2 space-y-6">
        <!-- 4. Informations de l'offre -->
        <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4 min-w-0">
            <h2 class="text-base sm:text-lg font-semibold text-slate-900 truncate">Informations de l'offre</h2>
            <Link
              v-if="comboOffer?.canEdit !== false"
              :href="`/owner/combo-offers/${comboOffer?.id}/edit`"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium shrink-0"
            >
              Modifier les informations →
            </Link>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Prix original</p>
              <p class="font-medium text-slate-900 line-through break-words">
                {{ formatPrice(comboOffer?.originalPrice || 0) }} CFA
              </p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Prix réduit</p>
              <p class="font-medium text-emerald-600 text-lg break-words">
                {{ formatPrice(comboOffer?.discountedPrice || comboOffer?.price || 0) }} CFA
              </p>
            </div>
            <div v-if="comboOffer?.discount && comboOffer.discount > 0" class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Réduction</p>
              <p class="font-medium text-emerald-600 break-words">
                -{{ comboOffer.discount || comboOffer.discountPercentage || 0 }}%
              </p>
            </div>
            <div v-if="comboOffer?.nbJours" class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Nombre de jours</p>
              <p class="font-medium text-slate-900 break-words">
                {{ comboOffer.nbJours }} jours
              </p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Date de début</p>
              <p class="font-medium text-slate-900 break-words">
                {{ formatDate(comboOffer?.startDate) }}
              </p>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 mb-1">Date de fin</p>
              <p class="font-medium text-slate-900 break-words">
                {{ formatDate(comboOffer?.endDate) }}
              </p>
            </div>
          </div>
          <div v-if="comboOffer?.description" class="mt-4 pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500 mb-2">Description</p>
            <p class="text-slate-700 whitespace-pre-line">{{ comboOffer.description }}</p>
          </div>
        </section>

        <!-- 5. Résidence incluse -->
        <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4 min-w-0">
            <h2 class="text-base sm:text-lg font-semibold text-slate-900 truncate">Résidence incluse</h2>
            <Link
              v-if="comboOffer?.residenceId"
              :href="`/owner/residences/${comboOffer.residenceId}`"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium shrink-0"
            >
              Voir les détails →
            </Link>
          </div>
          <div v-if="comboOffer?.residence">
            <dl class="space-y-3 min-w-0">
              <div class="min-w-0">
                <dt class="text-sm font-medium text-slate-500">Nom</dt>
                <dd class="mt-1 text-sm text-slate-900 font-medium break-words">
                  {{ comboOffer.residence.nom || comboOffer.residence.name || comboOffer.residence.title || 'N/A' }}
                </dd>
              </div>
              <div v-if="comboOffer.residence.ville || comboOffer.residence.city" class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Ville</dt>
                  <dd class="mt-1 text-sm text-slate-900 break-words">
                    {{ comboOffer.residence.ville || comboOffer.residence.city }}
                  </dd>
                </div>
                <div v-if="comboOffer.residence.prixParNuit || comboOffer.residence.pricePerNight" class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Prix par nuit</dt>
                  <dd class="mt-1 text-sm font-semibold text-slate-900 break-words">
                    {{ formatPrice(comboOffer.residence.prixParNuit || comboOffer.residence.pricePerNight || 0) }} CFA
                  </dd>
                </div>
              </div>
              <div v-if="comboOffer.residence.capacite || comboOffer.residence.capacity" class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Capacité</dt>
                  <dd class="mt-1 text-sm text-slate-900 break-words">
                    {{ comboOffer.residence.capacite || comboOffer.residence.capacity }} personnes
                  </dd>
                </div>
                <div v-if="comboOffer.residence.chambres || comboOffer.residence.bedrooms" class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Chambres</dt>
                  <dd class="mt-1 text-sm text-slate-900 break-words">
                    {{ comboOffer.residence.chambres || comboOffer.residence.bedrooms }}
                  </dd>
                </div>
              </div>
            </dl>
          </div>
          <div v-else class="text-center py-8 text-slate-500">
            Résidence non disponible
          </div>
        </section>

        <!-- 6. Véhicule inclus -->
        <section class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4 min-w-0">
            <h2 class="text-base sm:text-lg font-semibold text-slate-900 truncate">Véhicule inclus</h2>
            <Link
              v-if="comboOffer?.vehicleId"
              :href="`/owner/vehicles/${comboOffer.vehicleId}`"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium shrink-0"
            >
              Voir les détails →
            </Link>
          </div>
          <div v-if="comboOffer?.vehicle">
            <dl class="space-y-3 min-w-0">
              <div class="min-w-0">
                <dt class="text-sm font-medium text-slate-500">Nom</dt>
                <dd class="mt-1 text-sm text-slate-900 font-medium break-words">
                  {{ comboOffer.vehicle.titre || comboOffer.vehicle.name || (comboOffer.vehicle.marque && comboOffer.vehicle.modele ? `${comboOffer.vehicle.marque} ${comboOffer.vehicle.modele}` : 'N/A') }}
                </dd>
              </div>
              <div v-if="comboOffer.vehicle.marque || comboOffer.vehicle.brand" class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Marque</dt>
                  <dd class="mt-1 text-sm text-slate-900 break-words">
                    {{ comboOffer.vehicle.marque || comboOffer.vehicle.brand }}
                  </dd>
                </div>
                <div v-if="comboOffer.vehicle.modele || comboOffer.vehicle.model" class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Modèle</dt>
                  <dd class="mt-1 text-sm text-slate-900 break-words">
                    {{ comboOffer.vehicle.modele || comboOffer.vehicle.model }}
                  </dd>
                </div>
              </div>
              <div v-if="comboOffer.vehicle.prixParJour || comboOffer.vehicle.pricePerDay" class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Prix par jour</dt>
                  <dd class="mt-1 text-sm font-semibold text-slate-900 break-words">
                    {{ formatPrice(comboOffer.vehicle.prixParJour || comboOffer.vehicle.pricePerDay || 0) }} CFA
                  </dd>
                </div>
                <div v-if="comboOffer.vehicle.places || comboOffer.vehicle.seats" class="min-w-0">
                  <dt class="text-sm font-medium text-slate-500">Places</dt>
                  <dd class="mt-1 text-sm text-slate-900 break-words">
                    {{ comboOffer.vehicle.places || comboOffer.vehicle.seats }}
                  </dd>
                </div>
              </div>
            </dl>
          </div>
          <div v-else class="text-center py-8 text-slate-500">
            Véhicule non disponible
          </div>
        </section>

        <!-- 7. Réservations liées -->
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
            <p class="text-slate-500">Aucune réservation pour cette offre</p>
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
                      :class="getBookingStatusClass(booking.status)"
                    >
                      {{ booking.status }}
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

        <!-- 8. Gestion des disponibilités -->
        <section class="bg-white border border-slate-200 rounded-2xl p-4">
          <div class="mb-3">
            <h2 class="text-base font-semibold text-slate-900">Disponibilités</h2>
            <p class="text-xs text-slate-500 mt-1">Cliquez sur une date pour la bloquer/débloquer</p>
          </div>
          <AvailabilityCalendar 
            :bookings="bookingsForCalendar"
            :property-id="comboOffer?.id"
            property-type="offer"
            :blocked-dates="comboOffer?.blockedDates || []"
            :editable="true"
          />
        </section>
      </div>

      <!-- Colonne latérale -->
      <div class="space-y-6">
        <!-- Actions rapides -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Actions rapides</h2>
          <div class="space-y-3">
            <Link
              v-if="comboOffer?.canEdit !== false"
              :href="`/owner/combo-offers/${comboOffer?.id}/edit`"
              class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2"
            >
              <Pencil class="w-4 h-4" />
              Modifier l'offre
            </Link>
            <button
              v-if="comboOffer?.canEdit !== false"
              @click="confirmDelete"
              class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center justify-center gap-2"
            >
              <Trash2 class="w-4 h-4" />
              Supprimer l'offre
            </button>
          </div>
        </section>
      </div>
    </div>

    <!-- Modal pour afficher l'image en grand -->
    <div
      v-if="modalImage"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 touch-manipulation"
      role="dialog"
      aria-modal="true"
      aria-label="Image agrandie"
    >
      <div class="relative max-w-7xl max-h-full" @click.stop>
        <button
          type="button"
          @click="closeImageModal"
          class="absolute -top-10 right-0 min-h-[44px] min-w-[44px] flex items-center justify-center text-white hover:text-slate-300 transition-colors touch-manipulation"
          aria-label="Fermer"
        >
          <X class="w-8 h-8" />
        </button>
        <img
          :src="modalImage"
          :alt="modalImageAlt"
          class="max-w-full max-h-[90vh] object-contain rounded-lg"
        />
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-sm bg-black/50 px-4 py-2 rounded">
          {{ modalImageIndex + 1 }} / {{ modalImageTotal }}
        </div>
        <button
          type="button"
          v-if="modalImageIndex > 0"
          @click.stop="previousImage"
          class="absolute left-4 top-1/2 transform -translate-y-1/2 min-h-[44px] min-w-[44px] flex items-center justify-center bg-black/50 hover:bg-black/70 text-white p-2 rounded-full transition-colors touch-manipulation"
          aria-label="Image précédente"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <button
          type="button"
          v-if="modalImageIndex < modalImageTotal - 1"
          @click.stop="nextImage"
          class="absolute right-4 top-1/2 transform -translate-y-1/2 min-h-[44px] min-w-[44px] flex items-center justify-center bg-black/50 hover:bg-black/70 text-white p-2 rounded-full transition-colors touch-manipulation"
          aria-label="Image suivante"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showDeleteModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Confirmer la suppression</h3>
        <p class="text-slate-600 mb-6">
          Êtes-vous sûr de vouloir supprimer l'offre combinée
          <strong>{{ comboOffer?.title || comboOffer?.name || 'cette offre' }}</strong> ?
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
            @click="deleteOffer"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';
import AvailabilityCalendar from '../../../Components/AvailabilityCalendar.vue';
import { getStorageImageUrl } from '../../../utils/imageUrl';
import {
  ArrowLeft,
  Pencil,
  MoreVertical,
  Calendar,
  DollarSign,
  Star,
  TrendingUp,
  ImageIcon,
  Trash2,
  X,
} from 'lucide-vue-next';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  comboOffer: {
    id: number | string;
    title?: string;
    name?: string;
    description?: string;
    residenceId?: string;
    residence?: any;
    vehicleId?: string;
    vehicle?: any;
    price?: number;
    originalPrice?: number;
    discountedPrice?: number;
    discount?: number;
    discountPercentage?: number;
    nbJours?: number;
    startDate?: string;
    endDate?: string;
    validFrom?: string;
    validTo?: string;
    imageUrl?: string;
    images?: string[];
    isActive?: boolean;
    status?: string;
    canEdit?: boolean;
    blockedDates?: string[];
  };
  stats?: {
    totalBookings: number;
    confirmedBookings: number;
    totalRevenue: number;
    conversionRate: number;
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
  }>;
}>();

const showActionsMenu = ref(false);
const showDeleteModal = ref(false);

// Toggle menu actions
const toggleActionsMenu = () => {
  showActionsMenu.value = !showActionsMenu.value;
};

// Fermer le menu au clic extérieur
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.relative')) {
    showActionsMenu.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Gestion des images
const imageErrors = ref<Record<number, boolean>>({});
const modalImage = ref<string | null>(null);
const modalImageAlt = ref<string>('');
const modalImageIndex = ref<number>(0);
const modalImageTotal = ref<number>(0);
const modalImageList = ref<string[]>([]);

const allImages = computed(() => {
  const images = [];
  
  // Images de l'offre
  if (props.comboOffer?.images && Array.isArray(props.comboOffer.images)) {
    images.push(...props.comboOffer.images.filter(img => img && typeof img === 'string'));
  }
  if (props.comboOffer?.imageUrl && !images.includes(props.comboOffer.imageUrl)) {
    images.push(props.comboOffer.imageUrl);
  }
  
  // Images de la résidence
  if (props.comboOffer?.residence) {
    const residence = props.comboOffer.residence;
    if (residence.images && Array.isArray(residence.images)) {
      residence.images.forEach(img => {
        if (img && typeof img === 'string' && !images.includes(img)) {
          images.push(img);
        }
      });
    }
    if (residence.imageUrl && !images.includes(residence.imageUrl)) {
      images.push(residence.imageUrl);
    }
  }
  
  // Images du véhicule
  if (props.comboOffer?.vehicle) {
    const vehicle = props.comboOffer.vehicle;
    if (vehicle.images && Array.isArray(vehicle.images)) {
      vehicle.images.forEach(img => {
        if (img && typeof img === 'string' && !images.includes(img)) {
          images.push(img);
        }
      });
    }
    if (vehicle.imageUrl && !images.includes(vehicle.imageUrl)) {
      images.push(vehicle.imageUrl);
    }
  }
  
  return images;
});

const handleImageError = (index: number) => {
  imageErrors.value[index] = true;
};

const openImageModal = (image: string, index: number) => {
  const validList = allImages.value.filter((_, idx) => !imageErrors.value[idx]);
  modalImageList.value = validList.map((img) => getStorageImageUrl(img));
  modalImageAlt.value = `Image ${index + 1}`;
  const rawIndex = validList.indexOf(image);
  modalImageIndex.value = rawIndex >= 0 ? rawIndex : 0;
  modalImageTotal.value = modalImageList.value.length;
  modalImage.value = modalImageList.value[modalImageIndex.value] || getStorageImageUrl(image);
};

const closeImageModal = () => {
  modalImage.value = null;
  modalImageList.value = [];
  modalImageIndex.value = 0;
  modalImageTotal.value = 0;
};

const previousImage = () => {
  if (modalImageIndex.value > 0) {
    modalImageIndex.value--;
    modalImage.value = modalImageList.value[modalImageIndex.value];
  }
};

const nextImage = () => {
  if (modalImageIndex.value < modalImageTotal.value - 1) {
    modalImageIndex.value++;
    modalImage.value = modalImageList.value[modalImageIndex.value];
  }
};

// Gestion du clavier pour naviguer dans la modal
const handleKeydown = (event: KeyboardEvent) => {
  if (!modalImage.value) return;
  
  if (event.key === 'Escape') {
    closeImageModal();
  } else if (event.key === 'ArrowLeft') {
    previousImage();
  } else if (event.key === 'ArrowRight') {
    nextImage();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});

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

// Gestion de la suppression
const confirmDelete = () => {
  showDeleteModal.value = true;
  showActionsMenu.value = false;
};

const deleteOffer = () => {
  if (!props.comboOffer?.id) return;
  
  router.delete(`/owner/combo-offers/${props.comboOffer.id}`, {
    onSuccess: () => {
      showDeleteModal.value = false;
    },
    onError: () => {
      showDeleteModal.value = false;
    },
  });
};

// Formatage
const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('fr-FR').format(num);
};

const formatDate = (date: string | null | undefined): string => {
  if (!date) return 'N/A';
  try {
    const d = new Date(date);
    return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
  } catch {
    return date;
  }
};

const getStatusClass = (status: string | boolean): string => {
  if (typeof status === 'boolean') {
    return status
      ? 'bg-emerald-100 text-emerald-700'
      : 'bg-red-100 text-red-700';
  }

  const statusLower = status.toLowerCase();
  if (statusLower === 'active' || statusLower === 'actif') {
    return 'bg-emerald-100 text-emerald-700';
  }
  return 'bg-red-100 text-red-700';
};

const getStatusLabel = (status: string | boolean): string => {
  if (typeof status === 'boolean') {
    return status ? 'Active' : 'Inactive';
  }

  const statusMap: Record<string, string> = {
    active: 'Active',
    actif: 'Active',
    inactive: 'Inactive',
    inactif: 'Inactive',
  };

  return statusMap[status.toLowerCase()] || status;
};

const getBookingStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'confirmée' || statusLower === 'confirmed') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'annulée' || statusLower === 'cancelled') {
    return 'bg-red-100 text-red-700';
  } else if (statusLower === 'terminée' || statusLower === 'completed') {
    return 'bg-blue-100 text-blue-700';
  }
  return 'bg-amber-100 text-amber-700';
};
</script>
