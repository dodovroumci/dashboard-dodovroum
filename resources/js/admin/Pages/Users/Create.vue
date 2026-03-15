<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Créer un propriétaire</h1>
        <p class="text-sm text-slate-500 mt-1">Ajouter un nouveau propriétaire à la plateforme</p>
      </div>
      <Link
        :href="route('admin.users.index')"
        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
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
      <!-- Informations personnelles -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Informations personnelles</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Prénom *
            </label>
            <input
              v-model="form.firstName"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Prénom"
            />
            <p v-if="errors.firstName" class="text-red-600 text-sm mt-1">{{ errors.firstName }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nom *
            </label>
            <input
              v-model="form.lastName"
              type="text"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Nom"
            />
            <p v-if="errors.lastName" class="text-red-600 text-sm mt-1">{{ errors.lastName }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Email *
            </label>
            <input
              v-model="form.email"
              type="email"
              required
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="email@example.com"
            />
            <p v-if="errors.email" class="text-red-600 text-sm mt-1">{{ errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Téléphone
            </label>
            <input
              v-model="form.phone"
              type="tel"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="+225 01 23 45 67 89"
            />
            <p v-if="errors.phone" class="text-red-600 text-sm mt-1">{{ errors.phone }}</p>
          </div>
        </div>
      </div>

      <!-- Authentification -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Authentification</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Mot de passe *
            </label>
            <input
              v-model="form.password"
              type="password"
              required
              minlength="8"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Minimum 8 caractères"
            />
            <p v-if="errors.password" class="text-red-600 text-sm mt-1">{{ errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Confirmer le mot de passe *
            </label>
            <input
              v-model="form.password_confirmation"
              type="password"
              required
              minlength="8"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Répétez le mot de passe"
            />
            <p v-if="errors.password_confirmation" class="text-red-600 text-sm mt-1">{{ errors.password_confirmation }}</p>
          </div>
        </div>
      </div>

      <!-- Informations propriétaire -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Informations propriétaire</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Type de propriétaire
            </label>
            <select
              v-model="form.typeProprietaire"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sélectionner un type</option>
              <option value="Propriétaire individuel">Propriétaire individuel</option>
              <option value="Agence">Agence</option>
              <option value="Entreprise">Entreprise</option>
              <option value="Autre">Autre</option>
            </select>
            <p v-if="errors.typeProprietaire" class="text-red-600 text-sm mt-1">{{ errors.typeProprietaire }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Localisation
            </label>
            <input
              v-model="form.localisation"
              type="text"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Ex: Abidjan, Cocody"
            />
            <p v-if="errors.localisation" class="text-red-600 text-sm mt-1">{{ errors.localisation }}</p>
          </div>
        </div>
      </div>

      <!-- Statut -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Statut du compte</h2>
        <div class="space-y-4">
          <div class="flex items-center">
            <input
              v-model="form.isActive"
              type="checkbox"
              id="isActive"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded"
            />
            <label for="isActive" class="ml-2 block text-sm text-slate-700">
              Compte actif
            </label>
          </div>

          <div class="flex items-center">
            <input
              v-model="form.isVerified"
              type="checkbox"
              id="isVerified"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded"
            />
            <label for="isVerified" class="ml-2 block text-sm text-slate-700">
              Compte vérifié
            </label>
          </div>
          
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p class="text-sm text-blue-800">
              <span class="font-medium">Rôle :</span> Propriétaire (défini automatiquement)
            </p>
          </div>
        </div>
      </div>

      <!-- Adresse -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Adresse</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-3">
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Adresse
            </label>
            <input
              v-model="form.address"
              type="text"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="123 Rue de la Paix"
            />
            <p v-if="errors.address" class="text-red-600 text-sm mt-1">{{ errors.address }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Ville
            </label>
            <input
              v-model="form.city"
              type="text"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Abidjan"
            />
            <p v-if="errors.city" class="text-red-600 text-sm mt-1">{{ errors.city }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Pays
            </label>
            <input
              v-model="form.country"
              type="text"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Côte d'Ivoire"
            />
            <p v-if="errors.country" class="text-red-600 text-sm mt-1">{{ errors.country }}</p>
          </div>
        </div>
      </div>

      <!-- Vérification d'identité -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Vérification d'identité</h2>
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Type de pièce d'identité
              </label>
              <select
                v-model="form.identityType"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Sélectionner un type</option>
                <option value="CNI">CNI (Carte Nationale d'Identité)</option>
                <option value="PASSPORT">Passeport</option>
                <option value="PERMIS">Permis de conduire</option>
                <option value="AUTRE">Autre</option>
              </select>
              <p v-if="errors.identityType" class="text-red-600 text-sm mt-1">{{ errors.identityType }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Numéro de pièce d'identité
              </label>
              <input
                v-model="form.identityNumber"
                type="text"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                placeholder="Numéro de la pièce"
              />
              <p v-if="errors.identityNumber" class="text-red-600 text-sm mt-1">{{ errors.identityNumber }}</p>
            </div>
          </div>

          <!-- Documents (photos) -->
          <div>
            <h3 class="text-md font-semibold mb-3">Documents d'identité</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Photo recto -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Recto de la pièce *
                </label>
                <div v-if="form.identityPhotoFront" class="mb-2">
                  <img
                    :src="getStorageImageUrl(form.identityPhotoFront, 'residences')"
                    alt="Recto"
                    class="w-full h-32 object-cover rounded-lg border border-slate-200"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                  />
                  <button
                    type="button"
                    @click="form.identityPhotoFront = ''"
                    class="mt-2 text-sm text-red-600 hover:text-red-700"
                  >
                    Supprimer
                  </button>
                </div>
                <input
                  v-else
                  type="file"
                  accept="image/*"
                  @change="handleFileUpload($event, 'front')"
                  class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <p v-if="errors.identityPhotoFront" class="text-red-600 text-sm mt-1">{{ errors.identityPhotoFront }}</p>
                <p v-if="uploading.front" class="text-sm text-slate-500 mt-1">Upload en cours...</p>
              </div>

              <!-- Photo verso -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Verso de la pièce
                </label>
                <div v-if="form.identityPhotoBack" class="mb-2">
                  <img
                    :src="getStorageImageUrl(form.identityPhotoBack, 'residences')"
                    alt="Verso"
                    class="w-full h-32 object-cover rounded-lg border border-slate-200"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                  />
                  <button
                    type="button"
                    @click="form.identityPhotoBack = ''"
                    class="mt-2 text-sm text-red-600 hover:text-red-700"
                  >
                    Supprimer
                  </button>
                </div>
                <input
                  v-else
                  type="file"
                  accept="image/*"
                  @change="handleFileUpload($event, 'back')"
                  class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <p v-if="errors.identityPhotoBack" class="text-red-600 text-sm mt-1">{{ errors.identityPhotoBack }}</p>
                <p v-if="uploading.back" class="text-sm text-slate-500 mt-1">Upload en cours...</p>
              </div>

              <!-- Photo supplémentaire -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Document supplémentaire
                </label>
                <div v-if="form.identityPhotoExtra" class="mb-2">
                  <img
                    :src="getStorageImageUrl(form.identityPhotoExtra, 'residences')"
                    alt="Document supplémentaire"
                    class="w-full h-32 object-cover rounded-lg border border-slate-200"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                  />
                  <button
                    type="button"
                    @click="form.identityPhotoExtra = ''"
                    class="mt-2 text-sm text-red-600 hover:text-red-700"
                  >
                    Supprimer
                  </button>
                </div>
                <input
                  v-else
                  type="file"
                  accept="image/*"
                  @change="handleFileUpload($event, 'extra')"
                  class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <p v-if="errors.identityPhotoExtra" class="text-red-600 text-sm mt-1">{{ errors.identityPhotoExtra }}</p>
                <p v-if="uploading.extra" class="text-sm text-slate-500 mt-1">Upload en cours...</p>
              </div>
            </div>
          </div>

          <!-- Statut de vérification -->
          <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
            <p class="text-sm text-slate-700">
              <span class="font-medium">Statut de vérification :</span> 
              <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                En attente
              </span>
            </p>
            <p class="text-xs text-slate-500 mt-1">
              La vérification sera effectuée après la création du compte
            </p>
          </div>
        </div>
      </div>

      <!-- Boutons d'action -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          :href="route('admin.users.index')"
          class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
        >
          Annuler
        </Link>
        <button
          type="submit"
          :disabled="processing"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ processing ? 'Création...' : 'Créer le propriétaire' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { getStorageImageUrl } from '../../utils/imageUrl';

const props = defineProps<{
  errors?: Record<string, string>;
}>();

const form = useForm({
  firstName: '',
  lastName: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  role: 'proprietaire', // Par défaut propriétaire
  isActive: true,
  isVerified: false,
  address: '',
  city: '',
  country: '',
  localisation: '',
  typeProprietaire: '',
  // Vérification d'identité
  identityType: '',
  identityNumber: '',
  identityPhotoFront: '',
  identityPhotoBack: '',
  identityPhotoExtra: '',
});

const uploading = ref({
  front: false,
  back: false,
  extra: false,
});

const processing = computed(() => form.processing);

const handleFileUpload = async (event: Event, type: 'front' | 'back' | 'extra') => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  
  if (!file) return;

  // Vérifier la taille (5MB max)
  if (file.size > 5 * 1024 * 1024) {
    alert('Le fichier est trop volumineux. Taille maximale : 5MB');
    return;
  }

  uploading.value[type] = true;

  try {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('category', 'users');

    const response = await axios.post(route('admin.images.upload'), formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (response.data.success && response.data.url) {
      if (type === 'front') {
        form.identityPhotoFront = response.data.url;
      } else if (type === 'back') {
        form.identityPhotoBack = response.data.url;
      } else if (type === 'extra') {
        form.identityPhotoExtra = response.data.url;
      }
    } else {
      alert('Erreur lors de l\'upload de l\'image');
    }
  } catch (error: any) {
    console.error('Erreur upload:', error);
    alert('Erreur lors de l\'upload de l\'image: ' + (error.response?.data?.message || error.message));
  } finally {
    uploading.value[type] = false;
    target.value = '';
  }
};

const submit = () => {
  form.post(route('admin.users.store'));
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.users.index': '/admin/users',
    'admin.users.create': '/admin/users/create',
    'admin.users.store': '/admin/users',
    'admin.users.show': (id: string) => `/admin/users/${id}`,
    'admin.users.edit': (id: string) => `/admin/users/${id}/edit`,
    'admin.images.upload': '/admin/images/upload',
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};

const errors = computed(() => props.errors || {});
</script>

