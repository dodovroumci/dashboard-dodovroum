<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between min-w-0">
      <div class="min-w-0 flex-1">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">Détails de l'utilisateur</h1>
        <p class="text-sm text-slate-500 mt-1 truncate">{{ user.name }}</p>
      </div>
      <div class="flex flex-wrap gap-2 sm:gap-3">
        <Link
          :href="route('admin.users.edit', user.id)"
          class="px-4 py-2.5 sm:py-2 min-h-[44px] flex items-center bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
        >
          Modifier
        </Link>
        <button
          @click="confirmDelete"
          class="px-4 py-2.5 sm:py-2 min-h-[44px] bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
        >
          Supprimer
        </button>
        <Link
          :href="route('admin.users.index')"
          class="px-4 py-2.5 sm:py-2 min-h-[44px] flex items-center border border-slate-300 rounded-lg hover:bg-slate-50 text-sm font-medium"
        >
          Retour à la liste
        </Link>
      </div>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Colonne principale -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Informations personnelles -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <h2 class="text-lg font-semibold mb-4">Informations personnelles</h2>
          <dl class="space-y-4">
            <div v-if="user.firstName || user.lastName">
              <dt class="text-sm font-medium text-slate-500">Prénom</dt>
              <dd class="mt-1 text-sm text-slate-900">{{ user.firstName || 'N/A' }}</dd>
            </div>
            <div v-if="user.firstName || user.lastName">
              <dt class="text-sm font-medium text-slate-500">Nom</dt>
              <dd class="mt-1 text-sm text-slate-900">{{ user.lastName || 'N/A' }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500">Nom complet</dt>
              <dd class="mt-1 text-sm text-slate-900 font-semibold">{{ user.name }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500">Email</dt>
              <dd class="mt-1 text-sm text-slate-900">
                <a :href="'mailto:' + user.email" class="text-blue-600 hover:text-blue-700">
                  {{ user.email }}
                </a>
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500">Téléphone</dt>
              <dd class="mt-1 text-sm text-slate-900">
                <a v-if="user.phone" :href="'tel:' + user.phone" class="text-blue-600 hover:text-blue-700">
                  {{ user.phone }}
                </a>
                <span v-else class="text-slate-400 italic">Non renseigné</span>
              </dd>
            </div>
            <div v-if="user.typeProprietaire && isProprietaire">
              <dt class="text-sm font-medium text-slate-500">Type de propriétaire</dt>
              <dd class="mt-1 text-sm text-slate-900">{{ user.typeProprietaire }}</dd>
            </div>
            <div v-if="user.localisation">
              <dt class="text-sm font-medium text-slate-500">Localisation</dt>
              <dd class="mt-1 text-sm text-slate-900">{{ user.localisation }}</dd>
            </div>
            <div v-if="user.address">
              <dt class="text-sm font-medium text-slate-500">Adresse</dt>
              <dd class="mt-1 text-sm text-slate-900">{{ user.address }}</dd>
            </div>
            <div v-if="user.city || user.country">
              <dt class="text-sm font-medium text-slate-500">Ville / Pays</dt>
              <dd class="mt-1 text-sm text-slate-900">
                {{ [user.city, user.country].filter(Boolean).join(', ') || 'N/A' }}
              </dd>
            </div>
          </dl>
        </div>

        <!-- Résidences du propriétaire -->
        <div v-if="isProprietaire" class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Résidences ({{ user.residences?.length || 0 }})</h2>
          </div>
          <div v-if="user.residences && user.residences.length > 0" class="space-y-4">
            <div
              v-for="residence in user.residences"
              :key="residence.id"
              class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50 transition-colors"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h3 class="font-semibold text-slate-900 mb-2">
                    {{ residence.title || residence.titre || 'Résidence sans titre' }}
                  </h3>
                  <div class="grid grid-cols-2 gap-2 text-sm text-slate-600">
                    <div v-if="residence.address || residence.adresse">
                      <span class="font-medium">Adresse:</span> {{ residence.address || residence.adresse }}
                    </div>
                    <div v-if="residence.city || residence.ville">
                      <span class="font-medium">Ville:</span> {{ residence.city || residence.ville }}
                    </div>
                    <div v-if="residence.pricePerDay || residence.price_per_day">
                      <span class="font-medium">Prix/jour:</span> {{ formatPrice(residence.pricePerDay || residence.price_per_day) }} FCFA
                    </div>
                    <div v-if="residence.capacity || residence.capacite">
                      <span class="font-medium">Capacité:</span> {{ residence.capacity || residence.capacite }} personnes
                    </div>
                    <div v-if="residence.bedrooms || residence.chambres">
                      <span class="font-medium">Chambres:</span> {{ residence.bedrooms || residence.chambres }}
                    </div>
                    <div v-if="residence.bathrooms || residence.salles_de_bain">
                      <span class="font-medium">Salles de bain:</span> {{ residence.bathrooms || residence.salles_de_bain }}
                    </div>
                  </div>
                  <div v-if="residence._count" class="mt-3 flex gap-4 text-xs text-slate-500">
                    <span v-if="residence._count.bookings !== undefined">
                      {{ residence._count.bookings }} réservation(s)
                    </span>
                    <span v-if="residence._count.reviews !== undefined">
                      {{ residence._count.reviews }} avis
                    </span>
                    <span v-if="residence._count.favorites !== undefined">
                      {{ residence._count.favorites }} favori(s)
                    </span>
                  </div>
                </div>
                <div class="ml-4 flex flex-col gap-2">
                  <span
                    v-if="residence.isVerified !== undefined"
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="residence.isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-yellow-100 text-yellow-800'"
                  >
                    {{ residence.isVerified ? 'Vérifiée' : 'Non vérifiée' }}
                  </span>
                  <Link
                    v-if="residence.id"
                    :href="route('admin.residences.show', residence.id)"
                    class="text-xs text-blue-600 hover:text-blue-700"
                  >
                    Voir détails →
                  </Link>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-slate-500 text-center py-8">
            Aucune résidence enregistrée
          </div>
        </div>

        <!-- Véhicules du propriétaire -->
        <div v-if="isProprietaire" class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Véhicules ({{ user.vehicles?.length || 0 }})</h2>
          </div>
          <div v-if="user.vehicles && user.vehicles.length > 0" class="space-y-4">
            <div
              v-for="vehicle in user.vehicles"
              :key="vehicle.id"
              class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50 transition-colors"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h3 class="font-semibold text-slate-900 mb-2">
                    {{ getVehicleName(vehicle) }}
                  </h3>
                  <div class="grid grid-cols-2 gap-2 text-sm text-slate-600">
                    <div v-if="vehicle.brand || vehicle.marque">
                      <span class="font-medium">Marque:</span> {{ vehicle.brand || vehicle.marque }}
                    </div>
                    <div v-if="vehicle.model || vehicle.modele">
                      <span class="font-medium">Modèle:</span> {{ vehicle.model || vehicle.modele }}
                    </div>
                    <div v-if="vehicle.year || vehicle.annee">
                      <span class="font-medium">Année:</span> {{ vehicle.year || vehicle.annee }}
                    </div>
                    <div v-if="vehicle.type">
                      <span class="font-medium">Type:</span> {{ vehicle.type }}
                    </div>
                    <div v-if="vehicle.pricePerDay || vehicle.price_per_day">
                      <span class="font-medium">Prix/jour:</span> {{ formatPrice(vehicle.pricePerDay || vehicle.price_per_day) }} FCFA
                    </div>
                    <div v-if="vehicle.capacity || vehicle.capacite">
                      <span class="font-medium">Capacité:</span> {{ vehicle.capacity || vehicle.capacite }} personnes
                    </div>
                    <div v-if="vehicle.fuelType || vehicle.carburant">
                      <span class="font-medium">Carburant:</span> {{ vehicle.fuelType || vehicle.carburant }}
                    </div>
                    <div v-if="vehicle.transmission">
                      <span class="font-medium">Transmission:</span> {{ vehicle.transmission }}
                    </div>
                  </div>
                  <div v-if="vehicle._count" class="mt-3 flex gap-4 text-xs text-slate-500">
                    <span v-if="vehicle._count.bookings !== undefined">
                      {{ vehicle._count.bookings }} réservation(s)
                    </span>
                    <span v-if="vehicle._count.reviews !== undefined">
                      {{ vehicle._count.reviews }} avis
                    </span>
                    <span v-if="vehicle._count.favorites !== undefined">
                      {{ vehicle._count.favorites }} favori(s)
                    </span>
                  </div>
                </div>
                <div class="ml-4 flex flex-col gap-2">
                  <span
                    v-if="vehicle.isVerified !== undefined"
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="vehicle.isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-yellow-100 text-yellow-800'"
                  >
                    {{ vehicle.isVerified ? 'Vérifié' : 'Non vérifié' }}
                  </span>
                  <Link
                    v-if="vehicle.id"
                    :href="route('admin.vehicles.show', vehicle.id)"
                    class="text-xs text-blue-600 hover:text-blue-700"
                  >
                    Voir détails →
                  </Link>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-slate-500 text-center py-8">
            Aucun véhicule enregistré
          </div>
        </div>

        <!-- Vérification d'identité -->
        <div v-if="isProprietaire" class="bg-white border border-slate-200 rounded-xl p-6">
          <h2 class="text-lg font-semibold mb-4">Vérification d'identité</h2>
          
          <div v-if="user.identityVerification" class="space-y-6">
            <!-- Informations de vérification -->
            <dl class="space-y-4">
              <div>
                <dt class="text-sm font-medium text-slate-500">Statut</dt>
                <dd class="mt-1">
                  <span
                    class="px-3 py-1 text-sm font-medium rounded-full"
                    :class="getVerificationStatusClass(user.identityVerification.verificationStatus || user.identityVerification.verification_status)"
                  >
                    {{ formatVerificationStatus(user.identityVerification.verificationStatus || user.identityVerification.verification_status) }}
                  </span>
                </dd>
              </div>
              <div v-if="user.identityVerification.identityType || user.identityVerification.identity_type">
                <dt class="text-sm font-medium text-slate-500">Type de pièce</dt>
                <dd class="mt-1 text-sm text-slate-900">
                  {{ user.identityVerification.identityType || user.identityVerification.identity_type }}
                </dd>
              </div>
              <div v-if="user.identityVerification.identityNumber || user.identityVerification.identity_number">
                <dt class="text-sm font-medium text-slate-500">Numéro de pièce</dt>
                <dd class="mt-1 text-sm text-slate-900">
                  {{ user.identityVerification.identityNumber || user.identityVerification.identity_number }}
                </dd>
              </div>
              <div v-if="user.identityVerification.submittedAt || user.identityVerification.submitted_at">
                <dt class="text-sm font-medium text-slate-500">Date de soumission</dt>
                <dd class="mt-1 text-sm text-slate-900">
                  {{ formatDate(user.identityVerification.submittedAt || user.identityVerification.submitted_at) }}
                </dd>
              </div>
              <div v-if="user.identityVerification.verifiedAt || user.identityVerification.verified_at">
                <dt class="text-sm font-medium text-slate-500">Date de vérification</dt>
                <dd class="mt-1 text-sm text-slate-900">
                  {{ formatDate(user.identityVerification.verifiedAt || user.identityVerification.verified_at) }}
                </dd>
              </div>
              <div v-if="user.identityVerification.rejectionReason || user.identityVerification.rejection_reason">
                <dt class="text-sm font-medium text-slate-500">Raison du rejet</dt>
                <dd class="mt-1 text-sm text-red-600">
                  {{ user.identityVerification.rejectionReason || user.identityVerification.rejection_reason }}
                </dd>
              </div>
            </dl>

            <!-- Actions de vérification (si en attente) -->
            <div v-if="isPendingVerification" class="mt-6 pt-6 border-t border-slate-200">
              <h3 class="text-md font-semibold mb-3">Actions de vérification</h3>
              <div class="flex gap-3">
                <button
                  @click="approveIdentity"
                  :disabled="processing"
                  class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {{ processing ? 'Traitement...' : 'Approuver la vérification' }}
                </button>
                <button
                  @click="showRejectModal = true"
                  :disabled="processing"
                  class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Rejeter la vérification
                </button>
              </div>
            </div>

            <!-- Documents (photos) -->
            <div v-if="hasIdentityPhotos" class="mt-6">
              <h3 class="text-md font-semibold mb-3">Documents d'identité</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Photo recto -->
                <div v-if="getIdentityPhoto('front')" class="space-y-2">
                  <label class="text-sm font-medium text-slate-600">Recto</label>
                  <div class="relative group">
                    <img
                      :src="getStorageImageUrl(getIdentityPhoto('front'), 'residences')"
                      alt="Recto de la pièce d'identité"
                      class="w-full h-48 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-90 transition-opacity"
                      @click="openImageModal(getStorageImageUrl(getIdentityPhoto('front'), 'residences'), 'Recto de la pièce d\'identité')"
                      @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity rounded-lg flex items-center justify-center">
                      <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- Photo verso -->
                <div v-if="getIdentityPhoto('back')" class="space-y-2">
                  <label class="text-sm font-medium text-slate-600">Verso</label>
                  <div class="relative group">
                    <img
                      :src="getStorageImageUrl(getIdentityPhoto('back'), 'residences')"
                      alt="Verso de la pièce d'identité"
                      class="w-full h-48 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-90 transition-opacity"
                      @click="openImageModal(getStorageImageUrl(getIdentityPhoto('back'), 'residences'), 'Verso de la pièce d\'identité')"
                      @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity rounded-lg flex items-center justify-center">
                      <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- Photo supplémentaire -->
                <div v-if="getIdentityPhoto('extra')" class="space-y-2">
                  <label class="text-sm font-medium text-slate-600">Document supplémentaire</label>
                  <div class="relative group">
                    <img
                      :src="getStorageImageUrl(getIdentityPhoto('extra'), 'residences')"
                      alt="Document supplémentaire"
                      class="w-full h-48 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-90 transition-opacity"
                      @click="openImageModal(getStorageImageUrl(getIdentityPhoto('extra'), 'residences'), 'Document supplémentaire')"
                      @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity rounded-lg flex items-center justify-center">
                      <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Message si pas de vérification -->
          <div v-else class="space-y-4">
            <div class="text-sm text-slate-500 text-center py-4">
              <p>Aucune vérification d'identité enregistrée</p>
              <p class="text-xs mt-2">Le propriétaire n'a pas encore soumis de documents d'identité</p>
            </div>
            
            <!-- Actions de vérification même sans vérification -->
            <div v-if="isProprietaire" class="pt-4 border-t border-slate-200">
              <h3 class="text-md font-semibold mb-3">Actions de vérification</h3>
              <p class="text-sm text-slate-600 mb-3">
                Vous pouvez créer une vérification d'identité pour ce propriétaire.
              </p>
              <div class="flex gap-3">
                <button
                  @click="approveIdentity"
                  :disabled="processing"
                  class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {{ processing ? 'Traitement...' : 'Approuver (créer vérification)' }}
                </button>
                <button
                  @click="showRejectModal = true"
                  :disabled="processing"
                  class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Rejeter (créer vérification)
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Colonne latérale -->
      <div class="space-y-6">
        <!-- Avatar et statut -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex flex-col items-center">
            <div v-if="user.avatar" class="h-24 w-24 rounded-full mb-4 overflow-hidden">
              <img :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
            </div>
            <div v-else class="h-24 w-24 rounded-full bg-blue-100 flex items-center justify-center mb-4">
              <span class="text-blue-600 font-bold text-3xl">
                {{ user.name.charAt(0).toUpperCase() }}
              </span>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ user.name }}</h3>
            <span
              class="px-3 py-1 text-sm font-medium rounded-full mb-4"
              :class="getRoleClass(user.role)"
            >
              {{ formatRole(user.role) }}
            </span>
          </div>
        </div>

        <!-- Statut -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <h2 class="text-lg font-semibold mb-4">Statut du compte</h2>
          <div class="space-y-4">
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">Compte actif</dt>
              <dd>
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="user.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                >
                  {{ user.isActive ? 'Actif' : 'Inactif' }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-slate-500 mb-1">Compte vérifié</dt>
              <dd>
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="user.isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-yellow-100 text-yellow-800'"
                >
                  {{ user.isVerified ? 'Vérifié' : 'Non vérifié' }}
                </span>
              </dd>
            </div>
            <div v-if="user.createdAt">
              <dt class="text-sm font-medium text-slate-500 mb-1">Date de création</dt>
              <dd class="text-sm text-slate-900">{{ formatDate(user.createdAt) }}</dd>
            </div>
            <div v-if="user.updatedAt">
              <dt class="text-sm font-medium text-slate-500 mb-1">Dernière mise à jour</dt>
              <dd class="text-sm text-slate-900">{{ formatDate(user.updatedAt) }}</dd>
            </div>
          </div>
        </div>

        <!-- Statistiques (si propriétaire) -->
        <div v-if="isProprietaire" class="bg-white border border-slate-200 rounded-xl p-6">
          <h2 class="text-lg font-semibold mb-4">Statistiques</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-slate-600">Résidences</span>
              <span class="text-sm font-semibold text-slate-900">{{ user.residences?.length || 0 }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-slate-600">Véhicules</span>
              <span class="text-sm font-semibold text-slate-900">{{ user.vehicles?.length || 0 }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal pour afficher les images en grand -->
    <div
      v-if="imageModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"
      role="dialog"
      aria-modal="true"
      aria-label="Image agrandie"
    >
      <div class="relative max-w-4xl max-h-[90vh] p-4" @click.stop>
        <button
          @click="closeImageModal"
          class="absolute top-2 right-2 text-white hover:text-gray-300 z-10 bg-black bg-opacity-50 rounded-full p-2"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <img
          v-if="imageModal.src"
          :src="imageModal.src"
          :alt="imageModal.title"
          class="max-w-full max-h-[90vh] rounded-lg"
        />
        <p v-if="imageModal.title" class="text-white text-center mt-2">{{ imageModal.title }}</p>
      </div>
    </div>

    <!-- Modal de rejet de vérification -->
    <div
      v-if="showRejectModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      @click.self="showRejectModal = false"
    >
      <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Rejeter la vérification d'identité</h3>
        <p class="text-sm text-slate-600 mb-4">
          Veuillez indiquer la raison du rejet de cette vérification d'identité.
        </p>
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-700 mb-2">
            Raison du rejet *
          </label>
          <textarea
            v-model="rejectionReason"
            rows="4"
            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
            placeholder="Ex: Photo de mauvaise qualité, document illisible, informations manquantes..."
          ></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button
            @click="showRejectModal = false; rejectionReason = ''"
            class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
            :disabled="processing"
          >
            Annuler
          </button>
          <button
            @click="rejectIdentity"
            :disabled="processing || !rejectionReason.trim()"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ processing ? 'Traitement...' : 'Rejeter' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { getStorageImageUrl } from '../../utils/imageUrl';

const props = defineProps<{
  user: {
    id: string;
    firstName?: string;
    lastName?: string;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    isVerified: boolean;
    isActive: boolean;
    createdAt: string | null;
    updatedAt: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    localisation?: string | null;
    typeProprietaire?: string | null;
    avatar?: string | null;
    residences?: any[];
    vehicles?: any[];
    identityVerification?: any;
  };
}>();

const isProprietaire = computed(() => {
  const role = props.user.role?.toLowerCase() || '';
  return role === 'proprietaire' || role === 'owner' || role === 'propriétaire';
});

const formatRole = (role: string): string => {
  const roleMap: Record<string, string> = {
    admin: 'Admin',
    proprietaire: 'Propriétaire',
    owner: 'Propriétaire',
    user: 'Client',
  };
  return roleMap[role.toLowerCase()] || role;
};

const getRoleClass = (role: string): string => {
  const roleLower = role.toLowerCase();
  if (roleLower === 'admin') {
    return 'bg-purple-100 text-purple-800';
  } else if (roleLower === 'proprietaire' || roleLower === 'owner') {
    return 'bg-blue-100 text-blue-800';
  }
  return 'bg-slate-100 text-slate-800';
};

const formatDate = (dateString: string | null): string => {
  if (!dateString) return 'N/A';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return dateString;
  }
};

const formatPrice = (price: number): string => {
  if (!price) return '0';
  return new Intl.NumberFormat('fr-FR').format(price);
};

const getVehicleName = (vehicle: any): string => {
  const brand = vehicle.brand || vehicle.marque || '';
  const model = vehicle.model || vehicle.modele || '';
  const year = vehicle.year || vehicle.annee || '';
  
  const parts = [brand, model, year].filter(Boolean);
  return parts.length > 0 ? parts.join(' ') : vehicle.title || vehicle.titre || 'Véhicule sans nom';
};

const formatVerificationStatus = (status: string): string => {
  const statusMap: Record<string, string> = {
    pending: 'En attente',
    verified: 'Vérifié',
    rejected: 'Rejeté',
    approved: 'Approuvé',
  };
  return statusMap[status?.toLowerCase()] || status || 'Inconnu';
};

const getVerificationStatusClass = (status: string): string => {
  const statusLower = status?.toLowerCase() || '';
  if (statusLower === 'verified' || statusLower === 'approved') {
    return 'bg-emerald-100 text-emerald-800';
  } else if (statusLower === 'rejected') {
    return 'bg-red-100 text-red-800';
  }
  return 'bg-yellow-100 text-yellow-800';
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.users.index': '/admin/users',
    'admin.users.show': (id: string) => `/admin/users/${id}`,
    'admin.users.edit': (id: string) => `/admin/users/${id}/edit`,
    'admin.users.destroy': (id: string) => `/admin/users/${id}`,
    'admin.users.identity.approve': (id: string) => `/admin/users/${id}/identity/approve`,
    'admin.users.identity.reject': (id: string) => `/admin/users/${id}/identity/reject`,
    'admin.residences.show': (id: string) => `/admin/residences/${id}`,
    'admin.vehicles.show': (id: string) => `/admin/vehicles/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};

const confirmDelete = () => {
  if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
    router.delete(route('admin.users.destroy', props.user.id), {
      onSuccess: () => {
        // Redirection gérée par le contrôleur
      },
    });
  }
};

// Gestion des photos d'identité
const hasIdentityPhotos = computed(() => {
  if (!props.user.identityVerification) return false;
  const iv = props.user.identityVerification;
  return !!(
    iv.identityPhotoFront || iv.identity_photo_front ||
    iv.identityPhotoBack || iv.identity_photo_back ||
    iv.identityPhotoExtra || iv.identity_photo_extra
  );
});

const getIdentityPhoto = (side: 'front' | 'back' | 'extra'): string | null => {
  if (!props.user.identityVerification) return null;
  const iv = props.user.identityVerification;
  
  switch (side) {
    case 'front':
      return iv.identityPhotoFront || iv.identity_photo_front || null;
    case 'back':
      return iv.identityPhotoBack || iv.identity_photo_back || null;
    case 'extra':
      return iv.identityPhotoExtra || iv.identity_photo_extra || null;
    default:
      return null;
  }
};

// Modal pour agrandir les images
const imageModal = ref<{ open: boolean; src: string | null; title: string }>({
  open: false,
  src: null,
  title: '',
});

const openImageModal = (src: string, title: string) => {
  imageModal.value = {
    open: true,
    src,
    title,
  };
};

const closeImageModal = () => {
  imageModal.value = {
    open: false,
    src: null,
    title: '',
  };
};

// Gestion de la vérification d'identité
const isPendingVerification = computed(() => {
  // Si pas de vérification d'identité, on peut quand même vérifier si c'est un propriétaire
  if (!props.user.identityVerification) {
    return isProprietaire.value;
  }
  
  const status = props.user.identityVerification.verificationStatus || 
                 props.user.identityVerification.verification_status ||
                 props.user.identityVerification.status;
  
  // Si pas de statut, on peut vérifier si c'est un propriétaire
  if (!status) {
    return isProprietaire.value;
  }
  
  const statusLower = status.toString().toLowerCase().trim();
  
  // Vérifier plusieurs variantes possibles du statut "en attente"
  const isPending = statusLower === 'pending' || 
                    statusLower === 'en attente' ||
                    statusLower === 'waiting' ||
                    statusLower === 'en_attente' ||
                    statusLower === 'pending';
  
  // Si c'est en attente, on peut vérifier
  // Si c'est déjà vérifié ou rejeté, on ne peut plus modifier
  return isPending;
});

const showRejectModal = ref(false);
const rejectionReason = ref('');
const processing = ref(false);

const approveIdentity = () => {
  if (confirm('Êtes-vous sûr de vouloir approuver cette vérification d\'identité ?')) {
    processing.value = true;
    router.patch(route('admin.users.identity.approve', props.user.id), {}, {
      onFinish: () => {
        processing.value = false;
      },
    });
  }
};

const rejectIdentity = () => {
  if (!rejectionReason.value.trim()) {
    alert('Veuillez saisir une raison de rejet');
    return;
  }

  processing.value = true;
  const form = useForm({
    rejectionReason: rejectionReason.value,
  });

  form.patch(route('admin.users.identity.reject', props.user.id), {
    onFinish: () => {
      processing.value = false;
      showRejectModal.value = false;
      rejectionReason.value = '';
    },
  });
};
</script>
