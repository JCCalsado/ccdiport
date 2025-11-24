<script setup>
import { computed } from 'vue';
import { cn } from './utils/cn';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'secondary', 'destructive', 'outline', 'success'].includes(value),
  },
  class: {
    type: String,
    default: '',
  },
});

const badgeVariants = {
  default: 'border-transparent bg-slate-900 text-slate-50 hover:bg-slate-900/80 dark:bg-slate-50 dark:text-slate-900 dark:hover:bg-slate-50/80',
  secondary: 'border-transparent bg-slate-100 text-slate-900 hover:bg-slate-100/80 dark:bg-slate-800 dark:text-slate-50 dark:hover:bg-slate-800/80',
  destructive: 'border-transparent bg-red-500 text-slate-50 hover:bg-red-500/80 dark:bg-red-900 dark:text-slate-50 dark:hover:bg-red-900/80',
  outline: 'text-slate-950 dark:text-slate-50',
  success: 'border-transparent bg-green-500 text-slate-50 hover:bg-green-500/80 dark:bg-green-900 dark:text-slate-50 dark:hover:bg-green-900/80',
};

const classes = computed(() =>
  cn(
    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2 dark:focus:ring-slate-300',
    badgeVariants[props.variant],
    props.class
  )
);
</script>

<template>
  <div :class="classes">
    <slot />
  </div>
</template>