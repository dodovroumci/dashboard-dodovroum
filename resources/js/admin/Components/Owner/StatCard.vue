<template>
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group hover:shadow-md transition-all">
    <div class="p-6">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-sm font-medium text-slate-500">{{ title }}</p>
          <h3 class="text-2xl font-bold mt-1 text-slate-900">{{ value }}</h3>
        </div>
        <div :class="`p-2 rounded-lg ${color} bg-opacity-10 group-hover:scale-110 transition-transform`">
          <component :is="icon" :class="`w-5 h-5 ${iconColor}`" />
        </div>
      </div>
      <div class="mt-4 flex items-center gap-2">
        <span
          :class="`text-xs font-bold px-2 py-0.5 rounded-full ${
            isPositive ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
          }`"
        >
          {{ isPositive ? '+' : '' }}{{ trend }}%
        </span>
        <span class="text-xs text-slate-400">vs mois dernier</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';

interface StatCardProps {
  title: string;
  value: string;
  trend: number; // Pourcentage
  icon: Component;
  color: string; // Ex: "bg-blue-600"
}

const props = defineProps<StatCardProps>();

const isPositive = computed(() => props.trend > 0);

const iconColor = computed(() => {
  // Extraire la couleur de l'icône depuis la prop color
  // Ex: "bg-blue-600" -> "text-blue-600"
  return props.color.replace('bg-', 'text-');
});
</script>

