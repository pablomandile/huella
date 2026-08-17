<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { toggleSidebar } = useSidebar()
</script>

<template>
  <!-- El kit lo trae en 28px; acá se toca con el pulgar, así que va a 44. -->
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('touch-target', props.class)"
    @click="toggleSidebar"
  >
    <!--
      Las tres líneas de siempre. El kit alternaba entre dos íconos de panel
      según el estado, que se entienden como "acomodar la pantalla" más que como
      "menú"; el hamburguer no necesita que nadie lo interprete. El estado se ve
      en la sidebar misma, y para lector de pantalla lo dice el texto de abajo.
    -->
    <Menu />
    <span class="sr-only">Mostrar u ocultar el menú</span>
  </Button>
</template>
