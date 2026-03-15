<template>
  <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-sm min-w-0">
    <div class="flex items-center justify-between gap-2">
      <div class="min-w-0">
        <p class="text-xs uppercase tracking-wide text-slate-500 truncate">{{ title }}</p>
        <p class="text-xl sm:text-2xl font-semibold text-slate-900 mt-1 truncate">{{ value }}</p>
        <p class="text-xs text-emerald-600 mt-2" v-if="trend">{{ trend }}</p>
      </div>
      <div class="w-10 h-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center">
        <slot name="icon" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string;
  value: string | number;
  trend?: string;
}>();
</script>

