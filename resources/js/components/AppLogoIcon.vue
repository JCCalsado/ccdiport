<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

defineOptions({
    inheritAttrs: false,
})

interface Props {
    className?: HTMLAttributes['class']
    width?: number
    height?: number
}

const props = defineProps<Props>()
</script>

<template>
    <img 
        src="/images/logo2.jfif"
        :class="['w-full h-full object-cover', className]"
        :style="{
            width: props.width ? props.width + 'px' : undefined,
            height: props.height ? props.height + 'px' : undefined,
        }"
        alt="Logo"
        v-bind="$attrs"
    />
</template>