<script setup>
import { cn } from '../utils/cn';

defineProps({
  class: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <p :class="cn('text-sm text-slate-500', $props.class)">
    <slot />
  </p>
</template>