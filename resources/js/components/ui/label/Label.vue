<script setup>
import { cn } from '../utils/cn';

defineProps({
  for: {
    type: String,
    default: undefined,
  },
  required: {
    type: Boolean,
    default: false,
  },
  class: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <label
    :for="$props.for"
    :class="cn('text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70', $props.class)"
  >
    <slot />
    <span v-if="required" class="text-red-500 ml-1">*</span>
  </label>
</template>