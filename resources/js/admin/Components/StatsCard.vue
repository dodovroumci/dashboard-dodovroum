<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 min-w-0
              shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]
              hover:shadow-[0_2px_8px_rgba(0,0,0,0.06),0_8px_24px_rgba(0,0,0,0.06)]
              hover:-translate-y-px transition-all duration-200 cursor-default">
    <div class="flex items-center justify-between gap-2">
      <div class="min-w-0 flex-1">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 truncate">{{ title }}</p>
        <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1.5 leading-tight break-words tracking-tight" :title="String(value)">{{ value }}</p>
        <p class="text-xs font-medium text-emerald-600 mt-1.5" v-if="trend">{{ trend }}</p>
      </div>
      <div class="w-11 h-11 rounded-xl bg-brand/10 text-brand flex items-center justify-center flex-shrink-0
                  ring-1 ring-brand/10">
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

