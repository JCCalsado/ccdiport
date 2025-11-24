<script setup>
import { computed } from 'vue';
import { cn } from '../utils/cn';

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Select an option',
  },
  options: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
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
  class: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const classes = computed(() =>
  cn(
    'flex h-10 w-full items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.class
  )
);
</script>

<template>
  <select
    :id="id"
    :value="modelValue"
    :disabled="disabled"
    :required="required"
    :name="name"
    :class="classes"
    @change="emit('update:modelValue', $event.target.value); emit('change', $event)"
  >
    <option value="" disabled selected v-if="placeholder">
      {{ placeholder }}
    </option>
    <option
      v-for="option in options"
      :key="option.value"
      :value="option.value"
    >
      {{ option.label }}
    </option>
    <slot />
  </select>
</template>