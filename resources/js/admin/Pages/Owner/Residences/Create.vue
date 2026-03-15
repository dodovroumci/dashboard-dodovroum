<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Ajouter une résidence</h1>
        <p class="text-sm text-slate-500 mt-1">Créer une nouvelle résidence</p>
      </div>
      <Link
        href="/owner/residences"
        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
      >
        Annuler
      </Link>
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
              placeholder="ex: Villa de luxe à Cocody"
            />
            <p v-if="errors.title" class="text-red-600 text-sm mt-1">{{ errors.title }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Type *
            </label>
            <select
              v-model="form.typeResidence"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner un type</option>
              <option value="villa">Villa</option>
              <option value="appartement">Appartement</option>
              <option value="maison">Maison</option>
              <option value="studio">Studio</option>
            </select>
            <p v-if="errors.typeResidence" class="text-red-600 text-sm mt-1">{{ errors.typeResidence }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Adresse *
            </label>
            <input
              v-model="form.address"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="123 Rue de la Paix"
            />
            <p v-if="errors.address" class="text-red-600 text-sm mt-1">{{ errors.address }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Ville *
            </label>
            <input
              v-model="form.city"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Abidjan"
            />
            <p v-if="errors.city" class="text-red-600 text-sm mt-1">{{ errors.city }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Pays *
            </label>
            <input
              v-model="form.country"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Côte d'Ivoire"
            />
            <p v-if="errors.country" class="text-red-600 text-sm mt-1">{{ errors.country }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Prix par nuit (CFA) *
            </label>
            <input
              v-model.number="form.pricePerNight"
              type="number"
              required
              min="0"
              step="0.01"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="50000"
            />
            <p v-if="errors.pricePerNight" class="text-red-600 text-sm mt-1">{{ errors.pricePerNight }}</p>
          </div>
        </div>
      </div>


      <!-- Équipements -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Équipements</h2>
        <div class="space-y-2">
          <div class="flex flex-wrap gap-2">
            <label
              v-for="amenity in availableAmenities"
              :key="amenity"
              class="flex items-center px-3 py-2 border border-slate-300 rounded-lg cursor-pointer hover:bg-slate-50"
              :class="form.amenities.includes(amenity) ? 'bg-blue-50 border-blue-500' : ''"
            >
              <input
                type="checkbox"
                :value="amenity"
                v-model="form.amenities"
                class="mr-2"
              />
              <span>{{ amenity }}</span>
            </label>
          </div>
          <input
            type="text"
            v-model="newAmenity"
            @keyup.enter="addAmenity"
            placeholder="Ajouter un équipement (Entrée pour ajouter)"
            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
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
                  :src="getStorageImageUrl(image, 'residences')"
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

      <!-- Statuts -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Statuts</h2>
        <div class="space-y-3">
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="form.isActive"
              class="mr-2"
            />
            <span class="text-sm font-medium text-slate-700">Résidence active</span>
          </label>
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="form.isVerified"
              class="mr-2"
            />
            <span class="text-sm font-medium text-slate-700">Résidence vérifiée</span>
          </label>
        </div>
      </div>

      <!-- Caractéristiques -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Caractéristiques</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nombre de chambres *
            </label>
            <input
              v-model.number="form.bedrooms"
              type="number"
              required
              min="0"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="errors.bedrooms" class="text-red-600 text-sm mt-1">{{ errors.bedrooms }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nombre de salles de bain *
            </label>
            <input
              v-model.number="form.bathrooms"
              type="number"
              required
              min="0"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="errors.bathrooms" class="text-red-600 text-sm mt-1">{{ errors.bathrooms }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Capacité (personnes) *
            </label>
            <input
              v-model.number="form.capacity"
              type="number"
              required
              min="1"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="errors.capacity" class="text-red-600 text-sm mt-1">{{ errors.capacity }}</p>
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
          placeholder="Description de la résidence..."
        ></textarea>
        <div class="flex justify-between items-center mt-1">
          <p v-if="errors.description" class="text-red-600 text-sm">{{ errors.description }}</p>
          <p class="text-xs text-slate-500 ml-auto">{{ form.description?.length || 0 }}/500 caractères</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          href="/owner/residences"
          class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
        >
          Annuler
        </Link>
        <button
          type="submit"
          :disabled="processing"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {{ processing ? 'Création...' : 'Créer la résidence' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { getStorageImageUrl } from '../../../utils/imageUrl';
import OwnerLayout from '../../../Components/Layouts/OwnerLayout.vue';

defineOptions({
  layout: OwnerLayout,
});

const form = useForm({
  title: '',
  typeResidence: '',
  address: '',
  city: '',
  country: '',
  pricePerNight: 0,
  bedrooms: 0,
  bathrooms: 0,
  capacity: 1,
  description: '',
  images: [],
  amenities: [],
  isActive: true,
  isVerified: false,
});

const availableAmenities = [
  'WiFi',
  'Piscine',
  'Parking',
  'Climatisation',
  'Chauffage',
  'Cuisine équipée',
  'Lave-linge',
  'Télévision',
  'Jardin',
  'Terrasse',
  'Balcon',
  'Ascenseur',
  'Salle de sport',
  'Spa',
];

const newAmenity = ref('');
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const imageErrors = ref<Record<number, boolean>>({});

const addAmenity = () => {
  if (newAmenity.value.trim() && !form.amenities.includes(newAmenity.value.trim())) {
    form.amenities.push(newAmenity.value.trim());
    newAmenity.value = '';
  }
};

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  
  if (!file) return;

  // Vérifier la taille (5MB max)
  if (file.size > 5 * 1024 * 1024) {
    alert('Le fichier est trop volumineux. Taille maximale : 5MB');
    return;
  }

  uploading.value = true;

  try {
    const formData = new FormData();
    formData.append('image', file);

    const response = await axios.post('/owner/images/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (response.data.success && response.data.url) {
      form.images.push(response.data.url);
    } else {
      alert('Erreur lors de l\'upload de l\'image');
    }
  } catch (error: any) {
    console.error('Erreur upload:', error);
    alert('Erreur lors de l\'upload de l\'image: ' + (error.response?.data?.message || error.message));
  } finally {
    uploading.value = false;
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  }
};


const handleImageError = (index: number) => {
  // Marquer l'image comme ayant une erreur de chargement
  imageErrors.value[index] = true;
  console.warn(`Image ${index} ne peut pas être chargée:`, form.images[index]);
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
  form.post('/owner/residences', {
    onSuccess: () => {
      // Redirection gérée par le contrôleur
    },
  });
};

const errors = form.errors;
const processing = form.processing;

</script>

