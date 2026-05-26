<template>
  <div class="auth-root">
    <!-- LEFT PANEL — branding -->
    <div class="brand-panel">
      <!-- Animated gradient blobs -->
      <div class="blob blob-1" />
      <div class="blob blob-2" />
      <div class="blob blob-3" />

      <!-- Grid overlay -->
      <div class="grid-overlay" />

      <div class="brand-content">
        <!-- Logo card -->
        <div class="logo-card" :class="{ 'logo-card--visible': mounted }">
          <img :src="logoUrl" alt="DodoVroum" class="logo-img" />
        </div>

        <!-- Tagline -->
        <div class="brand-text" :class="{ 'brand-text--visible': mounted }">
          <h2 class="brand-headline">La plateforme de gestion<br/>de locations tout-en-un</h2>
          <p class="brand-sub">Résidences · Véhicules · Réservations</p>
        </div>

        <!-- Feature pills -->
        <div class="feature-pills" :class="{ 'feature-pills--visible': mounted }">
          <span class="pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Résidences
          </span>
          <span class="pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 6h-2l-3 7H3l1 4h14l1-4h-3l-3-7z"/></svg>
            Véhicules
          </span>
          <span class="pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Réservations
          </span>
        </div>

        <!-- Floating decorative icons -->
        <div class="float-icon float-house">
          <svg viewBox="0 0 48 48" fill="none"><path d="M6 20L24 6l18 14v22H6V20z" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/><rect x="18" y="30" width="12" height="12" rx="1" fill="rgba(249,115,22,0.3)"/></svg>
        </div>
        <div class="float-icon float-car">
          <svg viewBox="0 0 48 48" fill="none"><path d="M8 30l4-12h24l4 12v8H8v-8z" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/><circle cx="14" cy="38" r="4" fill="rgba(249,115,22,0.3)" stroke="rgba(249,115,22,0.4)" stroke-width="1.5"/><circle cx="34" cy="38" r="4" fill="rgba(249,115,22,0.3)" stroke="rgba(249,115,22,0.4)" stroke-width="1.5"/></svg>
        </div>

        <!-- Footer copyright -->
        <p class="brand-footer">© {{ new Date().getFullYear() }} DodoVroum. Tous droits réservés.</p>
      </div>
    </div>

    <!-- RIGHT PANEL — form -->
    <div class="form-panel">
      <div class="form-wrapper" :class="{ 'form-wrapper--visible': mounted }">

        <!-- Mobile logo -->
        <div class="mobile-logo">
          <img :src="logoUrl" alt="DodoVroum" class="mobile-logo-img" />
        </div>

        <!-- Header -->
        <div class="form-header">
          <h1 class="form-title">Bon retour !</h1>
          <p class="form-subtitle">Connectez-vous à votre espace de gestion</p>
        </div>

        <!-- Global error banner -->
        <transition name="shake">
          <div v-if="globalError" class="error-banner">
            <div class="error-banner__icon">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            </div>
            <span>{{ globalError }}</span>
          </div>
        </transition>

        <form @submit.prevent="submit" class="form-body" novalidate>
          <!-- Email -->
          <div class="field" :class="{ 'field--error': form.errors.email, 'field--focused': focusedField === 'email', 'field--filled': form.email }">
            <label for="email" class="field__label">Adresse email</label>
            <div class="field__input-wrap">
              <div class="field__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </div>
              <input
                id="email"
                v-model="form.email"
                name="email"
                type="email"
                autocomplete="email"
                required
                placeholder="votre@email.com"
                class="field__input"
                @focus="focusedField = 'email'"
                @blur="focusedField = null"
              />
            </div>
            <transition name="slide-down">
              <p v-if="form.errors.email" class="field__error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ form.errors.email }}
              </p>
            </transition>
          </div>

          <!-- Password -->
          <div class="field" :class="{ 'field--error': form.errors.password, 'field--focused': focusedField === 'password', 'field--filled': form.password }">
            <label for="password" class="field__label">Mot de passe</label>
            <div class="field__input-wrap">
              <div class="field__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <input
                id="password"
                v-model="form.password"
                name="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                placeholder="••••••••"
                class="field__input field__input--password"
                @focus="focusedField = 'password'"
                @blur="focusedField = null"
              />
              <button
                type="button"
                class="field__toggle"
                :title="showPassword ? 'Masquer' : 'Afficher'"
                @click="showPassword = !showPassword"
              >
                <!-- Eye open -->
                <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <!-- Eye off -->
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
            <transition name="slide-down">
              <p v-if="form.errors.password" class="field__error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ form.errors.password }}
              </p>
            </transition>
          </div>

          <!-- Remember me -->
          <div class="remember-row">
            <label class="remember-label">
              <div class="remember-checkbox-wrap">
                <input
                  id="remember"
                  v-model="form.remember"
                  name="remember"
                  type="checkbox"
                  class="remember-input"
                />
                <div class="remember-custom" :class="{ 'remember-custom--checked': form.remember }">
                  <svg v-if="form.remember" viewBox="0 0 12 12" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 6l3 3 5-5"/></svg>
                </div>
              </div>
              <span>Se souvenir de moi</span>
            </label>
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            :disabled="form.processing"
            class="submit-btn"
            :class="{ 'submit-btn--loading': form.processing }"
          >
            <span v-if="!form.processing" class="submit-btn__content">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
              Se connecter
            </span>
            <span v-else class="submit-btn__spinner">
              <svg viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
              Connexion en cours…
            </span>
          </button>
        </form>

        <!-- Form footer -->
        <p class="form-footer">
          © {{ new Date().getFullYear() }} DodoVroum · Plateforme de gestion
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '../Components/Layouts/AuthLayout.vue';
import logoUrl from '../assets/logo.png';

