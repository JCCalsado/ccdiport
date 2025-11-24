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
  <div :class="cn('text-sm [&_p]:leading-relaxed', $props.class)">
    <slot />
  </div>
</template>