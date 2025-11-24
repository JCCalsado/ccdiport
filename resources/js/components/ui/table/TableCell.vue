<script setup name="TableCell">
import { computed } from 'vue';
import { cn } from './utils/cn';

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
});

const classes = computed(() =>
  cn('p-4 align-middle [&:has([role=checkbox])]:pr-0', props.class)
);
</script>

<template>
  <td :class="classes">
    <slot />
  </td>
</template>