defineOptions({ layout: AuthLayout });

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const focusedField = ref<string | null>(null);
const mounted = ref(false);

const globalError = computed(() => {
  if (form.errors.email && form.errors.password) return null;
  if (!form.errors.email && !form.errors.password) return null;
  return null;
});

onMounted(() => {
  setTimeout(() => { mounted.value = true; }, 50);
});

const submit = () => {
  form.post('/login', { onFinish: () => form.reset('password') });
};
</script>

<style scoped>
/* ─── Root layout ─── */
.auth-root {
  display: flex;
  min-height: 100dvh;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

/* ─── LEFT BRAND PANEL ─── */
.brand-panel {
  display: none;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #0a1628 0%, #0d2855 40%, #1a4a9e 100%);
}
@media (min-width: 1024px) {
  .brand-panel { display: flex; flex: 0 0 52%; align-items: center; justify-content: center; }
}

/* Animated blobs */
.blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.25;
  animation: drift 12s ease-in-out infinite;
}
.blob-1 {
  width: 420px; height: 420px;
  background: #1d6fd4;
  top: -80px; left: -80px;
  animation-duration: 14s;
}
.blob-2 {
  width: 320px; height: 320px;
  background: #f97316;
  bottom: 20%; right: -60px;
  animation-duration: 10s;
  animation-delay: -4s;
}
.blob-3 {
  width: 260px; height: 260px;
  background: #1d6fd4;
  bottom: -60px; left: 20%;
  animation-duration: 18s;
  animation-delay: -8s;
}
@keyframes drift {
  0%, 100% { transform: translate(0, 0) scale(1); }
  33% { transform: translate(20px, -20px) scale(1.05); }
  66% { transform: translate(-15px, 15px) scale(0.96); }
}

/* Grid overlay */
.grid-overlay {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 48px 48px;
}

/* Brand content */
.brand-content {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 32px;
  padding: 48px 40px;
  width: 100%;
  max-width: 500px;
}

