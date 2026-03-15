<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Modifier l'utilisateur</h1>
        <p class="text-sm text-slate-500 mt-1">{{ user.name }}</p>
      </div>
      <Link
        :href="route('admin.users.show', user.id)"
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
              Prénom
            </label>
            <input
              v-model="form.firstName"
              type="text"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Prénom"
            />
            <p v-if="errors.firstName" class="text-red-600 text-sm mt-1">{{ errors.firstName }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nom
            </label>
            <input
              v-model="form.lastName"
              type="text"
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
              placeholder="+33 1 23 45 67 89"
            />
            <p v-if="errors.phone" class="text-red-600 text-sm mt-1">{{ errors.phone }}</p>
          </div>
        </div>
      </div>

      <!-- Rôle et statut -->
      <div>
        <h2 class="text-lg font-semibold mb-4">Rôle et statut</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Rôle
            </label>
            <select
              v-model="form.role"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="admin">Admin</option>
              <option value="proprietaire">Propriétaire</option>
              <option value="client">Client</option>
            </select>
            <p v-if="errors.role" class="text-red-600 text-sm mt-1">{{ errors.role }}</p>
          </div>

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

      <!-- Boutons d'action -->
      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <Link
          :href="route('admin.users.show', user.id)"
          class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
        >
          Annuler
        </Link>
        <button
          type="submit"
          :disabled="processing"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ processing ? 'Enregistrement...' : 'Enregistrer les modifications' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  user: {
    id: string;
    firstName: string;
    lastName: string;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    isVerified: boolean;
    isActive: boolean;
    address: string | null;
    city: string | null;
    country: string | null;
  };
  errors?: Record<string, string>;
}>();

const form = useForm({
  firstName: props.user.firstName || '',
  lastName: props.user.lastName || '',
  email: props.user.email,
  phone: props.user.phone || '',
  role: props.user.role === 'user' ? 'client' : props.user.role,
  isActive: props.user.isActive,
  isVerified: props.user.isVerified,
  address: props.user.address || '',
  city: props.user.city || '',
  country: props.user.country || '',
});

const processing = computed(() => form.processing);

const submit = () => {
  form.put(route('admin.users.update', props.user.id));
};

const route = (name: string, params?: any): string => {
  const routes: Record<string, any> = {
    'admin.users.index': '/admin/users',
    'admin.users.show': (id: string) => `/admin/users/${id}`,
    'admin.users.edit': (id: string) => `/admin/users/${id}/edit`,
    'admin.users.update': (id: string) => `/admin/users/${id}`,
  };

  if (typeof routes[name] === 'function') {
    return routes[name](params);
  }
  return routes[name] || '#';
};

const errors = computed(() => props.errors || {});
</script>

