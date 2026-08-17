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
    <!--
        width/height son las medidas reales del archivo: no fijan el tamaño (lo
        gobiernan las clases) pero le dan al navegador el aspecto para reservar
        el hueco antes de que la imagen llegue.
    -->
    <img
        src="/img/huella-icono-app.webp"
        alt="Huella"
        width="460"
        height="446"
        :class="className"
        class="rounded-[22%] object-cover"
        v-bind="$attrs"
    />
</template>
