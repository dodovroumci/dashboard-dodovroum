<template>
  <div class="space-y-6">
    <!-- Lien de retour -->
    <Link
      :href="route('admin.vehicles.index')"
      class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors min-w-0"
    >
      <ArrowLeft class="w-4 h-4 shrink-0" />
      <span class="truncate">Retour à la liste des véhicules</span>
    </Link>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- 1. En-tête du véhicule -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 overflow-hidden">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between min-w-0">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
            <h1 class="text-xl sm:text-3xl font-bold text-slate-900 truncate min-w-0">
              {{ getVehicleName() }}
            </h1>
            <span
              class="px-3 py-1 text-sm font-medium rounded-full shrink-0"
              :class="getStatusClass(vehicle?.status || vehicle?.isAvailable || vehicle?.available)"
            >
              {{ getStatusLabel(vehicle?.status || vehicle?.isAvailable || vehicle?.available) }}
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-slate-600 min-w-0">
            <div class="flex items-center gap-2 flex-wrap min-w-0">
              <span class="text-sm">{{ vehicle?.brand || vehicle?.marque || 'Marque' }}</span>
              <span class="text-slate-400">•</span>
              <span class="text-sm">{{ vehicle?.model || vehicle?.modele || 'Modèle' }}</span>
              <span class="text-slate-400">•</span>
              <span class="text-sm">{{ vehicle?.year || vehicle?.annee || 'Année' }}</span>
            </div>
            <div v-if="vehicle?.ownerName || vehicle?.owner" class="flex items-center gap-2 min-w-0 truncate">
              <User class="w-4 h-4 shrink-0" />
              <span class="font-medium text-slate-700 truncate">{{ vehicle?.ownerName || getOwnerName() }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2 relative shrink-0 flex-wrap">
          <Link
            :href="route('admin.vehicles.edit', vehicle?.id)"
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
                :href="`/vehicles/${vehicle?.id}`"
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
                {{ vehicle?.isAvailable || vehicle?.available ? 'Désactiver' : 'Activer' }}
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
          :href="route('admin.vehicles.edit', vehicle?.id)"
          class="text-sm text-blue-600 hover:text-blue-700 font-medium"
        >
          Modifier les photos →
        </Link>
      </div>
      <div v-if="vehicle?.images && vehicle.images.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div
          v-for="(image, index) in vehicle.images.slice(0, 5)"
          :key="`image-${index}-${image}`"
          role="button"
          tabindex="0"
          class="aspect-video bg-slate-200 rounded-lg overflow-hidden cursor-pointer relative group touch-manipulation"
          @click="openImageModal(getImageUrl(image))"
          @keydown.enter.prevent="openImageModal(getImageUrl(image))"
          @keydown.space.prevent="openImageModal(getImageUrl(image))"
        >
          <img
            v-if="!imageErrors[index] && image"
            :src="getImageUrl(image)"
            :alt="`Image ${index + 1}`"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform"
            @error="() => handleImageError(index)"
            @load="() => console.log('✅ Image chargée avec succès', { index, url: getImageUrl(image) })"
          />
          <div v-else class="w-full h-full flex flex-col items-center justify-center p-2">
            <ImageIcon class="w-8 h-8 text-slate-400 mb-2" />
            <p class="text-xs text-slate-500 text-center px-2 break-all" :title="image">
              {{ image ? (image.length > 30 ? image.substring(0, 30) + '...' : image) : 'URL invalide' }}
            </p>
          </div>
          <div v-if="index === 0 && !imageErrors[index]" class="absolute top-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
            Principale
          </div>
        </div>
      </div>
      <div v-else class="aspect-video bg-slate-200 rounded-lg flex items-center justify-center">
        <div class="text-center">
          <ImageIcon class="w-12 h-12 text-slate-400 mx-auto mb-2" />
          <p class="text-slate-500">Aucune image disponible</p>
          <Link
            :href="route('admin.vehicles.edit', vehicle?.id)"
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
        <!-- 4. Informations du véhicule -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Informations du véhicule</h2>
            <Link
              :href="route('admin.vehicles.edit', vehicle?.id)"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              Modifier les informations →
            </Link>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-slate-500 mb-1">Type de véhicule</p>
              <p class="font-medium text-slate-900">{{ formatType(vehicle?.type) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Prix / jour</p>
              <p class="font-medium text-slate-900">
                {{ formatPrice(vehicle?.pricePerDay || vehicle?.price_per_day || vehicle?.price || 0) }} CFA
              </p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Capacité</p>
              <p class="font-medium text-slate-900">
                {{ vehicle?.seats || vehicle?.places || 0 }} places
              </p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Année</p>
              <p class="font-medium text-slate-900">
                {{ vehicle?.year || vehicle?.annee || 'N/A' }}
              </p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Transmission</p>
              <p class="font-medium text-slate-900">
                {{ formatTransmission(vehicle?.transmission) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Carburant</p>
              <p class="font-medium text-slate-900">
                {{ formatFuel(vehicle?.fuel || vehicle?.carburant || vehicle?.fuelType) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Couleur</p>
              <p class="font-medium text-slate-900">
                {{ vehicle?.color || vehicle?.couleur || 'N/A' }}
              </p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Numéro de plaque</p>
              <p class="font-medium text-slate-900">
                {{ vehicle?.plateNumber || vehicle?.plate_number || 'N/A' }}
              </p>
            </div>
            <div v-if="vehicle?.mileage || vehicle?.kilometrage">
              <p class="text-sm text-slate-500 mb-1">Kilométrage</p>
              <p class="font-medium text-slate-900">
                {{ formatPrice(vehicle.mileage || vehicle.kilometrage || 0) }} km
              </p>
            </div>
          </div>
          <div v-if="vehicle?.description" class="mt-4 pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500 mb-2">Description</p>
            <p class="text-slate-700 whitespace-pre-line">{{ vehicle.description }}</p>
          </div>
          <div v-if="vehicle?.features && vehicle.features.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500 mb-2">Caractéristiques</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="(feature, index) in vehicle.features"
                :key="index"
                class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-sm"
              >
                {{ feature }}
              </span>
            </div>
          </div>
        </section>

        <!-- 6. Réservations liées -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Réservations liées</h2>
            <Link
              :href="route('admin.bookings.index')"
              class="text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              Voir toutes →
            </Link>
          </div>
          <div v-if="!bookings || bookings.length === 0" class="text-center py-8">
            <Calendar class="w-12 h-12 text-slate-300 mx-auto mb-2" />
            <p class="text-slate-500">Aucune réservation pour ce véhicule</p>
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
                      :href="route('admin.bookings.show', booking.id)"
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
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-slate-900">Disponibilités</h2>
            <button class="text-xs text-blue-600 hover:text-blue-700 font-medium">
              Modifier →
            </button>
          </div>
          <AvailabilityCalendar :bookings="bookingsForCalendar" />
        </section>

        <!-- Informations techniques -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Informations techniques</h2>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-slate-500 mb-1">Marque</p>
              <p class="text-sm font-medium text-slate-900">{{ vehicle?.brand || vehicle?.marque || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Modèle</p>
              <p class="text-sm font-medium text-slate-900">{{ vehicle?.model || vehicle?.modele || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Année</p>
              <p class="text-sm font-medium text-slate-900">{{ vehicle?.year || vehicle?.annee || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Transmission</p>
              <p class="text-sm font-medium text-slate-900">{{ formatTransmission(vehicle?.transmission) }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500 mb-1">Carburant</p>
              <p class="text-sm font-medium text-slate-900">{{ formatFuel(vehicle?.fuel || vehicle?.carburant || vehicle?.fuelType) }}</p>
            </div>
            <div v-if="vehicle?.mileage || vehicle?.kilometrage">
              <p class="text-sm text-slate-500 mb-1">Kilométrage</p>
              <p class="text-sm font-medium text-slate-900">
                {{ formatPrice(vehicle.mileage || vehicle.kilometrage || 0) }} km
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
          {{ vehicle?.isAvailable || vehicle?.available ? 'Désactiver le véhicule' : 'Activer le véhicule' }}
        </button>
        <button
          @click="confirmDelete"
          class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center justify-center gap-2"
        >
          <Trash2 class="w-4 h-4" />
          Supprimer le véhicule
        </button>
      </div>
    </section>

    <!-- Modal de confirmation de suppression - Téléporté directement dans <body> -->
    <Teleport to="body">
      <div
        v-if="showDeleteModal"
        id="modal-suppression-vehicle-show-final"
        class="fixed inset-0 z-[999999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
        @click.self="showDeleteModal = false"
        style="pointer-events: auto !important;"
      >
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all scale-100 border border-slate-100">
          <div class="mb-6 text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900">Supprimer ce véhicule ?</h3>
            
            <!-- Message d'avertissement si des réservations existent -->
            <div v-if="vehicleHasBookings" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-amber-800">Attention !</p>
                  <p class="text-sm text-amber-700 mt-1">{{ vehicleBookingsMessage || `Ce véhicule a ${vehicleBookingsCount} réservation(s) liée(s).` }}</p>
                  <p class="text-sm text-amber-700 mt-1">La suppression n'est pas possible tant que des réservations sont actives.</p>
                  
                  <!-- Liste des réservations -->
                  <div v-if="vehicleBookingsList.length > 0" class="mt-3 space-y-2">
                    <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide">Réservations actives :</p>
                    <div class="space-y-1 max-h-40 overflow-y-auto">
                      <div 
                        v-for="booking in vehicleBookingsList" 
                        :key="booking.id"
                        class="text-xs bg-white/60 rounded p-2 border border-amber-200"
                      >
                        <div class="flex items-center justify-between">
                          <span class="font-medium text-amber-900">{{ booking.customer }}</span>
                          <span 
                            class="px-2 py-0.5 rounded text-xs font-medium"
                            :class="{
                              'bg-emerald-100 text-emerald-700': booking.status?.toLowerCase() === 'confirmed' || booking.status?.toLowerCase() === 'confirmee',
                              'bg-amber-100 text-amber-700': booking.status?.toLowerCase() === 'pending' || booking.status?.toLowerCase() === 'en attente',
                              'bg-red-100 text-red-700': booking.status?.toLowerCase() === 'cancelled' || booking.status?.toLowerCase() === 'annulee',
                              'bg-slate-100 text-slate-700': !booking.status
                            }"
                          >
                            {{ booking.status || 'Inconnu' }}
                          </span>
                        </div>
                        <div class="text-amber-700 mt-1">
                          <span v-if="booking.startDate && booking.endDate">
                            {{ formatDateRange(booking.startDate, booking.endDate) }}
                          </span>
                          <span v-else class="text-amber-600 italic">Dates non disponibles</span>
                        </div>
                      </div>
                    </div>
                    <Link
                      :href="route('admin.bookings.index')"
                      class="inline-flex items-center gap-1 text-xs text-amber-700 hover:text-amber-800 font-medium mt-2"
                    >
                      Voir toutes les réservations →
                    </Link>
                  </div>
                </div>
              </div>
            </div>
            
            <p v-else class="text-slate-500 mt-2">
              Êtes-vous sûr de vouloir supprimer le véhicule
              <strong class="text-slate-900">{{ getVehicleName() }}</strong> ?
              Cette action est irréversible. Le bolide sera retiré du garage.
            </p>
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click.stop="showDeleteModal = false"
              class="flex-1 py-3 px-4 rounded-xl bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200 transition-all"
            >
              Annuler
            </button>
            <button
              type="button"
              @click.stop.prevent="deleteVehicle"
              :disabled="processing || vehicleHasBookings || checkingBookings"
              class="flex-1 py-3 px-4 rounded-xl bg-red-600 text-white font-semibold shadow-lg shadow-red-200 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2"
            >
              <span v-if="checkingBookings">Vérification...</span>
              <span v-else-if="processing">Suppression...</span>
              <span v-else-if="vehicleHasBookings">Impossible</span>
              <span v-else>Confirmer</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

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
import { ref, computed, onMounted } from 'vue';
import { Teleport } from 'vue';
import AdminLayout from '../../Components/Layouts/AdminLayout.vue';
import AvailabilityCalendar from '../../Components/AvailabilityCalendar.vue';
import { getStorageImageUrl } from '../../utils/imageUrl';
import {
  ArrowLeft,
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
  User,
} from 'lucide-vue-next';

defineOptions({
  layout: AdminLayout,
});

const props = defineProps<{
  vehicle?: {
    id: number | string;
    name?: string;
    brand?: string;
    marque?: string;
    model?: string;
    modele?: string;
    year?: number;
    annee?: number;
    type?: string;
    seats?: number;
    places?: number;
    plateNumber?: string;
    plate_number?: string;
    pricePerDay?: number;
    price_per_day?: number;
    price?: number;
    color?: string;
    couleur?: string;
    transmission?: string;
    fuel?: string;
    carburant?: string;
    fuelType?: string;
    mileage?: number;
    kilometrage?: number;
    description?: string;
    images?: string[];
    features?: string[];
    isAvailable?: boolean;
    available?: boolean;
    status?: string;
    canEdit?: boolean;
    owner?: {
      id?: string;
      name?: string;
      nom?: string;
      email?: string;
    };
    ownerName?: string;
    proprietaireId?: string;
    ownerId?: string;
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
  }>;
}>();

const showActionsMenu = ref(false);
const showDeleteModal = ref(false);
const vehicleHasBookings = ref(false);
const vehicleBookingsCount = ref(0);
const vehicleBookingsMessage = ref<string | null>(null);
const vehicleBookingsList = ref<Array<{
  id: string | number;
  customer: string;
  startDate: string | null;
  endDate: string | null;
  status: string;
  totalPrice: number;
}>>([]);
const checkingBookings = ref(false);
const processing = ref(false);
const selectedImage = ref<string | null>(null);
const imageErrors = ref<Record<number, boolean>>({});

const toggleActionsMenu = () => {
  showActionsMenu.value = !showActionsMenu.value;
};

const toggleDisable = () => {
  // TODO: Implémenter la désactivation
  alert('Fonctionnalité à implémenter');
  showActionsMenu.value = false;
};

const confirmDelete = async () => {
  if (!props.vehicle?.id) {
    console.error('❌ Véhicule invalide', { vehicle: props.vehicle });
    return;
  }
  
  // Vérifier les réservations avant d'afficher le modal
  checkingBookings.value = true;
  vehicleHasBookings.value = false;
  vehicleBookingsCount.value = 0;
  vehicleBookingsMessage.value = null;
  
  try {
    const response = await fetch(route('admin.vehicles.check-bookings', props.vehicle.id));
    const data = await response.json();
    
    vehicleHasBookings.value = data.hasBookings || false;
    vehicleBookingsCount.value = data.bookingsCount || 0;
    vehicleBookingsMessage.value = data.message || null;
    vehicleBookingsList.value = data.bookings || [];
    
    console.log('🔵 Vérification réservations', {
      hasBookings: vehicleHasBookings.value,
      count: vehicleBookingsCount.value,
      message: vehicleBookingsMessage.value,
      bookings: vehicleBookingsList.value,
    });
  } catch (error) {
    console.error('❌ Erreur lors de la vérification des réservations', error);
    // En cas d'erreur, on continue quand même (on ne bloque pas)
  } finally {
    checkingBookings.value = false;
  }
  
  showDeleteModal.value = true;
  showActionsMenu.value = false;
};

const deleteVehicle = () => {
  if (!props.vehicle?.id || processing.value) {
    console.warn('⚠️ Suppression impossible : véhicule non défini ou déjà en cours');
    return;
  }

  const vehicleId = props.vehicle.id;
  
  if (!vehicleId) {
    console.error('❌ Aucun ID de véhicule', { vehicle: props.vehicle });
    alert('Erreur : Aucun ID de véhicule trouvé. Veuillez réessayer.');
    return;
  }

  router.delete(route('admin.vehicles.destroy', vehicleId), {
    onBefore: () => {
      processing.value = true;
      console.log('🚀 Requête DELETE envoyée pour ID:', vehicleId);
    },
    onSuccess: (page) => {
      showDeleteModal.value = false;
      processing.value = false;
      
      // Vérifier si un message d'erreur est présent dans les flash messages
      if (page.props.flash?.error) {
        console.error('❌ Échec de la suppression:', page.props.flash.error);
        // Le message d'erreur sera affiché automatiquement par le template
      } else {
        console.log('✅ Véhicule supprimé avec succès');
        // Rediriger vers la liste après succès
        router.visit(route('admin.vehicles.index'));
      }
    },
    onError: (errors) => {
      processing.value = false;
      showDeleteModal.value = false;
      console.error('❌ Échec de la suppression:', errors);
    },
    preserveScroll: true
  });
};

const openImageModal = (image: string) => {
  selectedImage.value = image;
};

const getVehicleName = (): string => {
  if (props.vehicle?.name) {
    return props.vehicle.name;
  }
  const brand = props.vehicle?.brand || props.vehicle?.marque || '';
  const model = props.vehicle?.model || props.vehicle?.modele || '';
  const year = props.vehicle?.year || props.vehicle?.annee;
  if (brand || model) {
    return `${brand} ${model}${year ? ` ${year}` : ''}`.trim();
  }
  return 'Véhicule sans nom';
};

const getOwnerName = (): string => {
  if (props.vehicle?.ownerName) {
    return props.vehicle.ownerName;
  }
  if (props.vehicle?.owner) {
    const owner = props.vehicle.owner;
    return owner.name || owner.nom || owner.email || 'Propriétaire inconnu';
  }
  return 'Propriétaire non spécifié';
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatDateRange = (startDate: string, endDate: string): string => {
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const startFormatted = start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    const endFormatted = end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
    return `${startFormatted} - ${endFormatted}`;
  } catch {
    return `${startDate} - ${endDate}`;
  }
};

const formatNumber = (value: number): string => {
  return new Intl.NumberFormat('fr-FR').format(value);
};

const formatType = (type: string): string => {
  const typeMap: Record<string, string> = {
    car: 'Voiture',
    berline: 'Berline',
    suv: 'SUV',
    '4x4': '4x4',
    utilitaire: 'Utilitaire',
    moto: 'Moto',
    scooter: 'Scooter',
    sedan: 'Berline',
    truck: 'Camion',
  };
  return typeMap[type?.toLowerCase()] || type || 'N/A';
};

const formatTransmission = (transmission: string): string => {
  if (!transmission) return 'N/A';
  const transmissionMap: Record<string, string> = {
    manual: 'Manuelle',
    automatic: 'Automatique',
    manuelle: 'Manuelle',
    automatique: 'Automatique',
  };
  return transmissionMap[transmission.toLowerCase()] || transmission;
};

const formatFuel = (fuel: string): string => {
  if (!fuel) return 'N/A';
   const fuelMap: Record<string, string> = {
    petrol: 'Essence',
    gasoline: 'Essence',
    diesel: 'Diesel',
    electric: 'Électrique',
    hybrid: 'Hybride',
    essence: 'Essence',
    electrique: 'Électrique',
    hybride: 'Hybride',
  };
  return fuelMap[fuel.toLowerCase()] || fuel;
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
    return status ? 'Disponible' : 'Indisponible';
  }
  const statusMap: Record<string, string> = {
    active: 'Disponible',
    inactive: 'Indisponible',
    available: 'Disponible',
    unavailable: 'Indisponible',
    rented: 'En location',
    maintenance: 'Maintenance',
  };
  return statusMap[status.toLowerCase()] || status;
};

const getBookingStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'confirmée' || statusLower === 'confirmed') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'en attente' || statusLower === 'pending') {
    return 'bg-amber-100 text-amber-700';
  } else if (statusLower === 'annulée' || statusLower === 'cancelled') {
    return 'bg-red-100 text-red-700';
  } else if (statusLower === 'terminée' || statusLower === 'completed') {
    return 'bg-blue-100 text-blue-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const getImageUrl = (image: string | null | undefined): string =>
  getStorageImageUrl(image, 'vehicles');

const handleImageError = (index: number) => {
  const imageUrl = props.vehicle?.images?.[index];
  console.warn('❌ Erreur de chargement d\'image', {
    index,
    originalUrl: imageUrl,
    processedUrl: getImageUrl(imageUrl),
    allImages: props.vehicle?.images,
  });
  imageErrors.value[index] = true;
};

// Log pour déboguer les images au montage
onMounted(() => {
  console.log('🔍 Show.vue - Images du véhicule', {
    hasImages: !!props.vehicle?.images,
    imagesCount: props.vehicle?.images?.length || 0,
    images: props.vehicle?.images,
    vehicleId: props.vehicle?.id,
  });
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

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.vehicles.index': '/admin/vehicles',
    'admin.vehicles.edit': (id: string | number) => `/admin/vehicles/${id}/edit`,
    'admin.vehicles.destroy': (id: string | number) => `/admin/vehicles/${id}`,
    'admin.vehicles.check-bookings': (id: string | number) => `/admin/vehicles/${id}/check-bookings`,
    'admin.bookings.index': '/admin/bookings',
    'admin.bookings.show': (id: string | number) => `/admin/bookings/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};
</script>
