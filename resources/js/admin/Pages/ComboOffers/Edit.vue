<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Modifier l'offre combinée</h1>
        <p class="text-sm text-slate-500 mt-1">{{ comboOffer?.title || comboOffer?.name || 'Offre sans nom' }}</p>
      </div>
      <Link
        :href="route('admin.combo-offers.index')"
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
              Prix original (CFA) *
            </label>
            <input
              v-model.number="form.originalPrice"
              type="number"
              required
              min="0"
              step="0.01"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="150000"
            />
            <p v-if="errors.originalPrice" class="text-red-600 text-sm mt-1">{{ errors.originalPrice }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Prix réduit (CFA) *
            </label>
            <input
              v-model.number="form.discountedPrice"
              type="number"
              required
              min="0"
              step="0.01"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="135000"
            />
            <p v-if="errors.discountedPrice" class="text-red-600 text-sm mt-1">{{ errors.discountedPrice }}</p>
            <p class="text-xs text-slate-500 mt-1">
              Réduction: {{ calculateDiscount() }}%
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nombre de jours
            </label>
            <input
              v-model.number="form.nbJours"
              type="number"
              min="1"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="7"
            />
            <p v-if="errors.nbJours" class="text-red-600 text-sm mt-1">{{ errors.nbJours }}</p>
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
                  :src="getStorageImageUrl(image)"
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
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner une résidence</option>
              <option
                v-for="residence in residences"
                :key="residence.id"
                :value="residence.id"
              >
                {{ residence.nom || residence.name || residence.title || 'Résidence sans nom' }} - {{ formatPrice(residence.prixParNuit || residence.pricePerNight || residence.price || 0) }} CFA/nuit
              </option>
            </select>
            <p v-if="errors.residenceId" class="text-red-600 text-sm mt-1">{{ errors.residenceId }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Véhicule *
            </label>
            <select
              v-model="form.vehicleId"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner un véhicule</option>
              <option
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                :value="vehicle.id"
              >
                {{ vehicle.titre || vehicle.name || `${vehicle.marque || ''} ${vehicle.modele || ''}`.trim() || 'Véhicule sans nom' }} - {{ formatPrice(vehicle.prixParJour || vehicle.pricePerDay || vehicle.price || 0) }} CFA/jour
              </option>
            </select>
            <p v-if="errors.vehicleId" class="text-red-600 text-sm mt-1">{{ errors.vehicleId }}</p>
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
          {{ processing ? 'Mise à jour...' : 'Mettre à jour' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { getStorageImageUrl } from '../../utils/imageUrl';

const props = defineProps<{
  comboOffer: {
    id: number;
    title?: string;
    name?: string;
    description?: string;
    residenceId?: string;
    residence?: any;
    vehicleId?: string;
    vehicle?: any;
    originalPrice?: number;
    discountedPrice?: number;
    discount?: number;
    discountPercentage?: number;
    nbJours?: number;
    imageUrl?: string;
    images?: string[];
    startDate?: string;
    endDate?: string;
    isActive?: boolean;
    isVerified?: boolean;
  };
  residences?: Array<any>;
  vehicles?: Array<any>;
}>();

// Initialiser les images : utiliser images si présent, sinon imageUrl
const initialImages = props.comboOffer?.images && Array.isArray(props.comboOffer.images) && props.comboOffer.images.length > 0
  ? props.comboOffer.images
  : (props.comboOffer?.imageUrl ? [props.comboOffer.imageUrl] : []);

const form = useForm({
  title: props.comboOffer?.title || props.comboOffer?.name || '',
  description: props.comboOffer?.description || '',
  residenceId: props.comboOffer?.residenceId || props.comboOffer?.residence?.id || '',
  vehicleId: props.comboOffer?.vehicleId || props.comboOffer?.vehicle?.id || '',
  originalPrice: props.comboOffer?.originalPrice || 0,
  discountedPrice: props.comboOffer?.discountedPrice || 0,
  discount: props.comboOffer?.discount || props.comboOffer?.discountPercentage || 0,
  nbJours: props.comboOffer?.nbJours || null,
  imageUrl: props.comboOffer?.imageUrl || '',
  images: initialImages,
  startDate: props.comboOffer?.startDate ? props.comboOffer.startDate.split('T')[0] : '',
  endDate: props.comboOffer?.endDate ? props.comboOffer.endDate.split('T')[0] : '',
  isActive: props.comboOffer?.isActive ?? true,
  isVerified: props.comboOffer?.isVerified ?? false,
});

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

const submit = () => {
  form.put(route('admin.combo-offers.update', props.comboOffer.id), {
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

const calculateDiscount = (): number => {
  if (!form.originalPrice || form.originalPrice === 0) return 0;
  const discount = ((form.originalPrice - form.discountedPrice) / form.originalPrice) * 100;
  return Math.round(discount * 100) / 100;
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.combo-offers.index': '/admin/combo-offers',
    'admin.combo-offers.update': (id: number) => `/admin/combo-offers/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};
</script>

