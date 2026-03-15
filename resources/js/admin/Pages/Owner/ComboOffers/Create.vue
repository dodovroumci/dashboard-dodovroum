<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Ajouter une offre combinée</h1>
        <p class="text-sm text-slate-500 mt-1">Créer une nouvelle offre résidence + véhicule</p>
      </div>
      <Link
        :href="route('owner.combo-offers.index')"
        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
      >
        Annuler
      </Link>
    </div>

    <!-- Message d'erreur général -->
    <div v-if="errors.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
      {{ errors.error }}
    </div>

    <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-6">
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
              @click="fileInput?.click()"
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
      <div>
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
              Aucune résidence disponible
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
                {{ vehicle.titre || vehicle.name || `${vehicle.marque || ''} ${vehicle.modele || ''}`.trim() || 'Véhicule sans nom' }} - {{ formatPrice(vehicle.prixParJour || vehicle.pricePerDay || vehicle.price || 0) }} CFA/jour
              </option>
            </select>
            <p v-if="errors.vehicleId" class="text-red-600 text-sm mt-1">{{ errors.vehicleId }}</p>
            <p v-if="ownerVehicles.length === 0 && !loadingVehicles" class="text-amber-600 text-sm mt-1">
              Aucun véhicule disponible
            </p>
          </div>
        </div>
      </div>

      <!-- Dates -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Dates de validité</h2>
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
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          :href="route('owner.combo-offers.index')"
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
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { getStorageImageUrl } from '../../../utils/imageUrl';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';

defineOptions({
  layout: OwnerLayout,
});

const props = defineProps<{
  residences?: Array<any>;
  vehicles?: Array<any>;
}>();

const form = useForm({
  title: '',
  description: '',
  residenceId: '',
  vehicleId: '',
  originalPrice: 0,
  discountedPrice: 0,
  discount: 0,
  nbJours: null,
  imageUrl: '',
  images: [] as string[],
  startDate: '',
  endDate: '',
  isActive: true,
});

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

// Charger les résidences et véhicules du propriétaire connecté
const loadOwnerProperties = async () => {
  loadingResidences.value = true;
  loadingVehicles.value = true;

  try {
    // Charger les résidences et véhicules du propriétaire connecté
    const response = await axios.get('/owner/combo-offers/owner-properties');
    
    ownerResidences.value = response.data?.residences || [];
    ownerVehicles.value = response.data?.vehicles || [];

    // Si les données sont déjà passées en props, les utiliser
    if (props.residences && props.residences.length > 0) {
      ownerResidences.value = props.residences;
    }
    if (props.vehicles && props.vehicles.length > 0) {
      ownerVehicles.value = props.vehicles;
    }
  } catch (error: any) {
    console.error('Erreur lors du chargement des résidences/véhicules:', error);
    // Utiliser les props en cas d'erreur
    if (props.residences) {
      ownerResidences.value = props.residences;
    }
    if (props.vehicles) {
      ownerVehicles.value = props.vehicles;
    }
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
const fileInput = ref<HTMLInputElement | null>(null);

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploading.value = true;
  const formData = new FormData();
  formData.append('image', file);

  try {
    const response = await axios.post('/owner/images/upload', formData, {
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
    if (target) {
      target.value = '';
    }
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

const submit = () => {
  form.post(route('owner.combo-offers.store'), {
    onSuccess: () => {
      // Redirection gérée par le contrôleur
    },
  });
};

const errors = form.errors;
const processing = form.processing;

const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-FR').format(price);
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, string> = {
    'owner.combo-offers.index': '/owner/combo-offers',
    'owner.combo-offers.store': '/owner/combo-offers',
  };
  return routes[name] || '#';
};

// Dates par défaut (évite l'échec de validation si l'utilisateur n'a pas rempli les dates)
const setDefaultDates = () => {
  const today = new Date().toISOString().slice(0, 10);
  const oneYearLater = new Date();
  oneYearLater.setFullYear(oneYearLater.getFullYear() + 1);
  const endDefault = oneYearLater.toISOString().slice(0, 10);
  if (!form.startDate) form.startDate = today;
  if (!form.endDate) form.endDate = endDefault;
};

// Charger les propriétés au montage
onMounted(() => {
  setDefaultDates();
  loadOwnerProperties();
});
</script>
