<!-- Right bottom. Shows coordinates as well as a zoom slider and coordination input.-->
<script setup lang="ts">
import { ZoomIn, ZoomOut } from 'lucide-vue-next';

const props = defineProps<{
  hoverX: number | null
  hoverY: number | null,
  zoomIn: () => void,
  zoomOut: () => void,
}>();

</script>

<!-- Halb-Transparente Box rechts unten mit Darstellung aktueller Koordinaten und Input-Feldern. Erstmal Mock.up TailwindCSS-->
<template>
  <div class="fixed bottom-0 right-0 bg-gray-700/70 p-3 shadow-md border border-gray-500 shadow-gray-700 z-10 m-3 rounded-lg">
    <div class="flex space-x-2 mb-2">
      <input type="number" placeholder="X" readonly :value="props.hoverX" min="0" max="1199" step="1" class="w-16 border rounded-lg pl-1 bg-gray-300 border-gray-500" />
      <input type="number" placeholder="Y" readonly :value="props.hoverY" min="0" max="1199" step="1" class="w-16 border rounded-lg pl-1 bg-gray-300 border-gray-500" />
    </div>
    <div class="flex space-x-2 justify-start">
      <ZoomOut
          color="white"
          :size="24"
          @click="props.zoomOut()"
      />
      <ZoomIn
          color="white"
          :size="24"
          @click="props.zoomIn()"
      />
    </div>
  </div>

</template>

<style scoped>
</style>