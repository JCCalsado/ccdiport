<script setup name="TableCaption">
import { computed } from 'vue';
import { cn } from './utils/cn';

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
});

const classes = computed(() =>
  cn('mt-4 text-sm text-slate-500 dark:text-slate-400', props.class)
);
</script>

<template>
  <caption :class="classes">
    <slot />
  </caption>
</template>