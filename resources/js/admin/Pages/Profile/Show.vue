<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Mon profil</h1>
        <p class="text-sm text-slate-500 mt-1">Gérez vos informations personnelles</p>
      </div>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ error }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Informations principales -->
      <div class="lg:col-span-2">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <h2 class="text-lg font-semibold text-slate-900 mb-6">Informations personnelles</h2>
          
          <form @submit.prevent="submitForm" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Prénom -->
              <div>
                <label for="firstName" class="block text-sm font-medium text-slate-700 mb-2">
                  Prénom
                </label>
                <input
                  id="firstName"
                  v-model="form.firstName"
                  type="text"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Votre prénom"
                />
                <div v-if="form.errors.firstName" class="mt-1 text-sm text-red-600">
                  {{ form.errors.firstName }}
                </div>
              </div>

              <!-- Nom -->
              <div>
                <label for="lastName" class="block text-sm font-medium text-slate-700 mb-2">
                  Nom
                </label>
                <input
                  id="lastName"
                  v-model="form.lastName"
                  type="text"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Votre nom"
                />
                <div v-if="form.errors.lastName" class="mt-1 text-sm text-red-600">
                  {{ form.errors.lastName }}
                </div>
              </div>
            </div>

            <!-- Email (lecture seule) -->
            <div>
              <label for="email" class="block text-sm font-medium text-slate-700 mb-2">
                Email
              </label>
              <input
                id="email"
                :value="user?.email ?? ''"
                type="email"
                disabled
                class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500 cursor-not-allowed"
              />
              <p class="mt-1 text-xs text-slate-500">L'email ne peut pas être modifié</p>
            </div>

            <!-- Téléphone -->
            <div>
              <label for="phone" class="block text-sm font-medium text-slate-700 mb-2">
                Téléphone
              </label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="+33 6 12 34 56 78"
              />
              <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                {{ form.errors.phone }}
              </div>
            </div>

            <!-- Adresse -->
            <div>
              <label for="address" class="block text-sm font-medium text-slate-700 mb-2">
                Adresse
              </label>
              <input
                id="address"
                v-model="form.address"
                type="text"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="123 Rue de la République"
              />
              <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">
                {{ form.errors.address }}
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Ville -->
              <div>
                <label for="city" class="block text-sm font-medium text-slate-700 mb-2">
                  Ville
                </label>
                <input
                  id="city"
                  v-model="form.city"
                  type="text"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Paris"
                />
                <div v-if="form.errors.city" class="mt-1 text-sm text-red-600">
                  {{ form.errors.city }}
                </div>
              </div>

              <!-- Pays -->
              <div>
                <label for="country" class="block text-sm font-medium text-slate-700 mb-2">
                  Pays
                </label>
                <input
                  id="country"
                  v-model="form.country"
                  type="text"
                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="France"
                />
                <div v-if="form.errors.country" class="mt-1 text-sm text-red-600">
                  {{ form.errors.country }}
                </div>
              </div>
            </div>

            <!-- Boutons -->
            <div class="flex gap-3 pt-4">
              <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="form.processing">Enregistrement...</span>
                <span v-else>Enregistrer les modifications</span>
              </button>
              <button
                type="button"
                @click="resetForm"
                class="px-6 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6 mt-6">
          <h2 class="text-lg font-semibold text-slate-900 mb-2">Sécurité</h2>
          <p class="text-sm text-slate-500 mb-6">Changez votre mot de passe de connexion au tableau de bord.</p>

          <form @submit.prevent="submitPasswordForm" class="space-y-6">
            <div>
              <label for="current_password" class="block text-sm font-medium text-slate-700 mb-2">
                Mot de passe actuel
              </label>
              <input
                id="current_password"
                v-model="passwordForm.current_password"
                type="password"
                autocomplete="current-password"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <div v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">
                {{ passwordForm.errors.current_password }}
              </div>
            </div>

            <div>
              <label for="new_password" class="block text-sm font-medium text-slate-700 mb-2">
                Nouveau mot de passe
              </label>
              <input
                id="new_password"
                v-model="passwordForm.password"
                type="password"
                autocomplete="new-password"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p class="mt-1 text-xs text-slate-500">Au moins 8 caractères</p>
              <div v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">
                {{ passwordForm.errors.password }}
              </div>
            </div>

            <div>
              <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-2">
                Confirmer le nouveau mot de passe
              </label>
              <input
                id="password_confirmation"
                v-model="passwordForm.password_confirmation"
                type="password"
                autocomplete="new-password"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <div v-if="passwordForm.errors.password_confirmation" class="mt-1 text-sm text-red-600">
                {{ passwordForm.errors.password_confirmation }}
              </div>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                type="submit"
                :disabled="passwordForm.processing"
                class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="passwordForm.processing">Mise à jour...</span>
                <span v-else>Mettre à jour le mot de passe</span>
              </button>
              <button
                type="button"
                class="px-6 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                @click="resetPasswordForm"
              >
                Effacer
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Informations complémentaires -->
      <div class="space-y-6">
        <!-- Rôle -->
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <h3 class="text-sm font-medium text-slate-500 mb-2">Rôle</h3>
          <p class="text-lg font-semibold text-slate-900 capitalize">
            {{ roleLabel }}
          </p>
        </div>

        <!-- Date de création -->
        <div v-if="user?.createdAt" class="bg-white border border-slate-200 rounded-xl p-6">
          <h3 class="text-sm font-medium text-slate-500 mb-2">Membre depuis</h3>
          <p class="text-lg font-semibold text-slate-900">
            {{ formatDate(user.createdAt) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ProfileLayout from '../../Components/Layouts/ProfileLayout.vue';

defineOptions({
  layout: ProfileLayout,
});

const props = withDefaults(
  defineProps<{
    user?: {
      id?: string | number;
      email?: string;
      name?: string;
      firstName?: string;
      lastName?: string;
      phone?: string;
      address?: string;
      city?: string;
      country?: string;
      role?: string;
      createdAt?: string;
      updatedAt?: string;
    };
    error?: string;
  }>(),
  { user: () => ({}) }
);

const user = computed(() => props.user ?? {});

const form = useForm({
  firstName: user.value.firstName ?? '',
  lastName: user.value.lastName ?? '',
  phone: user.value.phone ?? '',
  address: user.value.address ?? '',
  city: user.value.city ?? '',
  country: user.value.country ?? '',
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

watch(
  () => props.user,
  (u) => {
    if (!u) return;
    form.firstName = u.firstName ?? '';
    form.lastName = u.lastName ?? '';
    form.phone = u.phone ?? '';
    form.address = u.address ?? '';
    form.city = u.city ?? '';
    form.country = u.country ?? '';
  },
  { deep: true }
);

const error = computed(() => props.error);

const roleLabel = computed(() => {
  const r = (user.value.role ?? '').toLowerCase();
  if (r === 'admin' || r === 'administrator') return 'Administrateur';
  if (r === 'owner' || r === 'proprietaire' || r === 'propriétaire') return 'Propriétaire';
  return 'Client';
});

const submitForm = () => {
  form.put('/profile', {
    preserveScroll: true,
    onSuccess: () => {
      // Le message de succès sera affiché via flash
    },
  });
};

const submitPasswordForm = () => {
  passwordForm.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => {
      passwordForm.reset();
      passwordForm.clearErrors();
    },
  });
};

const resetPasswordForm = () => {
  passwordForm.reset();
  passwordForm.clearErrors();
};

const resetForm = () => {
  const u = user.value;
  form.firstName = u.firstName ?? '';
  form.lastName = u.lastName ?? '';
  form.phone = u.phone ?? '';
  form.address = u.address ?? '';
  form.city = u.city ?? '';
  form.country = u.country ?? '';
  form.clearErrors();
};

const formatDate = (dateString: string): string => {
  if (!dateString) return '';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  } catch (e) {
    return dateString;
  }
};
</script>

