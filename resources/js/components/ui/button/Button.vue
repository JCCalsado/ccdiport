<script setup>
import { computed } from 'vue';
import { cn } from '../utils/cn';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'destructive', 'outline', 'secondary', 'ghost', 'link'].includes(value),
  },
  size: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'sm', 'lg', 'icon'].includes(value),
  },
  as: {
    type: String,
    default: 'button',
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  class: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['click']);

const variants = {
  default: 'bg-slate-900 text-slate-50 hover:bg-slate-900/90',
  destructive: 'bg-red-500 text-slate-50 hover:bg-red-500/90',
  outline: 'border border-slate-200 bg-white hover:bg-slate-100 hover:text-slate-900',
  secondary: 'bg-slate-100 text-slate-900 hover:bg-slate-100/80',
  ghost: 'hover:bg-slate-100 hover:text-slate-900',
  link: 'text-slate-900 underline-offset-4 hover:underline',
};

const sizes = {
  default: 'h-10 px-4 py-2',
  sm: 'h-9 px-3 text-xs',
  lg: 'h-11 px-8',
  icon: 'h-10 w-10',
};

const classes = computed(() =>
  cn(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
    variants[props.variant],
    sizes[props.size],
    props.class
  )
);
</script>

<template>
  <component
    :is="as"
    :type="as === 'button' ? type : undefined"
    :disabled="disabled"
    :class="classes"
    @click="emit('click', $event)"
  >
    <slot />
  </component>
</template>