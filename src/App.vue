<script setup lang="ts">
import GridPainter from './components/GridPainter.vue'

declare global {
  interface Window {
    DRAFT_ID?: string;
  }
}
//If save/XY in URL, set window.DRAFT_ID to XY
if (location.search.includes('/save/')) {
  window.DRAFT_ID = location.search.split('/save/')[1];
}

</script>

<template>
  <GridPainter :gridWidth="60" :gridHeight="40" :tileSize="28" />
</template>