/* Logo card */
.logo-card {
  background: rgba(255,255,255,0.95);
  border-radius: 28px;
  padding: 28px 36px;
  box-shadow: 0 24px 64px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
  backdrop-filter: blur(10px);
  transform: translateY(24px);
  opacity: 0;
  transition: transform 0.7s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.6s ease;
}
.logo-card--visible {
  transform: translateY(0);
  opacity: 1;
}
.logo-img {
  width: 220px;
  height: auto;
  display: block;
}

/* Brand text */
.brand-text {
  text-align: center;
  transform: translateY(20px);
  opacity: 0;
  transition: transform 0.6s ease 0.15s, opacity 0.6s ease 0.15s;
}
.brand-text--visible { transform: translateY(0); opacity: 1; }
.brand-headline {
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.35;
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}
.brand-sub {
  font-size: 0.875rem;
  color: rgba(255,255,255,0.55);
  margin: 0;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 500;
}

/* Feature pills */
.feature-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
  transform: translateY(20px);
  opacity: 0;
  transition: transform 0.6s ease 0.28s, opacity 0.6s ease 0.28s;
}
.feature-pills--visible { transform: translateY(0); opacity: 1; }
.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 100px;
  color: rgba(255,255,255,0.75);
  font-size: 0.8rem;
  font-weight: 500;
  backdrop-filter: blur(8px);
}
.pill svg { width: 14px; height: 14px; }

/* Floating icons */
.float-icon {
  position: absolute;
  opacity: 0.4;
  animation: float 6s ease-in-out infinite;
}
.float-icon svg { width: 64px; height: 64px; }
.float-house { top: 12%; left: 8%; animation-delay: 0s; }
.float-car { bottom: 18%; right: 6%; animation-delay: -3s; }
@keyframes float {
  0%, 100% { transform: translateY(0px) rotate(-3deg); }
  50% { transform: translateY(-14px) rotate(3deg); }
}

/* Brand footer */
.brand-footer {
  color: rgba(255,255,255,0.3);
  font-size: 0.75rem;
  margin: 0;
  position: absolute;
  bottom: 24px;
  left: 0; right: 0;
  text-align: center;
}

/* ─── RIGHT FORM PANEL ─── */
.form-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  background: #f8fafc;
  position: relative;
  overflow: hidden;
}
.form-panel::before {
  content: '';
  position: absolute;
  top: -200px; right: -200px;
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(249,115,22,0.06) 0%, transparent 70%);
  pointer-events: none;
}
.form-panel::after {
  content: '';
  position: absolute;
  bottom: -150px; left: -150px;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(29,111,212,0.07) 0%, transparent 70%);
  pointer-events: none;
}

/* Form wrapper card */
.form-wrapper {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 24px;
  padding: 40px 36px;
  box-shadow:
    0 1px 3px rgba(0,0,0,0.04),
    0 8px 24px rgba(0,0,0,0.06),
    0 24px 64px rgba(0,0,0,0.04);
  border: 1px solid rgba(0,0,0,0.06);
  transform: translateY(32px);
  opacity: 0;
  transition: transform 0.65s cubic-bezier(0.34, 1.4, 0.64, 1), opacity 0.5s ease;
}
.form-wrapper--visible { transform: translateY(0); opacity: 1; }

/* Mobile logo */
.mobile-logo {
  display: flex;
  justify-content: center;
  margin-bottom: 28px;
}
.mobile-logo-img {
  width: 160px;
  height: auto;
}
@media (min-width: 1024px) {
  .mobile-logo { display: none; }
}

/* Form header */
.form-header { margin-bottom: 28px; }
.form-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px;
  letter-spacing: -0.03em;
}
.form-subtitle {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0;
}

/* Error banner */
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  color: #dc2626;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 20px;
}
.error-banner__icon { flex-shrink: 0; }
.error-banner__icon svg { width: 18px; height: 18px; }

/* Form body */
.form-body { display: flex; flex-direction: column; gap: 20px; }

