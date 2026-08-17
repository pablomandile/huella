<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeftClose, PanelLeftOpen } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
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
    <PanelLeftOpen v-if="isMobile || state === 'collapsed'" />
    <PanelLeftClose v-else />
    <span class="sr-only">Mostrar u ocultar el menú</span>
  </Button>
</template>
