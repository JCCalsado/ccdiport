<script setup name="TableHead">
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
    'h-12 px-4 text-left align-middle font-medium text-slate-500 dark:text-slate-400 [&:has([role=checkbox])]:pr-0',
    props.class
  )
);
</script>

<template>
  <th :class="classes">
    <slot />
  </th>
</template>