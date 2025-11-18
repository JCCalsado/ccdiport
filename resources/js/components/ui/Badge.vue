<template>
  <span :class="classes"><slot /></span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String as () => 'default' | 'success' | 'danger' | 'warning' | string, default: 'default' },
  size: { type: String as () => 'xs' | 'sm' | 'md' | string, default: 'sm' }
})

const classes = computed(() => {
  const base = 'inline-flex items-center rounded px-2 py-0.5 font-medium'
  const sizeCls = props.size === 'xs' ? 'text-xs' : props.size === 'md' ? 'text-sm' : 'text-xs'
  const variantCls =
    props.variant === 'success'
      ? 'bg-green-100 text-green-800'
      : props.variant === 'danger'
      ? 'bg-red-100 text-red-800'
      : props.variant === 'warning'
      ? 'bg-yellow-100 text-yellow-800'
      : 'bg-gray-100 text-gray-800'
  return `${base} ${sizeCls} ${variantCls}`
})
</script>

<style scoped>
/* keep minimal — rely on Tailwind if present */
</style>