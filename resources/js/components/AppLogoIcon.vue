<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Ícono de marca. El original vive en public/img/huella-icono-app.webp. -->
    <img
        src="/img/huella-icono-app.webp"
        alt="Huella"
        :class="className"
        class="rounded-[22%] object-cover"
        v-bind="$attrs"
    />
</template>
