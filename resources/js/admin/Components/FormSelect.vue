<template>
  <div class="relative w-full" ref="containerRef">
    <button
      type="button"
      class="w-full min-w-0 min-h-[44px] px-4 py-2.5 sm:py-2 text-left border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white text-slate-900 text-base"
      :class="{ 'ring-2 ring-blue-500 border-blue-500': isOpen }"
      @click="toggle"
      @keydown.enter.prevent="toggle"
      @keydown.space.prevent="toggle"
      @keydown.down.prevent="isOpen ? focusNext() : (isOpen = true)"
      @keydown.up.prevent="isOpen ? focusPrev() : (isOpen = true)"
      @keydown.escape="close"
    >
      <span class="block truncate">{{ displayLabel }}</span>
      <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
        <ChevronDown class="w-5 h-5 transition-transform" :class="{ 'rotate-180': isOpen }" />
      </span>
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-show="isOpen"
        class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-[min(280px,50vh)] overflow-y-auto overflow-x-hidden"
        role="listbox"
        :aria-expanded="isOpen"
      >
        <button
          v-for="(opt, index) in options"
          :key="opt.value"
          type="button"
          role="option"
          class="w-full min-h-[44px] px-4 py-2.5 sm:py-2 text-left text-base text-slate-900 hover:bg-slate-50 focus:bg-blue-50 focus:outline-none focus:ring-0 truncate"
          :class="{ 'bg-blue-50 text-blue-900': String(modelValue) === String(opt.value) }"
          :ref="(el) => setOptionRef(el, index)"
          @click="select(opt.value)"
          @keydown.enter.prevent="select(opt.value)"
          @keydown.space.prevent="select(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    options: { value: string; label: string }[];
    placeholder?: string;
  }>(),
  { placeholder: 'Sélectionner…' }
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const optionRefs = ref<(HTMLElement | null)[]>([]);

function setOptionRef(el: unknown, index: number) {
  if (el instanceof HTMLElement) {
    optionRefs.value[index] = el;
  }
}

const displayLabel = computed(() => {
  const v = String(props.modelValue ?? '');
  const opt = props.options.find((o) => String(o.value) === v);
  return opt ? opt.label : props.placeholder;
});

function toggle() {
  isOpen.value = !isOpen.value;
}

function close() {
  isOpen.value = false;
}

function select(value: string) {
  emit('update:modelValue', String(value));
  close();
}

function focusNext() {
  const current = optionRefs.value.findIndex((el) => el === document.activeElement);
  const next = Math.min(current + 1, props.options.length - 1);
  if (next >= 0) optionRefs.value[next]?.focus();
}

function focusPrev() {
  const current = optionRefs.value.findIndex((el) => el === document.activeElement);
  const next = Math.max(current - 1, 0);
  optionRefs.value[next]?.focus();
}

function handleClickOutside(e: MouseEvent) {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    close();
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside, true);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside, true);
});
</script>
