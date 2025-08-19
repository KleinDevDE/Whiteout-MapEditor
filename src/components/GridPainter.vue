<template>
  <div class="w-screen h-screen overflow-hidden relative bg-white">
    <!-- Toolbar -->
    <div class="absolute left-3 top-3 z-10 bg-white/90 backdrop-blur rounded-lg shadow p-3 space-y-3 select-none">
      <div class="text-sm font-medium">Placement</div>
      <div class="flex flex-wrap gap-2">
        <button v-for="buildingType in Session.configuration.value.buildingTypes" :key="buildingType.id"
                class="px-2 py-1 border rounded text-sm"
                :class="selectedTool===buildingType.id ? 'bg-gray-200 font-medium' : ''"
                @click="selectedTool = buildingType.id">{{ buildingType.label }}
        </button>
      </div>

      <div class="flex gap-2">
        <button class="px-2 py-1 border rounded" @click="clearAll">Clear</button>
        <button class="px-2 py-1 border rounded" @click="centerView">Center</button>
        <button class="px-2 py-1 border rounded" @click="Session.save()">Save</button>
        <button class="px-2 py-1 border rounded" @click="loadFromFile">Load</button>
        <span class="text-xs text-gray-600">Zoom: {{ scale.toFixed(2) }}</span>
      </div>
      <div class="text-xs text-gray-600 leading-5">
        Links: platzieren • Rechts: löschen<br>
        Drag: <kbd>Shift</kbd> / Mittelklick (Pan)<br>
        Koord: {{ hoverX }}, {{ hoverY }} • Tool: {{ selectedTool }}
      </div>
    </div>

    <canvas ref="canvas"
            class="absolute inset-0 block w-full h-full"
            @contextmenu.prevent
            @mousedown="onDown" @mousemove="onMove" @mouseup="onUp" @mouseleave="onUp"
            @wheel.prevent="onWheel"/>
  </div>
</template>

<script setup lang="ts">
import {ref, onMounted, onBeforeUnmount, computed, pushScopeId, inject} from 'vue'
import {type Building, type BuildingType, type PlacedObject, type Tile, type XY} from '/src/core/types'
import {Session} from '../core/session.ts';
import type {MapGenerationService} from "../services/MapGenerationService.ts";
import {BUILDING_TYPES} from "../core/objects.ts";
import {build} from "vite";


const mapGenerationService = inject('mapGenerationService') as MapGenerationService

/** === Sparse-Grid, Objekte & Banner-Overlay === */
const tiles = ref<Map<string, number>>(new Map())                // "x,y" -> color (nur gesetzte Zellen)
const bannerOverlay = ref<Map<string, number>>(new Map())        // "x,y" -> coverage count

/** === UI === */
const canvas = ref<HTMLCanvasElement | null>(null)
const current = ref(0)
const toolMode = ref<'paint' | 'erase'>('paint')
const selectedTool = ref<string>('brush')

const hoverX = ref<number | null>(null)
const hoverY = ref<number | null>(null)

const mode = ref<'paint' | 'pan' | false>(false)
let dragStart = {x: 0, y: 0}

let ro: ResizeObserver | null = null
let selectedBuildingType: BuildingType | null = null

/** === Helpers === */
const keyOf = (x: number, y: number) => `${x},${y}`
const inBounds = (x: number, y: number) => x >= 0 && x < WORLD_W && y >= 0 && y < WORLD_H

function addBannerRange(center: XY, range: number, delta: 1 | -1) {
  // Quadratischer Bereich: (2*range+1) × (2*range+1)
  for (let dy = -range; dy <= range; dy++) {
    for (let dx = -range; dx <= range; dx++) {
      const x = center.x + dx, y = center.y + dy
      if (!inBounds(x, y)) continue
      const k = keyOf(x, y)
      const prev = bannerOverlay.value.get(k) ?? 0
      const next = Math.max(0, prev + delta)
      if (next === 0) bannerOverlay.value.delete(k)
      else bannerOverlay.value.set(k, next)
    }
  }
}

/** === Platzieren/Löschen eines Stamps === */
function placeBuilding(x: number, y: number, buildingType: BuildingType = null) {
  if (!selectedBuildingType) {
    return;
  }

  if (!canPlaceStamp(x, y)) {
    return;
  }

  buildingType = buildingType ?? selectedBuildingType;

  const buildingTiles: Array<XY> = []
  for (const cell of buildingType.shape) {
    buildingTiles.push({x: x + cell.x, y: y + cell.y, is_solid: true})
  }

  const index = Session.buildings.value.push({
    type: buildingType.id,
    center: {x: x, y: y},
    tiles: buildingTiles
  });
  tiles.value.set(keyOf(x, y), index)
  buildingTiles.forEach(tile => {
    const k = keyOf(tile.x, tile.y);
    if (!tiles.value.has(k)) {
      tiles.value.set(k, index);
    }
  });
}