/* Fields */
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
  letter-spacing: 0.01em;
  transition: color 0.2s;
}
.field--focused .field__label { color: #1d6fd4; }
.field--error .field__label { color: #dc2626; }

.field__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.field__icon {
  position: absolute;
  left: 14px;
  color: #9ca3af;
  transition: color 0.2s;
  pointer-events: none;
  display: flex;
}
.field__icon svg { width: 18px; height: 18px; }
.field--focused .field__icon { color: #1d6fd4; }
.field--error .field__icon { color: #dc2626; }

.field__input {
  width: 100%;
  padding: 13px 14px 13px 44px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.9rem;
  color: #0f172a;
  outline: none;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  -webkit-appearance: none;
}
.field__input::placeholder { color: #cbd5e1; }
.field__input:focus {
  background: #fff;
  border-color: #1d6fd4;
  box-shadow: 0 0 0 3px rgba(29,111,212,0.1);
}
.field--error .field__input {
  border-color: #f87171;
  background: #fff;
}
.field--error .field__input:focus {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220,38,38,0.08);
}
.field__input--password { padding-right: 44px; }

.field__toggle {
  position: absolute;
  right: 14px;
  background: none;
  border: none;
  cursor: pointer;
  color: #94a3b8;
  padding: 0;
  display: flex;
  align-items: center;
  transition: color 0.2s;
}
.field__toggle:hover { color: #1d6fd4; }
.field__toggle svg { width: 18px; height: 18px; }

.field__error {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.775rem;
  color: #dc2626;
  margin: 0;
  font-weight: 500;
}
.field__error svg { width: 13px; height: 13px; flex-shrink: 0; }

/* Remember me */
.remember-row { display: flex; align-items: center; justify-content: space-between; }
.remember-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 0.85rem;
  color: #64748b;
  user-select: none;
}
.remember-checkbox-wrap { position: relative; }
.remember-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
.remember-custom {
  width: 18px;
  height: 18px;
  border-radius: 5px;
  border: 1.5px solid #cbd5e1;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.remember-custom--checked {
  background: #1d6fd4;
  border-color: #1d6fd4;
}
.remember-custom svg { width: 10px; height: 10px; color: #fff; stroke-width: 2.5; }

/* Submit button */
.submit-btn {
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 13px;
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: 0.01em;
  transition:
    transform 0.15s,
    box-shadow 0.2s,
    opacity 0.2s,
    filter 0.2s;
  box-shadow: 0 4px 14px rgba(249,115,22,0.35), 0 1px 3px rgba(249,115,22,0.2);
  margin-top: 4px;
}
.submit-btn:hover:not(:disabled) {
  transform: translateY(-1px) scale(1.005);
  box-shadow: 0 8px 24px rgba(249,115,22,0.4), 0 2px 6px rgba(249,115,22,0.25);
  filter: brightness(1.04);
}
.submit-btn:active:not(:disabled) {
  transform: translateY(0) scale(0.99);
  box-shadow: 0 2px 8px rgba(249,115,22,0.3);
}
.submit-btn:disabled {
  opacity: 0.75;
  cursor: not-allowed;
}
.submit-btn__content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.submit-btn__content svg { width: 18px; height: 18px; }
.submit-btn__spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.submit-btn__spinner svg {
  width: 18px;
  height: 18px;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Form footer */
.form-footer {
  text-align: center;
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 24px 0 0;
}

/* ─── Transitions ─── */
.slide-down-enter-active { transition: all 0.25s ease; }
.slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from { opacity: 0; transform: translateY(-6px); }
.slide-down-leave-to { opacity: 0; transform: translateY(-4px); }

.shake-enter-active {
  animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}
.shake-leave-active { transition: opacity 0.2s; }
.shake-leave-to { opacity: 0; }
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  15%, 45%, 75% { transform: translateX(-5px); }
  30%, 60%, 90% { transform: translateX(5px); }
}

/* ─── Mobile tweaks ─── */
@media (max-width: 480px) {
  .form-wrapper {
    padding: 28px 20px;
    border-radius: 20px;
  }
  .form-title { font-size: 1.5rem; }
}
</style>
