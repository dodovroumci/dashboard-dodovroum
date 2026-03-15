<template>
  <div class="space-y-6">
    <!-- Garde : ne pas accéder au véhicule si absent (évite page blanche) -->
    <div v-if="!props.vehicle" class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-800">
      <p class="font-medium">Véhicule introuvable ou chargement en cours.</p>
      <p class="text-sm mt-1">Si le problème persiste, <Link href="/owner/vehicles" class="underline">retournez à la liste</Link>.</p>
    </div>

    <div v-else class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Modifier le véhicule</h1>
        <p class="text-sm text-slate-500 mt-1">{{ vehicleDisplayName }}</p>
      </div>
      <Link
        href="/owner/vehicles"
        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition"
      >
        Annuler
      </Link>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-6">
      <!-- Informations de base -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Informations de base</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nom du véhicule *
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="ex: BMW X5 2023"
            />
            <p v-if="errors.name" class="text-red-600 text-sm mt-1">{{ errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Type *
            </label>
            <select
              v-model="form.type"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            >
              <option value="">Sélectionner un type</option>
              <option
                v-for="vehicleType in vehicleTypes"
                :key="vehicleType.value"
                :value="vehicleType.value"
              >
                {{ vehicleType.label }}
              </option>
            </select>
            <p v-if="errors.type" class="text-red-600 text-sm mt-1">{{ errors.type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Marque *
            </label>
            <input
              v-model="form.brand"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="ex: BMW"
            />
            <p v-if="errors.brand" class="text-red-600 text-sm mt-1">{{ errors.brand }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Modèle *
            </label>
            <input
              v-model="form.model"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="ex: X5"
            />
            <p v-if="errors.model" class="text-red-600 text-sm mt-1">{{ errors.model }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Année *
            </label>
            <input
              v-model.number="form.year"
              type="number"
              required
              :min="1900"
              :max="new Date().getFullYear() + 1"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="2023"
            />
            <p v-if="errors.year" class="text-red-600 text-sm mt-1">{{ errors.year }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Numéro de plaque *
            </label>
            <input
              v-model="form.plateNumber"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition uppercase"
              placeholder="AB 123 CD"
            />
            <p v-if="errors.plateNumber" class="text-red-600 text-sm mt-1">{{ errors.plateNumber }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Prix par jour (CFA) *
            </label>
            <input
              v-model.number="form.pricePerDay"
              type="number"
              required
              min="0"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="25000"
            />
            <p v-if="errors.pricePerDay" class="text-red-600 text-sm mt-1">{{ errors.pricePerDay }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nombre de places *
            </label>
            <input
              v-model.number="form.seats"
              type="number"
              required
              min="1"
              max="50"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="5"
            />
            <p v-if="errors.seats" class="text-red-600 text-sm mt-1">{{ errors.seats }}</p>
          </div>
        </div>
      </div>

      <!-- Caractéristiques -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Caractéristiques</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Couleur
            </label>
            <input
              v-model="form.color"
              type="text"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="ex: Blanc"
            />
            <p v-if="errors.color" class="text-red-600 text-sm mt-1">{{ errors.color }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Transmission
            </label>
            <select
              v-model="form.transmission"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            >
              <option value="">Sélectionner</option>
              <option value="manual">Manuelle</option>
              <option value="automatic">Automatique</option>
            </select>
            <p v-if="errors.transmission" class="text-red-600 text-sm mt-1">{{ errors.transmission }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Carburant
            </label>
            <select
              v-model="form.fuel"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            >
              <option value="">Sélectionner</option>
              <option value="petrol">Essence</option>
              <option value="diesel">Diesel</option>
              <option value="electric">Électrique</option>
              <option value="hybrid">Hybride</option>
            </select>
            <p v-if="errors.fuel" class="text-red-600 text-sm mt-1">{{ errors.fuel }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Kilométrage
            </label>
            <input
              v-model.number="form.mileage"
              type="number"
              min="0"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              placeholder="50000"
            />
            <p v-if="errors.mileage" class="text-red-600 text-sm mt-1">{{ errors.mileage }}</p>
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
              class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 flex items-center gap-2 transition"
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
                  :src="getStorageImageUrl(image, 'vehicles')"
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

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Description
        </label>
        <textarea
          v-model="form.description"
          rows="4"
          maxlength="500"
          class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
          placeholder="Description du véhicule..."
        ></textarea>
        <div class="flex justify-between items-center mt-1">
          <p v-if="errors.description" class="text-red-600 text-sm">{{ errors.description }}</p>
          <p class="text-xs text-slate-500 ml-auto">{{ form.description?.length || 0 }}/500 caractères</p>
        </div>
      </div>

      <!-- Caractéristiques/Features -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Caractéristiques (une par ligne)
        </label>
        <textarea
          v-model="featuresText"
          rows="4"
          class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
          :placeholder="placeholderFeatures"
          @input="updateFeatures"
        ></textarea>
        <p class="text-xs text-slate-500 mt-1">Séparez chaque caractéristique par un retour à la ligne</p>
        <p v-if="errors.features" class="text-red-600 text-sm mt-1">{{ errors.features }}</p>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          href="/owner/vehicles"
          class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition"
        >
          Annuler
        </Link>
        <button
          type="submit"
          :disabled="form.processing"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
        >
          {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
        </button>
      </div>
    </form>
    </div>
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

const props = withDefaults(
  defineProps<{
    vehicle?: {
      id: number | string;
      name?: string;
      brand?: string;
      model?: string;
      year?: number;
      type?: string;
      seats?: number;
      plateNumber?: string;
      plate_number?: string;
      pricePerDay?: number;
      price_per_day?: number;
      price?: number;
      color?: string;
      transmission?: string;
      fuel?: string;
      mileage?: number;
      description?: string;
      images?: string[];
      features?: string[];
    } | null;
    vehicleTypes?: Array<{ value: string; label: string }>;
  }>(),
  { vehicle: null }
);

// Objet véhicule sûr pour éviter les erreurs d'accès
const vehicle = computed(() => props.vehicle ?? {});

const vehicleDisplayName = computed(() => {
  const v = vehicle.value;
  if (v.name) return v.name;
  const part = [v.brand, v.model].filter(Boolean).join(' ');
  return part.trim() || 'Sans nom';
});

// Utiliser les types depuis l'API ou les types par défaut
const vehicleTypes = computed(() => props.vehicleTypes || [
  { value: 'berline', label: 'Berline' },
  { value: 'suv', label: 'SUV' },
  { value: '4x4', label: '4x4' },
  { value: 'utilitaire', label: 'Utilitaire' },
  { value: 'moto', label: 'Moto' },
]);

const form = useForm({
  name: props.vehicle?.name ?? '',
  brand: props.vehicle?.brand ?? '',
  model: props.vehicle?.model ?? '',
  year: props.vehicle?.year ?? new Date().getFullYear(),
  type: props.vehicle?.type ?? '',
  seats: props.vehicle?.seats ?? 5,
  plateNumber: props.vehicle?.plateNumber ?? props.vehicle?.plate_number ?? '',
  pricePerDay: props.vehicle?.pricePerDay ?? props.vehicle?.price_per_day ?? props.vehicle?.price ?? 0,
  color: props.vehicle?.color ?? '',
  transmission: props.vehicle?.transmission ?? '',
  fuel: props.vehicle?.fuel ?? '',
  mileage: props.vehicle?.mileage ?? 0,
  description: props.vehicle?.description ?? '',
  images: Array.isArray(props.vehicle?.images) ? [...props.vehicle.images] : [],
  features: Array.isArray(props.vehicle?.features) ? [...props.vehicle.features] : [],
});

const errors = computed(() => form.errors);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const imageErrors = ref<Record<number, boolean>>({});
const placeholderFeatures = 'Climatisation\nGPS\nSièges en cuir...';
const featuresText = ref(Array.isArray(props.vehicle?.features) ? props.vehicle.features.join('\n') : '');

const updateFeatures = () => {
  form.features = featuresText.value
    .split('\n')
    .map(f => f.trim())
    .filter(f => f.length > 0);
};

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  
  if (!file) return;
  
  uploading.value = true;
  
  try {
    const formData = new FormData();
    formData.append('image', file);
    
    const response = await axios.post('/owner/images/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    if (response.data.url) {
      form.images.push(response.data.url);
    }
  } catch (error) {
    console.error('Erreur lors de l\'upload:', error);
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
  if (!props.vehicle?.id) return;
  form.put(`/owner/vehicles/${props.vehicle.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      // Le message de succès sera affiché via flash
    },
    onError: () => {
      // Les erreurs seront affichées via form.errors
    },
  });
};
</script>

