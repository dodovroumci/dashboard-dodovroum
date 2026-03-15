<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Paramètres</h1>
        <p class="text-sm text-slate-500 mt-1">Configuration de l'application</p>
      </div>
    </div>

    <!-- Messages de succès/erreur -->
    <div v-if="$page.props.flash?.success" class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ $page.props.flash.error }}
    </div>

    <!-- Configuration API -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
      <h2 class="text-lg font-semibold text-slate-900 mb-4">Configuration API</h2>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            URL de l'API
          </label>
          <input
            type="text"
            :value="settings.api_url"
            disabled
            class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500"
          />
          <p class="mt-1 text-xs text-slate-500">L'URL de l'API est configurée dans le fichier .env</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            Email administrateur
          </label>
          <input
            type="email"
            :value="settings.admin_email"
            disabled
            class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500"
          />
          <p class="mt-1 text-xs text-slate-500">L'email administrateur est configuré dans le fichier .env</p>
        </div>
      </div>
    </div>

    <!-- Test de connexion API -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
      <h2 class="text-lg font-semibold text-slate-900 mb-4">Tester la connexion API</h2>
      <p class="text-sm text-slate-600 mb-4">
        Testez de nouveaux identifiants pour l'API. Si le test réussit, mettez à jour le fichier <code class="bg-slate-100 px-1 rounded">.env</code> avec ces identifiants.
      </p>
      <form @submit.prevent="testConnection" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            Email administrateur
          </label>
          <input
            v-model="testEmail"
            type="email"
            required
            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="admin@dodovroum.com"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            Mot de passe administrateur
          </label>
          <input
            v-model="testPassword"
            type="password"
            required
            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Entrez le mot de passe"
          />
        </div>
        <div class="flex items-center gap-3">
          <button
            type="submit"
            :disabled="isTesting"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isTesting ? 'Test en cours...' : 'Tester la connexion' }}
          </button>
        </div>
        <div v-if="testResult" class="mt-4 p-4 rounded-lg" :class="testResult.success ? 'bg-emerald-100 border border-emerald-400 text-emerald-700' : 'bg-red-100 border border-red-400 text-red-700'">
          <p class="font-medium">{{ testResult.success ? '✓ Connexion réussie !' : '✗ Échec de la connexion' }}</p>
          <p class="text-sm mt-1">{{ testResult.message }}</p>
          <div v-if="testResult.success" class="mt-3 p-3 bg-white rounded border border-emerald-300">
            <p class="text-xs font-medium text-slate-700 mb-2">Mettez à jour votre fichier .env :</p>
            <code class="text-xs block text-slate-800">
              DODOVROUM_ADMIN_EMAIL={{ testEmail }}<br>
              DODOVROUM_ADMIN_PASSWORD={{ testPassword }}
            </code>
          </div>
        </div>
      </form>
    </div>

    <!-- Informations système -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
      <h2 class="text-lg font-semibold text-slate-900 mb-4">Informations système</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Version PHP
          </label>
          <p class="text-sm text-slate-600">{{ phpVersion }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Version Laravel
          </label>
          <p class="text-sm text-slate-600">{{ laravelVersion }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Environnement
          </label>
          <p class="text-sm text-slate-600">{{ environment }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Mode debug
          </label>
          <p class="text-sm text-slate-600">{{ debugMode ? 'Activé' : 'Désactivé' }}</p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
      <h2 class="text-lg font-semibold text-slate-900 mb-4">Actions</h2>
      <div class="space-y-3">
        <button
          @click="clearCache"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Vider le cache
        </button>
        <p class="text-xs text-slate-500">Vide le cache de l'application et des tokens API</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
  settings: {
    api_url: string;
    admin_email: string;
  };
}>();

const phpVersion = ref('8.2+');
const laravelVersion = ref('12.0');
const environment = ref('local');
const debugMode = ref(true);

const testEmail = ref(props.settings.admin_email);
const testPassword = ref('');
const isTesting = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);

const testConnection = () => {
  isTesting.value = true;
  testResult.value = null;

  router.post('/admin/settings/test-connection', {
    email: testEmail.value,
    password: testPassword.value,
  }, {
    preserveState: true,
    onSuccess: (page) => {
      isTesting.value = false;
      if (page.props.flash?.success) {
        testResult.value = {
          success: true,
          message: page.props.flash.success,
        };
      } else if (page.props.errors?.error) {
        testResult.value = {
          success: false,
          message: page.props.errors.error,
        };
      }
    },
    onError: (errors) => {
      isTesting.value = false;
      testResult.value = {
        success: false,
        message: errors.error || 'Erreur lors du test de connexion',
      };
    },
  });
};

const clearCache = () => {
  if (confirm('Êtes-vous sûr de vouloir vider le cache ?')) {
    router.post('/admin/settings/clear-cache', {}, {
      preserveState: false,
      onSuccess: () => {
        alert('Cache vidé avec succès');
      },
      onError: () => {
        alert('Erreur lors du vidage du cache');
      },
    });
  }
};
</script>