function removeBuilding(coordinates: XY) {
  const tile = tiles.value.get(keyOf(coordinates.x, coordinates.y));
  if (!tile) return;

  const building = Session.buildings.value[tile];
  for (const tile of building.tiles) {
    tiles.value.delete(keyOf(tile.x, tile.y));
  }

  Session.buildings.value.splice(tile, 1);
  tiles.value.delete(keyOf(coordinates.x, coordinates.y));
}

function calculateRange(center: XY, range: number): XY[] {
  const cells: XY[] = [];
  for (let dy = -range; dy <= range; dy++) {
    for (let dx = -range; dx <= range; dx++) {
      const x = center.x + dx, y = center.y + dy;
      if (inBounds(x, y)) {
        cells.push({x, y});
      }
    }
  }
  return cells;
}


function applyStampAt(gx: number, gy: number, erase = false, obj: Building = null) {
  if (!selectedBuildingType) {
    return;
  }

  if (!erase && !canPlaceStamp(gx, gy)) {
    return;
  }

  // 1) Zellen setzen/löschen (kein Collision-Check)
  for (const c of selectedBuildingType.shape) {
    const x = gx + c.x, y = gy + c.y
    if (!inBounds(x, y)) continue
    const k = keyOf(x, y)
    if (erase) tiles.value.delete(k)
    else tiles.value.set(k, (selectedBuildingType.color ?? current.value))
  }

  // 2) Objektliste aktualisieren (nur wenn platzieren)
  if (!erase) {
    if (!obj) {
      obj = {
        type: selectedBuildingType.id,
        center: {x: gx, y: gy},
        tiles:
      }
      Session.buildings.value.push(obj)
    }


    // 3) Banner-Range-Overlay
    if (stamp.bannerRange && stamp.bannerRange > 0) {
      addBannerRange(obj.origin, stamp.bannerRange, +1)
    }
  } else {
    // Beim Radieren: grob alle Objekte entfernen, die den Ursprung treffen (einfacher Ansatz)
    const before = Session.buildings.value.length
    Session.buildings.value = Session.buildings.value.filter(o => {
      const stamp2 = STAMPS[o.stampId]
      const hit = stamp2.shape.some(s => (o.origin.x + s.x === gx) && (o.origin.y + s.y === gy))
      if (hit && o.bannerRange) addBannerRange(o.origin, o.bannerRange, -1)
      return !hit
    })
    // (Optional: smarter löschen per Bounding-Box)
  }

  Session.saveDraft();
}

function canPlaceStamp(gx: number, gy: number) {
  const stamp = activeStamp.value
  if (!stamp) return {ok: false, hits: [] as Array<{ x: number, y: number }>}

  const hits: Array<{ x: number, y: number }> = []
  for (const cell of stamp.shape) {
    const x = gx + cell.x, y = gy + cell.y
    if (!inBounds(x, y)) {
      hits.push({x, y});
      continue
    }
    const k = keyOf(x, y)
    if (tiles.value.has(k)) hits.push({x, y})
  }
  return {ok: hits.length === 0, hits}
}

/** === Canvas/Viewport === */


/** === Events === */
function onDown(e: MouseEvent) {
  if (e.button === 1 || e.shiftKey) {
    mode.value = 'pan'
    dragStart = {x: e.clientX - mapGenerationService.offset.value.x, y: e.clientY - mapGenerationService.offset.value.y}
  } else {
    mode.value = 'paint'
    const {gx, gy} = mapGenerationService.toGrid(e)
    applyStampAt(gx, gy, e.button === 2 || toolMode.value === 'erase')
    draw()
  }
}

function onMove(e: MouseEvent) {
  const {gx, gy} = mapGenerationService.toGrid(e)
  hoverX.value = gx;
  hoverY.value = gy

  if (mode.value === 'paint') {
    // applyStampAt(gx, gy, e.buttons === 2 || toolMode.value==='erase')
    draw()
  } else if (mode.value === 'pan') {
    mapGenerationService.offset.value.x = e.clientX - dragStart.x
    mapGenerationService.offset.value.y = e.clientY - dragStart.y
    draw()
  } else {
    // Cursor auf "not-allowed", wenn Stamp kollidieren würde
    const c = canvas.value!
    const {ok} = canPlaceStamp(gx, gy)
    c.style.cursor = ok || toolMode.value === 'erase' ? 'crosshair' : 'not-allowed'
    draw()
  }
}

function onUp() {
  mode.value = false
}

