<script setup>
import { computed } from 'vue';
import { cn } from '../utils/cn';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'destructive', 'success', 'warning'].includes(value),
  },
  class: {
    type: String,
    default: '',
  },
});

const alertVariants = {
  default: 'bg-slate-50 text-slate-900 border-slate-200',
  destructive: 'bg-red-50 text-red-900 border-red-200',
  success: 'bg-green-50 text-green-900 border-green-200',
  warning: 'bg-yellow-50 text-yellow-900 border-yellow-200',
};

const classes = computed(() =>
  cn(
    'relative w-full rounded-lg border px-4 py-3 text-sm',
    alertVariants[props.variant],
    props.class
  )
);
</script>

<template>
  <div role="alert" :class="classes">
    <slot />
  </div>
</template>