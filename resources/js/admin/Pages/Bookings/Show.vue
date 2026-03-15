<template>
  <div class="space-y-6">
    <!-- Header avec navigation -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between min-w-0">
      <div class="flex items-center gap-3 sm:gap-4 min-w-0">
        <Link
          href="/admin/bookings"
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

    <!-- Hero Card avec gradient moderne (style Dribbble) -->
    <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl p-4 sm:p-8 text-white shadow-2xl transition-all duration-300 hover:shadow-3xl" 
         style="background: linear-gradient(135deg, rgb(26, 51, 101) 0%, rgb(37, 99, 235) 50%, rgb(59, 130, 246) 100%);">
      <!-- Pattern décoratif en arrière-plan -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white rounded-full blur-3xl"></div>
      </div>
      
      <div class="relative z-10 min-w-0">
        <div class="flex items-start justify-between mb-4 sm:mb-6 min-w-0">
          <div class="min-w-0 flex-1">
            <div class="mb-3 sm:mb-4">
              <span
                class="inline-flex items-center px-3 sm:px-4 py-1.5 sm:py-2 rounded-full text-xs sm:text-sm font-semibold uppercase backdrop-blur-sm bg-white/20 border border-white/30 shadow-lg"
                :class="getStatusBadgeClass(booking.status)"
              >
                {{ formatStatus(booking.status).toUpperCase() }}
              </span>
            </div>
            <h2 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold mb-2 sm:mb-3 tracking-tight truncate">
              #{{ booking.id?.substring(0, 8) || 'N/A' }}
            </h2>
            <p class="text-white/90 text-base sm:text-xl font-medium truncate">
              {{ formatDateRange(booking.startDate, booking.endDate) }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-3 sm:gap-8 pt-4 sm:pt-6 border-t border-white/20">
          <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm px-4 py-2.5 rounded-xl border border-white/20">
            <Calendar class="w-5 h-5" />
            <span class="text-sm font-semibold">{{ getNightsCount() }} Nuit{{ getNightsCount() > 1 ? 's' : '' }}</span>
          </div>
          <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm px-4 py-2.5 rounded-xl border border-white/20">
            <Users class="w-5 h-5" />
            <span class="text-sm font-semibold">{{ getGuestsCount() }} Invité{{ getGuestsCount() > 1 ? 's' : '' }}</span>
          </div>
          <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm px-4 py-2.5 rounded-xl border border-white/20">
            <DollarSign class="w-5 h-5" />
            <span class="text-sm font-semibold">{{ formatPrice(booking.totalPrice) }} CFA</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline moderne avec design Dribbble -->
    <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300">
      <div class="flex items-center justify-between">
        <!-- Propriétaire confirmé (premier) -->
        <div class="flex items-center gap-4 flex-1 group">
          <div 
            class="relative w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 transition-all duration-300 shadow-lg group-hover:scale-110"
            :class="booking.ownerConfirmedAt ? 'bg-gradient-to-br from-blue-500 to-blue-600' : 'bg-gradient-to-br from-slate-200 to-slate-300'"
          >
            <CheckCircle v-if="booking.ownerConfirmedAt" class="w-7 h-7 text-white" />
            <Clock v-else class="w-7 h-7 text-slate-500" />
            <div v-if="booking.ownerConfirmedAt" class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-400 rounded-full border-2 border-white"></div>
          </div>
          <div>
            <p class="text-sm font-semibold mb-1" :class="booking.ownerConfirmedAt ? 'text-blue-700' : 'text-slate-700'">
              Propriétaire confirmé
            </p>
            <p v-if="booking.ownerConfirmedAt" class="text-xs font-medium text-blue-600">
              {{ formatShortDate(booking.ownerConfirmedAt) }}
            </p>
            <p v-else class="text-xs text-slate-400">En attente</p>
          </div>
        </div>
        <div 
          class="flex-1 h-1 mx-6 rounded-full transition-all duration-300"
          :class="booking.ownerConfirmedAt ? 'bg-gradient-to-r from-blue-500 to-emerald-500' : 'bg-slate-200'"
        ></div>
        
        <!-- Clé récupérée (deuxième) -->
        <div class="flex items-center gap-4 flex-1 group">
          <div 
            class="relative w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 transition-all duration-300 shadow-lg group-hover:scale-110"
            :class="booking.keyRetrievedAt ? 'bg-gradient-to-br from-emerald-500 to-emerald-600' : 'bg-gradient-to-br from-slate-200 to-slate-300'"
          >
            <CheckCircle v-if="booking.keyRetrievedAt" class="w-7 h-7 text-white" />
            <Clock v-else class="w-7 h-7 text-slate-500" />
            <div v-if="booking.keyRetrievedAt" class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-400 rounded-full border-2 border-white"></div>
          </div>
          <div>
            <p class="text-sm font-semibold mb-1" :class="booking.keyRetrievedAt ? 'text-emerald-700' : 'text-slate-700'">
              Clé récupérée
            </p>
            <p v-if="booking.keyRetrievedAt" class="text-xs font-medium text-emerald-600">
              {{ formatShortDate(booking.keyRetrievedAt) }}
            </p>
            <p v-else class="text-xs text-slate-400">En attente</p>
          </div>
        </div>
        <div 
          class="flex-1 h-1 mx-6 rounded-full transition-all duration-300"
          :class="booking.keyRetrievedAt ? 'bg-gradient-to-r from-emerald-500 to-purple-500' : 'bg-slate-200'"
        ></div>
        
        <!-- Check-out effectué (troisième) -->
        <div class="flex items-center gap-4 flex-1 group">
          <div 
            class="relative w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 transition-all duration-300 shadow-lg group-hover:scale-110"
            :class="isCheckOutCompleted ? 'bg-gradient-to-br from-purple-500 to-purple-600' : 'bg-gradient-to-br from-slate-200 to-slate-300'"
          >
            <CheckCircle v-if="isCheckOutCompleted" class="w-7 h-7 text-white" />
            <Clock v-else class="w-7 h-7 text-slate-500" />
            <div v-if="isCheckOutCompleted" class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-400 rounded-full border-2 border-white"></div>
          </div>
          <div>
            <p class="text-sm font-semibold mb-1" :class="isCheckOutCompleted ? 'text-purple-700' : 'text-slate-700'">
              Check-out effectué
            </p>
            <p v-if="isCheckOutCompleted" class="text-xs font-medium text-purple-600">
              {{ booking.checkOutAt ? formatShortDate(booking.checkOutAt) : (booking.endDate ? formatShortDate(booking.endDate) : 'Terminé') }}
            </p>
            <p v-else class="text-xs text-slate-400">En attente</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmer le départ manuellement (séjour en cours, client n'a pas validé) -->
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
        <h2 class="text-2xl font-bold text-slate-900 mb-2">INTERVENANTS</h2>

        <!-- Carte Client (style Dribbble) -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
          <div class="flex items-start gap-5">
            <div class="relative">
              <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg" style="background: rgb(26, 51, 101);">
                {{ getInitials(booking.customerName) }}
              </div>
              <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-emerald-400 rounded-full border-4 border-white shadow-md"></div>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-bold text-slate-900 mb-1">{{ booking.customerName }}</h3>
              <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full mb-4">
                Client / Locataire
              </span>
              <div class="space-y-3 mt-4">
                <a v-if="booking.customerEmail" :href="'mailto:' + booking.customerEmail" 
                   class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-blue-50 transition-colors group">
                  <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <Mail class="w-5 h-5" style="color: rgb(26, 51, 101);" />
                  </div>
                  <span class="text-sm font-medium text-slate-700 group-hover:text-blue-700">
                    {{ booking.customerEmail }}
                  </span>
                </a>
                <a v-if="booking.customerPhone" :href="'tel:' + booking.customerPhone" 
                   class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-blue-50 transition-colors group">
                  <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <Phone class="w-5 h-5" style="color: rgb(26, 51, 101);" />
                  </div>
                  <span class="text-sm font-medium text-slate-700 group-hover:text-blue-700">
                    {{ booking.customerPhone }}
                  </span>
                  <button class="ml-auto p-2 hover:bg-blue-100 rounded-lg transition-colors">
                    <MessageCircle class="w-5 h-5" style="color: rgb(26, 51, 101);" />
                  </button>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Carte Propriétaire (style Dribbble) -->
        <div v-if="booking.ownerName || booking.ownerId" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
          <div class="flex items-start gap-5">
            <div class="relative">
              <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg bg-gradient-to-br from-emerald-400 to-emerald-600">
                {{ getInitials(booking.ownerName || 'Propriétaire') }}
              </div>
              <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-blue-400 rounded-full border-4 border-white shadow-md"></div>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-bold text-slate-900 mb-1">{{ booking.ownerName || 'Propriétaire' }}</h3>
              <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-full mb-4">
                Propriétaire
              </span>
              <div class="space-y-3 mt-4">
                <a v-if="booking.ownerPhone" :href="'tel:' + booking.ownerPhone" 
                   class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-emerald-50 transition-colors group">
                  <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                    <Phone class="w-5 h-5 text-emerald-600" />
                  </div>
                  <span class="text-sm font-medium text-slate-700 group-hover:text-emerald-700">
                    {{ booking.ownerPhone }}
                  </span>
                </a>
                <div v-if="booking.ownerAddress" 
                     class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                  <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <MapPin class="w-5 h-5 text-emerald-600" />
                  </div>
                  <span class="text-sm font-medium text-slate-700">{{ booking.ownerAddress }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Colonne droite : LA RÉSERVATION -->
      <div class="space-y-6">
        <h2 class="text-2xl font-bold text-slate-900 mb-2">LA RÉSERVATION</h2>

        <!-- Infos Clés (Bento Grid Style) -->
        <div class="bg-gradient-to-br from-white to-slate-50 border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-300">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-lg" style="background: rgb(26, 51, 101);">
              <Info class="w-6 h-6 text-white" />
            </div>
            <h3 class="text-xl font-bold text-slate-900">Infos Clés</h3>
          </div>
          <dl class="grid grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-white border border-slate-100 hover:border-blue-200 transition-colors">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">STATUT ACTUEL</dt>
              <dd>
                <span
                  class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
              </dd>
            </div>
            <div class="p-4 rounded-xl bg-white border border-slate-100 hover:border-blue-200 transition-colors">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">CRÉÉE LE</dt>
              <dd class="text-sm font-semibold text-slate-900">{{ formatShortDate(booking.createdAt) }}</dd>
            </div>
            <div class="p-4 rounded-xl bg-white border border-slate-100 hover:border-blue-200 transition-colors">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">DÉBUT</dt>
              <dd class="text-sm font-semibold text-slate-900">{{ formatShortDate(booking.startDate) }}</dd>
            </div>
            <div class="p-4 rounded-xl bg-white border border-slate-100 hover:border-blue-200 transition-colors">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">FIN</dt>
              <dd class="text-sm font-semibold text-slate-900">{{ formatShortDate(booking.endDate) }}</dd>
            </div>
            <div v-if="booking.unitPriceAmount" class="p-4 rounded-xl bg-white border border-slate-100 hover:border-blue-200 transition-colors">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">PRIX UNITAIRE</dt>
              <dd class="text-sm font-bold text-slate-900">
                {{ formatPrice(booking.unitPriceAmount) }} CFA {{ formatUnitSuffix(booking.unitPriceLabel) }}
              </dd>
            </div>
            <div class="p-4 rounded-xl text-white border-0 shadow-lg" style="background: rgb(26, 51, 101);">
              <dt class="text-xs font-semibold text-white/80 uppercase tracking-wide mb-2">PRIX TOTAL</dt>
              <dd class="text-lg font-bold">{{ formatPrice(booking.totalPrice) }} CFA</dd>
            </div>
            <div v-if="booking.totalPaid !== undefined" class="p-4 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white border-0 shadow-lg">
              <dt class="text-xs font-semibold text-white/80 uppercase tracking-wide mb-2">MONTANT PAYÉ</dt>
              <dd class="text-lg font-bold">{{ formatPrice(booking.totalPaid) }} CFA</dd>
            </div>
            <div v-if="booking.remainingBalance !== undefined && booking.remainingBalance > 0" class="p-4 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 text-white border-0 shadow-lg">
              <dt class="text-xs font-semibold text-white/80 uppercase tracking-wide mb-2">SOLDE RESTANT</dt>
              <dd class="text-lg font-bold">{{ formatPrice(booking.remainingBalance) }} CFA</dd>
            </div>
          </dl>
        </div>

        <!-- Monitoring des Paiements Mobile Money -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <DollarSign class="w-5 h-5 text-emerald-600" />
              <h3 class="text-lg font-semibold text-slate-900">Suivi des Paiements</h3>
            </div>
            <button
              v-if="!booking.isFullyPaid"
              @click="showMarkAsPaidModal = true"
              class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium"
            >
              Marquer comme payé
            </button>
          </div>

          <!-- Liste des transactions -->
          <div v-if="booking.payments && booking.payments.length > 0" class="space-y-3 mb-4">
            <div
              v-for="payment in booking.payments"
              :key="payment.id"
              class="border border-slate-200 rounded-lg p-4"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <span
                      class="px-2 py-1 rounded text-xs font-medium"
                      :class="getPaymentStatusClass(payment.status)"
                    >
                      {{ formatPaymentStatus(payment.status) }}
                    </span>
                    <span
                      v-if="payment.provider"
                      class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700"
                    >
                      {{ formatPaymentProvider(payment.provider) }}
                    </span>
                  </div>
                  <div class="text-sm font-semibold text-slate-900 mb-1">
                    {{ formatPrice(payment.amount) }} CFA
                  </div>
                  <div v-if="payment.transactionId" class="text-xs text-slate-500 font-mono">
                    Transaction: {{ payment.transactionId }}
                  </div>
                  <div v-if="payment.phoneNumber" class="text-xs text-slate-500">
                    Téléphone: {{ payment.phoneNumber }}
                  </div>
                  <div v-if="payment.createdAt" class="text-xs text-slate-500 mt-1">
                    {{ formatShortDate(payment.createdAt) }}
                  </div>
                  <div v-if="payment.notes" class="text-xs text-slate-600 mt-2 italic">
                    {{ payment.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-slate-500 text-center py-4">
            Aucune transaction enregistrée
          </div>

          <!-- Résumé des paiements -->
          <div class="border-t border-slate-200 pt-4 mt-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <div class="text-slate-500">Total à payer</div>
                <div class="font-semibold text-slate-900">{{ formatPrice(booking.totalPrice) }} CFA</div>
              </div>
              <div>
                <div class="text-slate-500">Total payé</div>
                <div class="font-semibold text-emerald-600">{{ formatPrice(booking.totalPaid || 0) }} CFA</div>
              </div>
              <div v-if="booking.remainingBalance !== undefined && booking.remainingBalance > 0">
                <div class="text-slate-500">Solde restant</div>
                <div class="font-semibold text-amber-600">{{ formatPrice(booking.remainingBalance) }} CFA</div>
              </div>
              <div>
                <div class="text-slate-500">Statut</div>
                <div class="font-semibold" :class="booking.isFullyPaid ? 'text-emerald-600' : 'text-amber-600'">
                  {{ booking.isFullyPaid ? 'Entièrement payé' : 'En attente de paiement' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal pour marquer comme payé -->
        <div
          v-if="showMarkAsPaidModal"
          class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
          @click.self="showMarkAsPaidModal = false"
        >
          <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Marquer la réservation comme payée</h3>
            <form @submit.prevent="handleMarkAsPaid" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Montant (CFA)
                </label>
                <input
                  v-model.number="markAsPaidForm.amount"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  :placeholder="booking.totalPrice.toString()"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Méthode de paiement
                </label>
                <select
                  v-model="markAsPaidForm.paymentMethod"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                >
                  <option value="orange_money">Orange Money</option>
                  <option value="mtn_money">MTN Money</option>
                  <option value="wave">Wave</option>
                  <option value="manual">Manuel</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  ID Transaction (optionnel)
                </label>
                <input
                  v-model="markAsPaidForm.transactionId"
                  type="text"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  placeholder="Ex: OR123456789"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Notes (optionnel)
                </label>
                <textarea
                  v-model="markAsPaidForm.notes"
                  rows="3"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  placeholder="Informations supplémentaires..."
                ></textarea>
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  @click="showMarkAsPaidModal = false"
                  class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  :disabled="markingAsPaid"
                  class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50"
                >
                  {{ markingAsPaid ? 'Enregistrement...' : 'Confirmer' }}
                </button>
              </div>
            </form>
          </div>
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
import AdminLayout from '../../Components/Layouts/AdminLayout.vue';
import { getStorageImageUrl } from '../../utils/imageUrl';
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
  layout: AdminLayout,
});

const props = defineProps<{
  booking: {
    id: string;
    bookingType?: 'residence' | 'vehicle' | 'package' | 'unknown';
    customerName: string;
    customerEmail: string | null;
    customerPhone: string | null;
    clientId: string | null;
    propertyName: string;
    residenceId: string | null;
    residenceDetails: any | null;
    vehicleName: string | null;
    vehicleId: string | null;
    vehicleDetails: any | null;
    vehicleDriverOption?: 'with_driver' | 'without_driver' | null;
    offerName: string | null;
    offerId: string | null;
    offerDetails: any | null;
    startDate: string | null;
    endDate: string | null;
    totalPrice: number;
    totalPaid?: number;
    remainingBalance?: number;
    isFullyPaid?: boolean;
    payments?: Array<{
      id: string | null;
      amount: number;
      status: string;
      type: string;
      method?: string | null;
      transactionId?: string | null;
      provider?: string | null;
      phoneNumber?: string | null;
      createdAt?: string | null;
      updatedAt?: string | null;
      notes?: string | null;
    }>;
    unitPriceAmount?: number | null;
    unitPriceLabel?: string | null;
    status: string;
    createdAt: string | null;
    keyRetrievedAt: string | null;
    ownerConfirmedAt: string | null;
    checkOutAt: string | null;
    isStayInProgress?: boolean;
    isStayCompleted?: boolean;
    ownerId: string | null;
    ownerName: string | null;
    ownerPhone: string | null;
    ownerAddress: string | null;
    reviewId: string | null;
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

const bookingIndexUrl = '/admin/bookings';

function route(name: string, params?: string): string {
  const routes: Record<string, string | ((id: string) => string)> = {
    'admin.bookings.index': bookingIndexUrl,
    'admin.bookings.show': (id: string) => `/admin/bookings/${id}`,
    'admin.bookings.destroy': (id: string) => `/admin/bookings/${id}`,
    'admin.bookings.mark-as-paid': (id: string) => `/admin/bookings/${id}/mark-as-paid`,
    'admin.bookings.confirm-checkout': (id: string) => `/admin/bookings/${id}/confirm-checkout`,
  };
  const value = routes[name];
  if (typeof value === 'function') {
    return value(params ?? props.booking.id);
  }
  return value ?? '#';
}

const showTechnicalInfo = ref(false);
const showMarkAsPaidModal = ref(false);
const markingAsPaid = ref(false);
const markAsPaidForm = ref({
  amount: null as number | null,
  paymentMethod: 'manual' as 'orange_money' | 'mtn_money' | 'wave' | 'manual',
  transactionId: '',
  notes: '',
});

const formatDate = (date: string | null): string => {
  if (!date) return 'N/A';
  try {
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    });
  } catch {
    return date;
  }
};

const formatShortDate = (date: string | null): string => {
  if (!date) return 'N/A';
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

const formatDateRange = (startDate: string | null, endDate: string | null): string => {
  if (!startDate || !endDate) return 'Dates non définies';
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} - ${end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}`;
  } catch {
    return `${startDate} - ${endDate}`;
  }
};

const formatPrice = (price: number): string => {
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

const isStartDatePassed = (): boolean => {
  if (!props.booking.startDate) return false;
  try {
    const start = new Date(props.booking.startDate);
    start.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    // La date de début est passée si elle est <= aujourd'hui
    return start <= today;
  } catch {
    return false;
  }
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

const isStartDateToday = (): boolean => {
  if (!props.booking.startDate) return false;
  try {
    const start = new Date(props.booking.startDate);
    start.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return start.getTime() === today.getTime();
  } catch {
    return false;
  }
};

const getInitials = (name: string): string => {
  if (!name) return '?';
  const parts = name.trim().split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

const canCancelBooking = (): boolean => {
  const status = props.booking.status?.toLowerCase() || '';
  // Ne pas permettre l'annulation si la réservation est terminée, annulée ou complétée
  const finalStatuses = ['completed', 'terminee', 'terminée', 'cancelled', 'canceled', 'annulée', 'annulee'];
  return !finalStatuses.includes(status);
};

/** Afficher le bouton "Confirmer le départ" : séjour en cours et checkout pas encore fait */
const canShowManualCheckOut = computed(() => {
  const b = props.booking;
  if (b.checkOutAt) return false;
  if (b.isStayInProgress === true) return true;
  // Fallback : clé récupérée + date de fin pas encore passée
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
  router.patch(route('admin.bookings.confirm-checkout', props.booking.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // La page est rechargée avec les nouvelles données (checkOutAt renseigné)
    },
  });
};

const handleCancel = () => {
  if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
    router.delete(route('admin.bookings.destroy', props.booking.id));
  }
};

const handleMarkAsPaid = async () => {
  markingAsPaid.value = true;
  try {
    await router.patch(route('admin.bookings.mark-as-paid', props.booking.id), {
      amount: markAsPaidForm.value.amount || props.booking.totalPrice,
      paymentMethod: markAsPaidForm.value.paymentMethod,
      transactionId: markAsPaidForm.value.transactionId || undefined,
      notes: markAsPaidForm.value.notes || undefined,
    });
    showMarkAsPaidModal.value = false;
    markAsPaidForm.value = {
      amount: null,
      paymentMethod: 'manual',
      transactionId: '',
      notes: '',
    };
  } catch (error) {
    console.error('Erreur lors du marquage comme payé:', error);
  } finally {
    markingAsPaid.value = false;
  }
};

const formatPaymentStatus = (status: string): string => {
  const statusLower = status.toLowerCase();
  const statusMap: Record<string, string> = {
    completed: 'Complété',
    completé: 'Complété',
    paid: 'Payé',
    payé: 'Payé',
    validated: 'Validé',
    validé: 'Validé',
    pending: 'En attente',
    'en attente': 'En attente',
    failed: 'Échoué',
    échoué: 'Échoué',
    cancelled: 'Annulé',
    annulé: 'Annulé',
  };
  return statusMap[statusLower] || status;
};

const getPaymentStatusClass = (status: string): string => {
  const statusLower = status.toLowerCase();
  if (statusLower === 'completed' || statusLower === 'completé' || statusLower === 'paid' || statusLower === 'payé' || statusLower === 'validated' || statusLower === 'validé') {
    return 'bg-emerald-100 text-emerald-700';
  } else if (statusLower === 'pending' || statusLower === 'en attente') {
    return 'bg-yellow-100 text-yellow-700';
  } else if (statusLower === 'failed' || statusLower === 'échoué') {
    return 'bg-red-100 text-red-700';
  }
  return 'bg-slate-100 text-slate-700';
};

const formatPaymentProvider = (provider: string): string => {
  const providerLower = provider.toLowerCase();
  const providerMap: Record<string, string> = {
    orange_money: 'Orange Money',
    mtn_money: 'MTN Money',
    wave: 'Wave',
    manual: 'Manuel',
  };
  return providerMap[providerLower] || provider;
};

const viewVehicle = (vehicleId: string) => {
  window.location.href = `/admin/vehicles/${vehicleId}`;
};

const viewResidence = (residenceId: string) => {
  window.location.href = `/admin/residences/${residenceId}`;
};

</script>
