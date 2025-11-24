<script setup name="TableBody">
import { computed } from 'vue';
import { cn } from './utils/cn';

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
});

const classes = computed(() =>
  cn('[&_tr:last-child]:border-0', props.class)
);
</script>

<template>
  <tbody :class="classes">
    <slot />
  </tbody>
</template>