<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Ajouter un véhicule</h1>
        <p class="text-sm text-slate-500 mt-1">Créer un nouveau véhicule</p>
      </div>
      <Link
        :href="route('admin.vehicles.index')"
        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
      >
        Annuler
      </Link>
    </div>

    <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-6">
      <!-- Message d'erreur général -->
      <div v-if="errors.error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <p class="font-medium">{{ errors.error }}</p>
      </div>
      
      <!-- Affichage de toutes les erreurs de validation -->
      <div v-if="Object.keys(errors).length > 0 && !errors.error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <p class="font-medium mb-2">Erreurs de validation :</p>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(error, field) in errors" :key="field">
            <strong>{{ field }}</strong>: {{ Array.isArray(error) ? error[0] : error }}
          </li>
        </ul>
      </div>
      
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner un type</option>
              <option
                v-for="vehicleType in filteredVehicleTypes"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase"
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
              min="1"
              step="1"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="5"
            />
            <p v-if="errors.seats" class="text-red-600 text-sm mt-1">{{ errors.seats }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Propriétaire *
            </label>
            <select
              v-model="form.proprietaireId"
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
            <p v-if="errors.proprietaireId" class="text-red-600 text-sm mt-1">{{ errors.proprietaireId }}</p>
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner</option>
              <option value="manuel">Manuelle</option>
              <option value="automatique">Automatique</option>
              <option value="automatic">Automatique (EN)</option>
            </select>
            <p v-if="errors.transmission" class="text-red-600 text-sm mt-1">{{ errors.transmission }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Carburant
            </label>
            <select
              v-model="form.fuel"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner</option>
              <option value="essence">Essence</option>
              <option value="gasoline">Essence (EN)</option>
              <option value="diesel">Diesel</option>
              <option value="electrique">Électrique</option>
              <option value="electric">Électrique (EN)</option>
              <option value="hybride">Hybride</option>
              <option value="hybrid">Hybride (EN)</option>
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
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="50000"
            />
            <p v-if="errors.mileage" class="text-red-600 text-sm mt-1">{{ errors.mileage }}</p>
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
          placeholder="Description du véhicule..."
        ></textarea>
        <div class="flex justify-between items-center mt-1">
          <p v-if="errors.description" class="text-red-600 text-sm">{{ errors.description }}</p>
          <p class="text-xs text-slate-500 ml-auto">{{ form.description?.length || 0 }}/500 caractères</p>
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

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          :href="route('admin.vehicles.index')"
          :class="form.processing ? 'pointer-events-none opacity-50' : ''"
          class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition-opacity"
        >
          Annuler
        </Link>
        <button
          type="submit"
          :disabled="form.processing"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2"
        >
          <svg
            v-if="form.processing"
            class="animate-spin h-4 w-4 text-white"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
          <span v-if="form.processing">Enregistrement en cours...</span>
          <span v-else>Enregistrer le véhicule</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { getStorageImageUrl } from '../../utils/imageUrl';

const props = defineProps<{
  vehicleTypes?: Array<{ value: string; label: string }>;
  owners?: Array<{ id: string; name: string; email?: string }>;
}>();

// Utiliser les types depuis l'API ou les types par défaut
const vehicleTypes = props.vehicleTypes || [
  { value: 'berline', label: 'Berline' },
  { value: 'suv', label: 'SUV' },
  { value: '4x4', label: '4x4' },
  { value: 'utilitaire', label: 'Utilitaire' },
  { value: 'moto', label: 'Moto' },
];

// Types acceptés par la validation
const acceptedTypes = ['berline', 'suv', '4x4', 'utilitaire', 'moto'];

// Mapper les variantes possibles
const typeMap: Record<string, string> = {
  'berline': 'berline',
  'suv': 'suv',
  '4x4': '4x4',
  '4 x 4': '4x4',
  'utilitaire': 'utilitaire',
  'moto': 'moto',
  'motorcycle': 'moto',
  'sedan': 'berline',
  'car': 'berline',
};

// Filtrer les types pour ne garder que ceux acceptés
const filteredVehicleTypes = computed(() => {
  return vehicleTypes.filter(type => {
    const normalizedValue = typeMap[type.value.toLowerCase()] || type.value.toLowerCase();
    return acceptedTypes.includes(normalizedValue);
  });
});

// Utiliser les propriétaires depuis les props
const owners = props.owners || [];

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

const form = useForm({
  name: '',
  brand: '',
  model: '',
  year: new Date().getFullYear(),
  type: '',
  seats: 5,
  plateNumber: '',
  pricePerDay: null as number | null,
  color: '',
  transmission: '',
  fuel: '',
  mileage: 0,
  description: '',
  images: [],
  features: [],
  proprietaireId: '',
});

const submit = (event?: Event) => {
  // Empêcher le comportement par défaut du formulaire
  if (event) {
    event.preventDefault();
  }
  
  console.log('Soumission du formulaire véhicule', {
    formData: form.data(),
    errors: form.errors,
    owners: owners.length,
    proprietaireId: form.proprietaireId,
    processing: form.processing,
  });
  
  // Générer automatiquement le nom si vide (marque + modèle + année)
  if (!form.name || form.name.trim() === '') {
    const parts = [];
    if (form.brand && form.brand.trim()) parts.push(form.brand.trim());
    if (form.model && form.model.trim()) parts.push(form.model.trim());
    if (form.year) parts.push(form.year.toString());
    if (parts.length > 0) {
      form.name = parts.join(' ');
    }
  }
  
  // Vérifier que tous les champs requis sont remplis
  const missingFields = [];
  if (!form.name || form.name.trim() === '') missingFields.push('nom du véhicule');
  if (!form.brand || form.brand.trim() === '') missingFields.push('marque');
  if (!form.model || form.model.trim() === '') missingFields.push('modèle');
  if (!form.type || form.type.trim() === '') missingFields.push('type');
  if (!form.proprietaireId || form.proprietaireId.trim() === '') missingFields.push('propriétaire');
  if (form.pricePerDay === null || form.pricePerDay === undefined || !form.pricePerDay || form.pricePerDay <= 0) missingFields.push('prix par jour');
  if (!form.plateNumber || form.plateNumber.trim() === '') missingFields.push('numéro de plaque');
  if (!form.year || form.year < 1900) missingFields.push('année');
  if (!form.seats || form.seats < 1) missingFields.push('nombre de places');
  
  if (missingFields.length > 0) {
    console.error('Champs requis manquants', {
      missingFields,
      formData: form.data(),
    });
    alert(`Veuillez remplir tous les champs obligatoires : ${missingFields.join(', ')}`);
    return;
  }
  
  // Normaliser le type en minuscules avant l'envoi
  if (form.type) {
    const originalType = form.type;
    form.type = form.type.toLowerCase().trim();
    form.type = typeMap[form.type] || form.type;
    
    console.log('Normalisation type véhicule', {
      original: originalType,
      normalized: form.type,
      allTypes: filteredVehicleTypes.value.map(t => t.value),
      acceptedTypes: acceptedTypes,
    });
  }
  
  const routeUrl = route('admin.vehicles.store');
  console.log('URL de la route', routeUrl);
  console.log('Données du formulaire avant envoi', {
    type: form.type,
    allData: form.data(),
  });
  
  form.post(routeUrl, {
    onBefore: () => {
      // Validation finale avant l'envoi pour éviter les requêtes inutiles
      if (!form.proprietaireId) {
        alert('Veuillez sélectionner un propriétaire');
        return false;
      }
      
      if (!form.brand || !form.model) {
        alert('Veuillez remplir la marque et le modèle du véhicule');
        return false;
      }
      
      if (!form.type) {
        alert('Veuillez sélectionner un type de véhicule');
        return false;
      }
      
      if (!form.pricePerDay || form.pricePerDay < 1) {
        alert('Veuillez saisir un prix par jour valide (minimum 1 CFA)');
        return false;
      }
      
      // Si toutes les validations passent, continuer avec l'envoi
      return true;
    },
    onSuccess: () => {
      console.log('Véhicule créé avec succès');
      // Redirection gérée par le contrôleur
    },
    onError: (errors) => {
      console.error('Erreurs lors de la création du véhicule', errors);
      console.error('Détails des erreurs', JSON.stringify(errors, null, 2));
    },
    onFinish: () => {
      console.log('Soumission terminée');
    },
  });
};

const errors = form.errors;

const route = (name: string, params?: any): string => {
  const routes: Record<string, string> = {
    'admin.vehicles.index': '/admin/vehicles',
    'admin.vehicles.store': '/admin/vehicles', // POST vers /admin/vehicles
    'admin.vehicles.create': '/admin/vehicles/create',
    'admin.images.upload': '/admin/images/upload',
  };
  
  let routePath = routes[name] || '#';
  
  // Remplacer les paramètres si fournis
  if (params && typeof params === 'object') {
    Object.keys(params).forEach(key => {
      routePath = routePath.replace(`:${key}`, params[key]);
    });
  }
  
  console.log('Route appelée', { name, routePath, params });
  return routePath;
};
</script>

