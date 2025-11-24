<script setup name="TableHeader">
import { computed } from 'vue';
import { cn } from './utils/cn';

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
});

const classes = computed(() =>
  cn('[&_tr]:border-b', props.class)
);
</script>

<template>
  <thead :class="classes">
    <slot />
  </thead>
</template>