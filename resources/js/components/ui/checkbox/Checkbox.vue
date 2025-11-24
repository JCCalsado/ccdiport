<script setup>
import { computed } from 'vue';
import { cn } from '../utils/cn';

const props = defineProps({
  checked: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  id: {
    type: String,
    default: undefined,
  },
  name: {
    type: String,
    default: undefined,
  },
  value: {
    type: String,
    default: undefined,
  },
  class: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:checked']);

const classes = computed(() =>
  cn(
    'peer h-4 w-4 shrink-0 rounded-sm border border-slate-900 ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-slate-900 data-[state=checked]:text-slate-50',
    props.class
  )
);
</script>

<template>
  <div class="relative inline-flex items-center">
    <input
      :id="id"
      type="checkbox"
      :checked="checked"
      :disabled="disabled"
      :name="name"
      :value="value"
      :class="classes"
      :data-state="checked ? 'checked' : 'unchecked'"
      @change="emit('update:checked', $event.target.checked)"
    />
    <svg
      v-if="checked"
      class="absolute left-0.5 top-0.5 h-3 w-3 pointer-events-none text-current"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="3"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <polyline points="20 6 9 17 4 12" />
    </svg>
  </div>
</template>