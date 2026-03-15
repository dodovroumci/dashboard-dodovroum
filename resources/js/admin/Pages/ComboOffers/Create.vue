<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Ajouter une offre combinée</h1>
        <p class="text-sm text-slate-500 mt-1">Créer une nouvelle offre résidence + véhicule</p>
      </div>
      <Link
        :href="route('admin.combo-offers.index')"
        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
      >
        Annuler
      </Link>
    </div>

    <!-- Message d'erreur serveur -->
    <div v-if="errors.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
      {{ errors.error }}
    </div>

    <!-- Message après clic (erreur envoi ou validation) -->
    <div v-if="submitError" class="bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded mb-4">
      {{ submitError }}
    </div>

    <!-- Erreurs de validation (champs manquants ou invalides) -->
    <div
      v-if="hasValidationErrors"
      class="bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded mb-4"
    >
      <p class="font-medium mb-2">Veuillez corriger les points suivants avant de créer l'offre :</p>
      <ul class="list-disc list-inside text-sm space-y-1">
        <li v-for="(msg, key) in validationErrorList" :key="key">{{ msg }}</li>
      </ul>
    </div>

    <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-6" novalidate>
      <!-- Informations de base -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Informations de base</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Titre *
            </label>
            <input
              v-model="form.title"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="ex: Week-end détente avec véhicule"
            />
            <p v-if="errors.title" class="text-red-600 text-sm mt-1">{{ errors.title }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Propriétaire *
            </label>
            <select
              v-model="selectedOwnerId"
              @change="onOwnerChange"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner un propriétaire</option>
              <option
                v-for="owner in owners"
                :key="owner.id"
                :value="owner.id"
              >
                {{ owner.name }} {{ owner.email ? `(${owner.email})` : '' }}
              </option>
            </select>
            <p v-if="errors.ownerId" class="text-red-600 text-sm mt-1">{{ errors.ownerId }}</p>
            <p v-else-if="owners && owners.length === 0" class="text-amber-600 text-sm mt-1">
              Aucun propriétaire trouvé. Vérifiez que l'API est accessible et que des comptes propriétaires existent.
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nombre de jours *
            </label>
            <input
              v-model.number="form.nbJours"
              type="number"
              min="1"
              required
              @input="calculatePrice"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="7"
            />
            <p v-if="errors.nbJours" class="text-red-600 text-sm mt-1">{{ errors.nbJours }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Réduction (%)
            </label>
            <input
              v-model.number="discountPercentage"
              type="number"
              min="0"
              max="100"
              step="0.01"
              @input="applyDiscount"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="10"
            />
            <p class="text-xs text-slate-500 mt-1">
              Réduction appliquée: {{ discountPercentage || 0 }}%
            </p>
          </div>

        </div>
      </div>

      <!-- Calcul du prix -->
      <div v-if="form.originalPrice > 0" class="bg-slate-50 border border-slate-200 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Calcul du prix</h3>
        <div class="space-y-1 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-600">Prix résidence ({{ form.nbJours || 0 }} nuit{{ form.nbJours > 1 ? 's' : '' }}):</span>
            <span class="font-medium">{{ formatPrice(residencePrice) }} CFA</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-600">Prix véhicule ({{ form.nbJours || 0 }} jour{{ form.nbJours > 1 ? 's' : '' }}):</span>
            <span class="font-medium">{{ formatPrice(vehiclePrice) }} CFA</span>
          </div>
          <div class="flex justify-between pt-2 border-t border-slate-200">
            <span class="text-slate-700 font-medium">Prix total:</span>
            <span class="font-bold text-lg">{{ formatPrice(form.originalPrice) }} CFA</span>
          </div>
          <div v-if="discountPercentage > 0" class="flex justify-between pt-2 border-t border-slate-200">
            <span class="text-slate-700 font-medium">Prix réduit ({{ discountPercentage }}%):</span>
            <span class="font-bold text-lg text-emerald-600">{{ formatPrice(form.discountedPrice) }} CFA</span>
          </div>
          <div v-if="discountPercentage > 0" class="flex justify-between">
            <span class="text-slate-600">Économie:</span>
            <span class="font-medium text-emerald-600">{{ formatPrice(form.originalPrice - form.discountedPrice) }} CFA</span>
          </div>
        </div>
      </div>

      <!-- Images -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Images</h2>
        <div class="space-y-4">
          <!-- Upload de fichier -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">
              Uploader une image depuis votre PC
            </label>
            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              @change="handleFileUpload"
              class="hidden"
            />
            <button
              type="button"
              @click="$refs.fileInput.click()"
              :disabled="uploading"
              class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 flex items-center gap-2"
            >
              <svg v-if="!uploading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <svg v-else class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ uploading ? 'Upload en cours...' : 'Choisir un fichier' }}
            </button>
          </div>

          <!-- Prévisualisation des images -->
          <div v-if="form.images && form.images.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="(image, index) in form.images" :key="index" class="relative group">
              <div class="w-full h-32 rounded-lg border border-slate-300 overflow-hidden bg-slate-100 flex items-center justify-center">
                <img
                  v-if="!imageErrors[index]"
                  :src="getStorageImageUrl(image, 'offers')"
                  :alt="`Image ${index + 1}`"
                  class="w-full h-full object-cover"
                  @error="() => handleImageError(index)"
                  @load="() => imageErrors[index] = false"
                />
                <div v-else class="w-full h-full flex flex-col items-center justify-center p-2 text-center">
                  <svg class="w-8 h-8 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <p class="text-xs text-slate-500 break-all px-2" :title="image">{{ image.length > 50 ? image.substring(0, 50) + '...' : image }}</p>
                  <p class="text-xs text-slate-400 mt-1">Image non accessible</p>
                </div>
              </div>
              <button
                type="button"
                @click.stop.prevent="removeImage(index)"
                class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1.5 opacity-90 hover:opacity-100 transition-opacity hover:bg-red-600 z-50 shadow-lg"
                style="pointer-events: auto !important;"
                title="Supprimer cette image"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sélection résidence et véhicule -->
      <div v-if="selectedOwnerId">
        <h2 class="text-lg font-semibold mb-4">Sélection</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Résidence *
            </label>
            <select
              v-model="form.residenceId"
              @change="onResidenceChange"
              required
              :disabled="loadingResidences"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              <option value="">{{ loadingResidences ? 'Chargement...' : 'Sélectionner une résidence' }}</option>
              <option
                v-for="residence in ownerResidences"
                :key="residence.id"
                :value="residence.id"
              >
                {{ residence.nom || residence.name || residence.title || 'Résidence sans nom' }} - {{ formatPrice(residence.prixParNuit || residence.pricePerNight || residence.price || 0) }} CFA/nuit
              </option>
            </select>
            <p v-if="errors.residenceId" class="text-red-600 text-sm mt-1">{{ errors.residenceId }}</p>
            <p v-if="ownerResidences.length === 0 && !loadingResidences" class="text-amber-600 text-sm mt-1">
              Aucune résidence disponible pour ce propriétaire
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Véhicule *
            </label>
            <select
              v-model="form.vehicleId"
              @change="onVehicleChange"
              required
              :disabled="loadingVehicles"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              <option value="">{{ loadingVehicles ? 'Chargement...' : 'Sélectionner un véhicule' }}</option>
              <option
                v-for="vehicle in ownerVehicles"
                :key="vehicle.id"
                :value="vehicle.id"
              >
                {{ getVehicleDisplayName(vehicle) }}{{ vehicle.year || vehicle.annee ? ` (${vehicle.year || vehicle.annee})` : '' }} - {{ formatPrice(vehicle.pricePerDay || vehicle.price_per_day || vehicle.prixParJour || vehicle.price || 0) }} CFA/jour
              </option>
            </select>
            <p v-if="errors.vehicleId" class="text-red-600 text-sm mt-1">{{ errors.vehicleId }}</p>
            <p v-if="ownerVehicles.length === 0 && !loadingVehicles" class="text-amber-600 text-sm mt-1">
              Aucun véhicule disponible pour ce propriétaire
            </p>
          </div>
        </div>
      </div>

      <!-- Dates -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Dates de validité</h2>
        <p class="text-xs text-slate-500 mb-2">Optionnel : si non renseigné, période par défaut = aujourd’hui → +1 an.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Date de début
            </label>
            <input
              v-model="form.startDate"
              type="date"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="errors.startDate" class="text-red-600 text-sm mt-1">{{ errors.startDate }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Date de fin
            </label>
            <input
              v-model="form.endDate"
              type="date"
              :min="form.startDate"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="errors.endDate" class="text-red-600 text-sm mt-1">{{ errors.endDate }}</p>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Description
        </label>
        <textarea
          v-model="form.description"
          rows="4"
          maxlength="500"
          class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          placeholder="Description de l'offre combinée..."
        ></textarea>
        <div class="flex justify-between items-center mt-1">
          <p v-if="errors.description" class="text-red-600 text-sm">{{ errors.description }}</p>
          <p class="text-xs text-slate-500 ml-auto">{{ form.description?.length || 0 }}/500 caractères</p>
        </div>
      </div>

      <!-- Statut -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Statut</h2>
        <div class="space-y-3">
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="form.isActive"
              class="mr-2"
            />
            <span class="text-sm font-medium text-slate-700">Offre active</span>
          </label>
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="form.isVerified"
              class="mr-2"
            />
            <span class="text-sm font-medium text-slate-700">Offre vérifiée</span>
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          :href="route('admin.combo-offers.index')"
          class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
        >
          Annuler
        </Link>
        <button
          type="submit"
          :disabled="processing"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {{ processing ? 'Création...' : 'Créer l\'offre' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { getStorageImageUrl } from '../../utils/imageUrl';
import { z } from 'zod';

const props = defineProps<{
  owners?: Array<{ id: string; name: string; email?: string }>;
}>();

const form = useForm({
  title: '',
  description: '',
  residenceId: '',
  vehicleId: '',
  ownerId: '',
  originalPrice: 0,
  discountedPrice: 0,
  discount: 0,
  nbJours: null,
  imageUrl: '',
  images: [] as string[],
  startDate: '',
  endDate: '',
  isActive: true,
  isVerified: false,
});

const selectedOwnerId = ref('');
const ownerResidences = ref<any[]>([]);
const ownerVehicles = ref<any[]>([]);
const loadingResidences = ref(false);
const loadingVehicles = ref(false);
const discountPercentage = ref(0);

// Prix calculés
const residencePrice = computed(() => {
  if (!form.nbJours || !form.residenceId) return 0;
  const residence = ownerResidences.value.find(r => r.id === form.residenceId);
  if (!residence) return 0;
  const pricePerNight = residence.prixParNuit || residence.pricePerNight || residence.price || 0;
  return pricePerNight * form.nbJours;
});

const vehiclePrice = computed(() => {
  if (!form.nbJours || !form.vehicleId) return 0;
  const vehicle = ownerVehicles.value.find(v => v.id === form.vehicleId);
  if (!vehicle) return 0;
  const pricePerDay = vehicle.prixParJour || vehicle.pricePerDay || vehicle.price || 0;
  return pricePerDay * form.nbJours;
});

// Charger les résidences et véhicules du propriétaire
const onOwnerChange = async () => {
  if (!selectedOwnerId.value) {
    ownerResidences.value = [];
    ownerVehicles.value = [];
    form.residenceId = '';
    form.vehicleId = '';
    form.originalPrice = 0;
    form.discountedPrice = 0;
    return;
  }

  loadingResidences.value = true;
  loadingVehicles.value = true;

  try {
    console.log('Chargement des propriétés pour le propriétaire:', selectedOwnerId.value);
    // Charger les résidences et véhicules du propriétaire via l'API
    const response = await axios.get('/admin/combo-offers/owner-properties', {
      params: { ownerId: selectedOwnerId.value }
    });
    
    console.log('Réponse API owner-properties:', response.data);
    
    ownerResidences.value = response.data?.residences || [];
    ownerVehicles.value = response.data?.vehicles || [];

    console.log('Résidences trouvées:', ownerResidences.value.length);
    console.log('Véhicules trouvés:', ownerVehicles.value.length);

    // Réinitialiser les sélections
    form.residenceId = '';
    form.vehicleId = '';
    form.ownerId = selectedOwnerId.value;
    form.originalPrice = 0;
    form.discountedPrice = 0;
    discountPercentage.value = 0;
  } catch (error: any) {
    console.error('Erreur lors du chargement des résidences/véhicules:', error);
    console.error('Détails de l\'erreur:', error.response?.data || error.message);
    ownerResidences.value = [];
    ownerVehicles.value = [];
  } finally {
    loadingResidences.value = false;
    loadingVehicles.value = false;
  }
};

// Calculer le prix total
const calculatePrice = () => {
  const total = residencePrice.value + vehiclePrice.value;
  form.originalPrice = total;
  
  // Réappliquer la réduction si elle existe
  if (discountPercentage.value > 0) {
    applyDiscount();
  } else {
    form.discountedPrice = total;
  }
};

const onResidenceChange = () => {
  calculatePrice();
};

const onVehicleChange = () => {
  calculatePrice();
};

// Appliquer la réduction
const applyDiscount = () => {
  if (discountPercentage.value > 0 && form.originalPrice > 0) {
    const discount = (form.originalPrice * discountPercentage.value) / 100;
    form.discountedPrice = form.originalPrice - discount;
    form.discount = discountPercentage.value;
  } else {
    form.discountedPrice = form.originalPrice;
    form.discount = 0;
  }
};

// Gestion de l'upload d'images
const uploading = ref(false);
const imageErrors = ref<Record<number, boolean>>({});

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploading.value = true;
  const formData = new FormData();
  formData.append('image', file);

  try {
    const response = await axios.post('/admin/images/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    if (response.data.url) {
      if (!form.images) {
        form.images = [];
      }
      form.images.push(response.data.url);
    }
  } catch (error) {
    console.error('Erreur upload image:', error);
    alert('Erreur lors de l\'upload de l\'image');
  } finally {
    uploading.value = false;
    target.value = '';
  }
};

const handleImageError = (index: number) => {
  imageErrors.value[index] = true;
};

const removeImage = (index: number) => {
  if (!form.images || index < 0 || index >= form.images.length) return;
  
  // Créer un nouveau tableau pour déclencher la réactivité
  const newImages = [...form.images];
  newImages.splice(index, 1);
  form.images = newImages;
  
  // Supprimer l'erreur associée si elle existe
  if (imageErrors.value[index] !== undefined) {
    const newErrors: Record<number, boolean> = {};
    Object.keys(imageErrors.value).forEach((key) => {
      const keyNum = parseInt(key);
      if (keyNum < index) {
        newErrors[keyNum] = imageErrors.value[keyNum];
      } else if (keyNum > index) {
        newErrors[keyNum - 1] = imageErrors.value[keyNum];
      }
    });
    imageErrors.value = newErrors;
  }
};

// Schéma de validation Zod
const packSchema = z.object({
  title: z.string().min(1, 'Le titre est requis').max(255, 'Le titre ne peut pas dépasser 255 caractères'),
  description: z.string().optional(),
  residenceId: z.string().min(1, 'La résidence est requise'),
  vehicleId: z.string().min(1, 'Le véhicule est requis'),
  ownerId: z.string().min(1, 'Le propriétaire est requis'),
  originalPrice: z.number().min(0, 'Le prix original doit être positif'),
  discountedPrice: z.number().min(0, 'Le prix réduit doit être positif'),
  discount: z.number().min(0, 'La réduction doit être positive').max(100, 'La réduction ne peut pas dépasser 100%').optional(),
  nbJours: z.number().int().min(1, 'Le nombre de jours doit être au moins 1').optional().nullable(),
  startDate: z.string().optional(),
  endDate: z.string().optional(),
  imageUrl: z.string().optional(),
  images: z.array(z.string()).optional(),
  isActive: z.boolean().optional(),
  isVerified: z.boolean().optional(),
}).refine((data) => {
  // Vérifier que la date de fin est après la date de début
  if (data.startDate && data.endDate) {
    const start = new Date(data.startDate);
    const end = new Date(data.endDate);
    return end > start;
  }
  return true;
}, {
  message: 'La date de fin doit être après la date de début',
  path: ['endDate'],
}).refine((data) => {
  // Vérifier que le prix réduit n'est pas supérieur au prix original
  return data.discountedPrice <= data.originalPrice;
}, {
  message: 'Le prix réduit ne peut pas être supérieur au prix original',
  path: ['discountedPrice'],
}).refine((data) => {
  // Les listes sont chargées pour le propriétaire sélectionné : si résidence et véhicule
  // viennent de ces listes, ils appartiennent au même propriétaire (comparaison en string pour éviter number/string).
  if (data.residenceId && data.vehicleId && data.ownerId && ownerResidences.value.length > 0 && ownerVehicles.value.length > 0) {
    const residenceInList = ownerResidences.value.some((r: { id?: string | number }) => String(r.id) === String(data.residenceId));
    const vehicleInList = ownerVehicles.value.some((v: { id?: string | number }) => String(v.id) === String(data.vehicleId));
    return residenceInList && vehicleInList;
  }
  return true;
}, {
  message: 'La résidence et le véhicule doivent appartenir au même propriétaire',
  path: ['vehicleId'],
});

const validationErrors = ref<Record<string, string>>({});
const submitError = ref<string | null>(null);

const submit = () => {
  validationErrors.value = {};
  submitError.value = null;

  if (selectedOwnerId.value) {
    form.ownerId = selectedOwnerId.value;
  }

  try {
    const formData = {
      title: form.title,
      description: form.description || '',
      residenceId: form.residenceId,
      vehicleId: form.vehicleId,
      ownerId: form.ownerId,
      originalPrice: form.originalPrice,
      discountedPrice: form.discountedPrice,
      discount: form.discount || 0,
      nbJours: form.nbJours,
      startDate: form.startDate,
      endDate: form.endDate,
      imageUrl: form.imageUrl || '',
      images: form.images || [],
      isActive: form.isActive ?? true,
      isVerified: form.isVerified ?? false,
    };

    packSchema.parse(formData);

    form.post(route('admin.combo-offers.store'), {
      onSuccess: () => {
        submitError.value = null;
      },
      onError: (errors) => {
        submitError.value = 'Une erreur est survenue. Vérifiez les messages ci-dessous.';
      },
      onFinish: () => {
        // Inertia a terminé (succès ou échec)
      },
    });
  } catch (err: unknown) {
    if (err instanceof z.ZodError && Array.isArray(err.errors)) {
      err.errors.forEach((e: { path?: (string | number)[]; message?: string }) => {
        const path = Array.isArray(e.path) ? e.path.join('.') : 'form';
        const msg = e.message ?? 'Champ invalide';
        validationErrors.value[path] = msg;
        form.setError(path as any, msg);
      });
      submitError.value = 'Veuillez corriger les champs indiqués.';
    } else {
      const message = err instanceof Error ? err.message : String(err);
      console.error('Erreur création offre combinée:', err);
      submitError.value = message || 'Une erreur inattendue s\'est produite.';
    }
  }
};

const errors = computed(() => {
  return { ...form.errors, ...validationErrors.value };
});
const processing = form.processing;

// Liste lisible des erreurs de validation pour l’encadré en haut
const validationErrorList = computed(() => {
  const list: string[] = [];
  const errs = { ...form.errors, ...validationErrors.value };
  const skipKeys = ['error'];
  Object.entries(errs).forEach(([key, msg]) => {
    if (skipKeys.includes(key) || !msg) return;
    list.push(typeof msg === 'string' ? msg : (msg as string[])[0] || String(msg));
  });
  return list;
});
const hasValidationErrors = computed(() => validationErrorList.value.length > 0);

// Fonction pour formater le nom du véhicule (même format que la liste des véhicules)
const getVehicleDisplayName = (vehicle: any): string => {
  // Même logique exacte que dans Vehicles/Index.vue (ligne 152)
  // Format: vehicle.name || `${vehicle.brand} ${vehicle.model}` || 'Véhicule sans nom'
  
  // Priorité 1: name (champ principal)
  if (vehicle.name) return vehicle.name;
  
  // Priorité 2: titre/title (variantes possibles de l'API)
  if (vehicle.titre) return vehicle.titre;
  if (vehicle.title) return vehicle.title;
  
  // Priorité 3: Construire à partir de brand + model (même format que Index.vue)
  const brand = vehicle.brand || vehicle.marque || '';
  const model = vehicle.model || vehicle.modele || '';
  
  if (brand && model) {
    return `${brand} ${model}`;
  } else if (brand) {
    return brand;
  } else if (model) {
    return model;
  }
  
  // Fallback: nom par défaut (même que Index.vue)
  return 'Véhicule sans nom';
};

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

// Cette fonction n'est plus nécessaire car on calcule directement avec discountPercentage

const route = (name: string, params?: any): string => {
  const routes: Record<string, string> = {
    'admin.combo-offers.index': '/admin/combo-offers',
    'admin.combo-offers.store': '/admin/combo-offers',
  };
  return routes[name] || '#';
};
</script>

