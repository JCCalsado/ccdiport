<script setup name="TableRow">
import { computed } from 'vue';
import { cn } from './utils/cn';

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
});

const classes = computed(() =>
  cn(
    'border-b transition-colors hover:bg-slate-100/50 data-[state=selected]:bg-slate-100 dark:hover:bg-slate-800/50 dark:data-[state=selected]:bg-slate-800',
    props.class
  )
);
</script>

<template>
  <tr :class="classes">
    <slot />
  </tr>
</template>