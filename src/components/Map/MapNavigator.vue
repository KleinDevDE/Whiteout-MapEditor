<!-- Right bottom. Shows coordinates as well as a zoom slider and coordination input.-->
<script setup lang="ts">
import { ref } from 'vue';
import { ZoomIn, ZoomOut } from 'lucide-vue-next';

const props = defineProps<{
    hoverX: number | null;
    hoverY: number | null;
    zoomIn: () => void;
    zoomOut: () => void;
    goTo: (x: number, y: number) => void;
}>();

const x = ref<number | null>(null);
const y = ref<number | null>(null);

function jump() {
    if (x.value != null && y.value != null) {
        props.goTo(x.value, y.value);
    }
}
</script>

<!-- Halb-Transparente Box rechts unten mit Darstellung aktueller Koordinaten und Input-Feldern. Erstmal Mock.up TailwindCSS-->
<template>
    <div
        class="fixed right-0 bottom-0 z-10 m-3 rounded-lg border border-gray-500 bg-gray-700/70 p-3 shadow-md shadow-gray-700"
    >
        <div class="mb-2 flex justify-center text-gray-200">
            X: {{ props.hoverX }} | Y: {{ props.hoverY }}
        </div>
        <div class="mb-2 flex justify-start space-x-2">
            <input
                v-model.number="x"
                @keyup.enter="jump"
                type="number"
                placeholder="X"
                min="0"
                max="1199"
                step="1"
                class="w-16 rounded-lg border border-gray-500 bg-gray-300 pl-1"
            />
            <input
                v-model.number="y"
                @keyup.enter="jump"
                type="number"
                placeholder="Y"
                min="0"
                max="1199"
                step="1"
                class="w-16 rounded-lg border border-gray-500 bg-gray-300 pl-1"
            />
            <button
                class="rounded-lg border border-gray-500 bg-gray-300 px-2 py-1"
                @click="jump"
            >
                Go
            </button>
        </div>
        <div class="flex justify-start space-x-2">
            <ZoomOut color="white" :size="24" @click="props.zoomOut()" />
            <ZoomIn color="white" :size="24" @click="props.zoomIn()" />
        </div>
    </div>
</template>

<style scoped></style>
