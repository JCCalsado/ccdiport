<script setup>
import { computed } from 'vue';
import { cn } from '../utils/cn';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  rows: {
    type: Number,
    default: 3,
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

const emit = defineEmits(['update:modelValue', 'blur', 'focus']);

const classes = computed(() =>
  cn(
    'flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.class
  )
);
</script>

<template>
  <textarea
    :id="id"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :readonly="readonly"
    :required="required"
    :name="name"
    :rows="rows"
    :class="classes"
    @input="emit('update:modelValue', $event.target.value)"
    @blur="emit('blur', $event)"
    @focus="emit('focus', $event)"
  />
</template>