function onWheel(e: WheelEvent) {
  const rect = canvas.value!.getBoundingClientRect()
  const mouseX = e.clientX - rect.left
  const mouseY = e.clientY - rect.top
  const prev = mapGenerationService.scale.value
  const dir = e.deltaY > 0 ? -0.1 : 0.1
  mapGenerationService.scale.value = Math.min(6, Math.max(0.2, prev + dir))
  const z = mapGenerationService.scale.value / prev
  mapGenerationService.offset.value.x = mouseX - (mouseX - mapGenerationService.offset.value.x) * z
  mapGenerationService.offset.value.y = mouseY - (mouseY - mapGenerationService.offset.value.y) * z
  draw()
}

/** === Zeichnen === */
function draw() {
  const c = canvas.value!, ctx = c.getContext('2d')!
  const {x1, y1, x2, y2} = mapGenerationService.visibleBounds()
  const line = 1 / mapGenerationService.scale.value
  const inset = 0.5 / mapGenerationService.scale.value     // minimaler Rand, damit Außenkanten sichtbar bleiben

  // Reset
  ctx.setTransform(mapGenerationService.dpr, 0, 0, mapGenerationService.dpr, 0, 0)
  ctx.clearRect(0, 0, c.clientWidth, c.clientHeight)
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, c.clientWidth, c.clientHeight)

  // Welt-Transform
  ctx.translate(mapGenerationService.offset.value.x, mapGenerationService.offset.value.y)
  ctx.scale(mapGenerationService.scale.value, mapGenerationService.scale.value)

  // 1) Tiles (sichtbar)
  for (let y = y1; y <= y2; y++) {
    for (let x = x1; x <= x2; x++) {
      const idx = tiles.value.get(keyOf(x, y))
      ctx.fillStyle = idx === undefined ? '#e5e7eb' : idx
      ctx.fillRect(x * mapGenerationService.configuration.world.tileSize, y * mapGenerationService.configuration.world.tileSize, mapGenerationService.configuration.world.tileSize, mapGenerationService.configuration.world.tileSize)
    }
  }

  // 2) Banner-Overlay (halbtransparent) – über den Kacheln, unter dem Grid
  ctx.save()
  ctx.globalAlpha = 0.12
  ctx.fillStyle = '#60a5fa' // blaues Overlay
  for (let y = y1; y <= y2; y++) {
    for (let x = x1; x <= x2; x++) {
      const cov = bannerOverlay.value.get(keyOf(x, y))
      if (cov) ctx.fillRect(x * mapGenerationService.configuration.world.tileSize, y * mapGenerationService.configuration.world.tileSize, mapGenerationService.configuration.world.tileSize, mapGenerationService.configuration.world.tileSize)
    }
  }

  ctx.globalAlpha = 1
  ctx.strokeStyle = '#ef4444'
  ctx.lineWidth = 2 / mapGenerationService.scale.value
  ctx.setLineDash([6 / mapGenerationService.scale.value, 4 / mapGenerationService.scale.value])
  ctx.shadowColor = 'rgba(239,68,68,0.7)'   // rot
  ctx.shadowBlur = 10 / mapGenerationService.scale.value

  for (let y = y1; y <= y2; y++) {
    for (let x = x1; x <= x2; x++) {
      const cov = bannerOverlay.value.get(keyOf(x, y)) ?? 0
      if (cov < 2) continue
      ctx.strokeRect(
          x * mapGenerationService.configuration.world.tileSize + inset,
          y * mapGenerationService.configuration.world.tileSize + inset,
          mapGenerationService.configuration.world.tileSize - 2 * inset,
          mapGenerationService.configuration.world.tileSize - 2 * inset
      )
    }
  }
  ctx.restore()

  // 3) Grid-Linien (sichtbar)
  ctx.strokeStyle = '#cbd5e1'
  ctx.lineWidth = line
  const crispX = (x: number) => x * mapGenerationService.configuration.world.tileSize + 0.5 / mapGenerationService.scale.value
  const crispY = (y: number) => y * mapGenerationService.configuration.world.tileSize + 0.5 / mapGenerationService.scale.value
  for (let x = x1; x <= x2 + 1; x++) {
    ctx.beginPath();
    const X = crispX(x);
    ctx.moveTo(X, y1 * mapGenerationService.configuration.world.tileSize);
    ctx.lineTo(X, (y2 + 1) * mapGenerationService.configuration.world.tileSize);
    ctx.stroke()
  }
  for (let y = y1; y <= y2 + 1; y++) {
    ctx.beginPath();
    const Y = crispY(y);
    ctx.moveTo(x1 * mapGenerationService.configuration.world.tileSize, Y);
    ctx.lineTo((x2 + 1) * mapGenerationService.configuration.world.tileSize, Y);
    ctx.stroke()
  }

  // 4) Grid-Masking für Mehrfeld-Objekte (deckt nur INNERE Linien ab)
  //    Wir zeichnen eine volle Rechteckfläche in Objektfarbe,
  //    aber leicht "eingezogen", damit Außenkanten (Grid) sichtbar bleiben.
  for (const obj: Building of Session.buildings.value) {
    const buildType = BUILDING_TYPES[obj.type];

    //Fill all obj.tiles tiles with color from buildType
    ctx.fillStyle = buildType.color ?? current.value;
    obj.tiles.forEach(tile => {
      ctx.fillRect(tile.x, tile.y, mapGenerationService.configuration.world.tileSize, mapGenerationService.configuration.world.tileSize)
    });

    //Print level on tile, in middle of building, if level is there
  }

  // 5) Hover-Vorschau
  // if (hoverX.value!=null && hoverY.value!=null) {
  //   const stamp = activeStamp.value
  //   if (stamp) {
  //     const { ok } = canPlaceStamp(hoverX.value, hoverY.value)
  //     const useIdx = (stamp.color ?? current.value)
  //     ctx.globalAlpha = 0.45
  //     ctx.fillStyle = ok ? useIdx : '#ef4444' // rot bei Kollision
  //
  //     for (const cell of stamp.shape) {
  //       const x = hoverX.value + cell.x
  //       const y = hoverY.value + cell.y
  //       if (!inBounds(x,y)) continue
  //       ctx.fillRect(x*TILE, y*TILE, TILE, TILE)
  //     }
  //
  //     if (stamp.bannerRange > 0 && tiles.value.get(keyOf(hoverX.value,hoverY.value)) === undefined) {
  //     // if (stamp.bannerRange > 0) {
  //       ctx.save()
  //       ctx.globalAlpha = 0.12
  //       ctx.fillStyle = '#60a5fa' // blaues Overlay
  //       for (let dy=-stamp.bannerRange; dy<=stamp.bannerRange; dy++) {
  //         for (let dx=-stamp.bannerRange; dx<=stamp.bannerRange; dx++) {
  //           const x = hoverX.value + dx, y = hoverY.value + dy
  //           if (!inBounds(x,y)) continue
  //           const k = keyOf(x,y)
  //           const prev = bannerOverlay.value.get(k) ?? 0
  //           const next = Math.max(0, prev + 1)
  //           if (next === 0) continue;
  //           ctx.fillRect(x*TILE, y*TILE, TILE, TILE)
  //         }
  //       }
  //
  //       ctx.globalAlpha = 1
  //       ctx.strokeStyle = '#ef4444'
  //       ctx.lineWidth = 2 / scale.value
  //       ctx.setLineDash([6/scale.value, 4/scale.value])
  //       ctx.shadowColor = 'rgba(239,68,68,0.7)'   // rot
  //       ctx.shadowBlur  = 10 / scale.value
  //
  //       for (let y= hoverY.value - stamp.bannerRange; y<= hoverY.value + stamp.bannerRange; y++) {
  //         for (let x= hoverX.value - stamp.bannerRange; x<= hoverX.value + stamp.bannerRange; x++) {
  //           const cov = bannerOverlay.value.get(keyOf(x,y)) ?? 0
  //           if (cov < 1) continue
  //           ctx.strokeRect(
  //               x*TILE + inset,
  //               y*TILE + inset,
  //               TILE - 2*inset,
  //               TILE - 2*inset
  //           )
  //         }
  //       }
  //       ctx.restore()
  //     }
  //     ctx.globalAlpha = 1.0
  //   }
  //
  //   // Cursor-Rahmen
  //   ctx.strokeStyle = '#111827'
  //   ctx.lineWidth = 2 / scale.value
  //   ctx.strokeRect(
  //       hoverX.value*TILE + 1/scale.value,
  //       hoverY.value*TILE + 1/scale.value,
  //       TILE - 2/scale.value,
  //       TILE - 2/scale.value
  //   )
}

/** === Actions === */
function clearAll() {
  tiles.value.clear()
  Session.buildings.value = []
  bannerOverlay.value.clear()
  Session.saveDraft();
  draw()
}

function loadFromFile() {
  clearAll()
  Session.load().then((result) => {
    console.log('Result:', result, 'Session:', Session.buildings.value)

    for (const building of Session.buildings.value) {
      placeBuilding(building.center.x, building.center.y, BUILDING_TYPES[building.type]);
    }
    draw()
  }).catch(err => {
    console.error('Error loading session:', err)
  })
}

/** === Lifecycle === */
onMounted(() => {
  Session.loadDraft();
  ro = new ResizeObserver(() => mapGenerationService.resizeCanvas())
  ro.observe(canvas.value!)
  mapGenerationService.resizeCanvas()
  mapGenerationService.centerView()
})
onBeforeUnmount(() => {
  ro?.disconnect()
})
</script>

<style scoped>
html, body, #app {
  height: 100%;
}
</style>
