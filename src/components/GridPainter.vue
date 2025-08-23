<template>
  <div class="w-screen h-screen overflow-hidden relative bg-white">
    <!-- Toolbar -->
    <div class="absolute z-10 bg-gray-700/70 p-3 shadow-md border border-gray-500 shadow-gray-700 m-3 rounded-lg">
      <div class="text-sm font-bold text-gray-100">Placement</div>
      <div class="flex flex-wrap gap-2 mb-2">
        <button v-for="opt in toolOptions" :key="opt.id"
                class="px-2 py-1 border rounded text-sm btn"
                :class="selectedTool===opt.id ? 'active' : ''"
                @click="selectedTool = opt.id">{{ opt.label }}</button>
      </div>

      <div class="text-sm font-bold mt-1 text-gray-100">Color</div>
      <div class="flex gap-2 items-center">
        <button v-for="(c) in palette" :key="c"
                class="w-6 h-6 btn"
                :style="{background:c}"
                :class="current===c ? 'active' : ''"
                @click="current=c" :title="c"/>
        <button class="px-2 py-1 btn"
                :class="toolMode==='erase' ? 'active' : ''"
                @click="toolMode = (toolMode==='paint' ? 'erase' : 'paint')">
          {{ toolMode==='paint' ? 'Eraser' : 'Paint' }}
        </button>
      </div>

      <div class="flex gap-2 mb-5">
        <button class="px-2 py-1 btn" @click="clearAll">Clear</button>
        <button class="px-2 py-1 btn" @click="centerView">Center</button>
      </div>
      <div class="flex justify-between items-center">
        <div class="text-xs text-gray-100 leading-5">
          Left-Click: Place | Right-Click: Remove<br>
          Tool: {{ selectedTool }} | Zoom: {{ scale.toFixed(2) }}
        </div>
        <div class="flex gap-2">
          <button class="px-2 py-1 border rounded hover:cursor-pointer bg-gray-100 hover:bg-gray-400 hover:text-white" @click="Session.save()">Save</button>
          <button class="px-2 py-1 border rounded hover:cursor-pointer bg-gray-100 hover:bg-gray-400 hover:text-white" @click="loadFromFile">Load</button>
        </div>
      </div>
    </div>

    <canvas ref="canvas"
            class="absolute inset-0 block w-full h-full"
            @contextmenu.prevent
            @mousedown="onDown" @mousemove="onMove" @mouseup="onUp" @mouseleave="onUp"
            @wheel.prevent="onWheel" />
  </div>
  <MapNavigator
      :hoverX="hoverWorldX" :hoverY="hoverWorldY" :zoomIn="zoomIn" :zoomOut="zoomOut" :goTo="goTo"
  ></MapNavigator>
  <InfoBox></InfoBox>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { STAMPS, PALETTE } from '../core/objects'
import {type PlacedObject, type XY} from '../core/types'
// import type {Stamp} from "../core/types.ts";
import {Session} from '../core/session.ts';
import MapNavigator from "./Map/MapNavigator.vue";
import InfoBox from "./InfoBox.vue";


/** === Welt === */
const WORLD_W = 1200
const WORLD_H = 1200
const TILE    = 24
const palette = PALETTE

/** === Sparse-Grid, Objekte & Banner-Overlay === */
const tiles = ref<Map<string, string>>(new Map())                // "x,y" -> color (nur gesetzte Zellen)
// const objects = ref<PlacedObject[]>([])                          // platzierte Gebäude
const bannerOverlay = ref<Map<string, number>>(new Map())        // "x,y" -> coverage count

/** === UI === */
const canvas  = ref<HTMLCanvasElement|null>(null)
const current = ref(palette[0])
const toolMode= ref<'paint'|'erase'>('paint')
const selectedTool = ref<string>('brush')

const hoverX  = ref<number|null>(null) // editor coords
const hoverY  = ref<number|null>(null)
const hoverWorldX = ref<number|null>(null) // game coords
const hoverWorldY = ref<number|null>(null)

const scale   = ref(1)
const offset  = ref({ x: 0, y: 0 })
const mode    = ref<'paint'|'pan'|false>(false)
let dragStart = { x: 0, y: 0 }

let ro: ResizeObserver | null = null
let dpr = window.devicePixelRatio || 1

const toolOptions = Object.values(STAMPS).map(s => ({ id: s.id, label: s.label }))
const activeStamp = computed(() => STAMPS[selectedTool.value])

/** === Helpers === */
const keyOf = (x:number,y:number)=> `${x},${y}`
const inBounds = (x:number,y:number)=> x>=0 && x<WORLD_W+1 && y>=0 && y<WORLD_H+1

// Editor uses top-left origin; game uses bottom-left with axes flipped
const toGameCoords = (x:number, y:number) => ({ x: WORLD_H - 1 - y, y: WORLD_W - 1 - x })
const toEditorCoords = (x:number, y:number) => ({ x: WORLD_W - 1 - y, y: WORLD_H - 1 - x })

function addBannerRange(center: XY, range: number, delta: 1 | -1) {
  // Quadratischer Bereich: (2*range+1) × (2*range+1)
  for (let dy=-range; dy<=range; dy++) {
    for (let dx=-range; dx<=range; dx++) {
      const x = center.x + dx, y = center.y + dy
      if (!inBounds(x,y)) continue
      const k = keyOf(x,y)
      const prev = bannerOverlay.value.get(k) ?? 0
      const next = Math.max(0, prev + delta)
      if (next === 0) bannerOverlay.value.delete(k)
      else bannerOverlay.value.set(k, next)
    }
  }
}

/** === Platzieren/Löschen eines Stamps === */
let nextId = 1
function applyStampAt(gx:number, gy:number, erase=false, obj:PlacedObject|null=null) {
  const stamp = obj ? STAMPS[obj.stampId] : activeStamp.value
  if (!stamp) return

  if (!erase) {
    const { ok } = canPlaceStamp(gx, gy)
    if (!ok) return // ❗ Platzieren unterbinden, wenn kollidiert
  }

  const gameOrigin = toGameCoords(gx, gy)

  // 1) Zellen setzen/löschen (kein Collision-Check)
  for (const c of stamp.shape) {
    const x = gx + c.x, y = gy + c.y
    if (!inBounds(x,y)) continue
    const k = keyOf(x,y)
    if (erase) tiles.value.delete(k)
    else tiles.value.set(k, (stamp.color ?? current.value))
  }

  // 2) Objektliste aktualisieren (nur wenn platzieren)
  if (!erase) {
    if (!obj) {
      obj = {
        id: String(nextId++),
        stampId: stamp.id,
        origin: gameOrigin,
        color: (stamp.color ?? current.value),
        bbox: stamp.bbox,
        bannerRange: stamp.bannerRange
      }
      Session.placedTiles.value.push(obj)
    }

    // 3) Banner-Range-Overlay
    if (stamp.bannerRange && stamp.bannerRange > 0) {
      addBannerRange({ x: gx, y: gy }, stamp.bannerRange, +1)
    }
  } else {
    // Beim Radieren: grob alle Objekte entfernen, die den Ursprung treffen (einfacher Ansatz)
    const gPos = gameOrigin
    Session.placedTiles.value = Session.placedTiles.value.filter(o => {
      const stamp2 = STAMPS[o.stampId]
      const hit = stamp2.shape.some(s => (o.origin.x + s.x === gPos.x) && (o.origin.y + s.y === gPos.y))
      if (hit && o.bannerRange) {
        const editorOrigin = toEditorCoords(o.origin.x, o.origin.y)
        addBannerRange(editorOrigin, o.bannerRange, -1)
      }
      return !hit
    })
    // (Optional: smarter löschen per Bounding-Box)
  }

  Session.saveDraft();
}

function canPlaceStamp(gx:number, gy:number) {
  const stamp = activeStamp.value
  if (!stamp) return { ok: false, hits: [] as Array<{x:number,y:number}> }

  const hits: Array<{x:number,y:number}> = []
  for (const cell of stamp.shape) {
    const x = gx + cell.x, y = gy + cell.y
    if (!inBounds(x,y)) { hits.push({x,y}); continue }
    const k = keyOf(x,y)
    if (tiles.value.has(k)) hits.push({x,y})
  }
  return { ok: hits.length === 0, hits }
}

/** === Canvas/Viewport === */
function resizeCanvas() {
  const c = canvas.value!, ctx = c.getContext('2d')!
  dpr = window.devicePixelRatio || 1
  const w = c.clientWidth, h = c.clientHeight
  c.width  = Math.floor(w * dpr)
  c.height = Math.floor(h * dpr)
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  ctx.imageSmoothingEnabled = false
  draw()
}
function centerView() {
  const c = canvas.value!
  offset.value.x = (c.clientWidth  - WORLD_W * TILE * scale.value) / 2
  offset.value.y = (c.clientHeight - WORLD_H * TILE * scale.value) / 2
  draw()
}
function visibleBounds() {
  const c = canvas.value!
  const inv = 1 / scale.value
  const x1 = Math.max(0, Math.floor((-offset.value.x) * inv / TILE) - 1)
  const y1 = Math.max(0, Math.floor((-offset.value.y) * inv / TILE) - 1)
  const x2 = Math.min(WORLD_W, Math.ceil((c.clientWidth  - offset.value.x) * inv / TILE) + 1)
  const y2 = Math.min(WORLD_H, Math.ceil((c.clientHeight - offset.value.y) * inv / TILE) + 1)
  return { x1, y1, x2, y2 }
}

function toGrid(e: MouseEvent) {
  const rect = canvas.value!.getBoundingClientRect()
  const px = (e.clientX - rect.left - offset.value.x) / scale.value
  const py = (e.clientY - rect.top  - offset.value.y) / scale.value
  return { gx: Math.floor(px / TILE), gy: Math.floor(py / TILE) }
}

/** === Events === */
function onDown(e: MouseEvent) {
  if (e.button === 1 || e.shiftKey) {
    mode.value = 'pan'
    dragStart = { x: e.clientX - offset.value.x, y: e.clientY - offset.value.y }
  } else {
    mode.value = 'paint'
    const { gx, gy } = toGrid(e)
    const world = toGameCoords(gx, gy)
    hoverX.value = gx; hoverY.value = gy
    hoverWorldX.value = world.x; hoverWorldY.value = world.y
    applyStampAt(gx, gy, e.button === 2 || toolMode.value==='erase')
    draw()
  }
}
function onMove(e: MouseEvent) {
  const { gx, gy } = toGrid(e)
  hoverX.value = gx; hoverY.value = gy
  const world = toGameCoords(gx, gy)
  hoverWorldX.value = world.x; hoverWorldY.value = world.y

  if (mode.value === 'paint') {
    // applyStampAt(gx, gy, e.buttons === 2 || toolMode.value==='erase')
    draw()
  } else if (mode.value === 'pan') {
    offset.value.x = e.clientX - dragStart.x
    offset.value.y = e.clientY - dragStart.y
    draw()
  } else {
    // Cursor auf "not-allowed", wenn Stamp kollidieren würde
    const c = canvas.value!
    const { ok } = canPlaceStamp(gx, gy)
    c.style.cursor = ok || toolMode.value==='erase' ? 'crosshair' : 'not-allowed'
    draw()
  }
}
function onUp() { mode.value = false }
function onWheel(e: WheelEvent) {
  const rect = canvas.value!.getBoundingClientRect()
  const mouseX = e.clientX - rect.left
  const mouseY = e.clientY - rect.top
  const prev = scale.value
  const dir = e.deltaY > 0 ? -0.1 : 0.1
  scale.value = Math.min(6, Math.max(0.2, prev + dir))
  const z = scale.value / prev
  offset.value.x = mouseX - (mouseX - offset.value.x) * z
  offset.value.y = mouseY - (mouseY - offset.value.y) * z
  draw()
}

/** === Zeichnen === */
function draw() {
  const c = canvas.value!, ctx = c.getContext('2d')!
  const { x1, y1, x2, y2 } = visibleBounds()
  const line = 1 / scale.value
  const inset = 0.5 / scale.value     // minimaler Rand, damit Außenkanten sichtbar bleiben

  // Reset
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  ctx.clearRect(0, 0, c.clientWidth, c.clientHeight)
  ctx.fillStyle = '#fff'; ctx.fillRect(0,0,c.clientWidth,c.clientHeight)

  // Welt-Transform
  ctx.translate(offset.value.x, offset.value.y)
  ctx.scale(scale.value, scale.value)

  // 1) Tiles (sichtbar)
  for (let y=y1; y<=y2; y++) {
    for (let x=x1; x<=x2; x++) {
      const idx = tiles.value.get(keyOf(x,y))
      ctx.fillStyle = idx === undefined ? '#e5e7eb' : idx
      ctx.fillRect(x*TILE, y*TILE, TILE, TILE)
    }
  }

  // 2) Banner-Overlay (halbtransparent) – über den Kacheln, unter dem Grid
  ctx.save()
  ctx.globalAlpha = 0.12
  ctx.fillStyle = '#60a5fa' // blaues Overlay
  for (let y=y1; y<=y2; y++) {
    for (let x=x1; x<=x2; x++) {
      const cov = bannerOverlay.value.get(keyOf(x,y))
      if (cov) ctx.fillRect(x*TILE, y*TILE, TILE, TILE)
    }
  }

  ctx.globalAlpha = 1
  ctx.strokeStyle = '#ef4444'
  ctx.lineWidth = 2 / scale.value
  ctx.setLineDash([6/scale.value, 4/scale.value])
  ctx.shadowColor = 'rgba(239,68,68,0.7)'   // rot
  ctx.shadowBlur  = 10 / scale.value

  for (let y=y1; y<=y2; y++) {
    for (let x=x1; x<=x2; x++) {
      const cov = bannerOverlay.value.get(keyOf(x,y)) ?? 0
      if (cov < 2) continue
      ctx.strokeRect(
          x*TILE + inset,
          y*TILE + inset,
          TILE - 2*inset,
          TILE - 2*inset
      )
    }
  }
  ctx.restore()

  // 3) Grid-Linien (sichtbar)
  ctx.strokeStyle = '#cbd5e1'
  ctx.lineWidth = line
  const crispX = (x:number)=> x*TILE + 0.5/scale.value
  const crispY = (y:number)=> y*TILE + 0.5/scale.value
  for (let x=x1; x<=x2+1; x++) { ctx.beginPath(); const X=crispX(x); ctx.moveTo(X, y1*TILE); ctx.lineTo(X,(y2+1)*TILE); ctx.stroke() }
  for (let y=y1; y<=y2+1; y++) { ctx.beginPath(); const Y=crispY(y); ctx.moveTo(x1*TILE, Y); ctx.lineTo((x2+1)*TILE,Y); ctx.stroke() }

  // 4) Grid-Masking für Mehrfeld-Objekte (deckt nur INNERE Linien ab)
  //    Wir zeichnen eine volle Rechteckfläche in Objektfarbe,
  //    aber leicht "eingezogen", damit Außenkanten (Grid) sichtbar bleiben.
  for (const obj of Session.placedTiles.value) {
    if (!obj.bbox || !obj.bbox.w || !obj.bbox.h) continue
    const editorOrigin = toEditorCoords(obj.origin.x, obj.origin.y)
    const ox = editorOrigin.x, oy = editorOrigin.y
    const w  = obj.bbox.w, h = obj.bbox.h
    // Sichtbarkeits-Check grob:
    if (ox+w < x1 || ox > x2+1 || oy+h < y1 || oy > y2+1) continue

    const drawX = (ox - Math.floor(w / 2)) * TILE + inset;
    const drawY = (oy - Math.floor(h / 2)) * TILE + inset;

    ctx.fillStyle = obj.color;

    if (w % 2 === 0 && h % 2 === 0) {
      ctx.fillRect(
          ox*TILE + inset,
          oy*TILE + inset,
          w*TILE - 2*inset,
          h*TILE - 2*inset
      )
    } else {
      ctx.fillRect(
          drawX,
          drawY,
          w * TILE - 2 * inset,
          h * TILE - 2 * inset
      );
    }
  }

  // 5) Hover-Vorschau
  if (hoverX.value!=null && hoverY.value!=null) {
    const stamp = activeStamp.value
    if (stamp) {
      const { ok } = canPlaceStamp(hoverX.value, hoverY.value)
      const useIdx = (stamp.color ?? current.value)
      ctx.globalAlpha = 0.45
      ctx.fillStyle = ok ? useIdx : '#ef4444' // rot bei Kollision

      for (const cell of stamp.shape) {
        const x = hoverX.value + cell.x
        const y = hoverY.value + cell.y
        if (!inBounds(x,y)) continue
        ctx.fillRect(x*TILE, y*TILE, TILE, TILE)
      }

      if (stamp.bannerRange!== undefined && stamp.bannerRange > 0 && tiles.value.get(keyOf(hoverX.value,hoverY.value)) === undefined) {
      // if (stamp.bannerRange > 0) {
        ctx.save()
        ctx.globalAlpha = 0.12
        ctx.fillStyle = '#60a5fa' // blaues Overlay
        for (let dy=-stamp.bannerRange; dy<=stamp.bannerRange; dy++) {
          for (let dx=-stamp.bannerRange; dx<=stamp.bannerRange; dx++) {
            const x = hoverX.value + dx, y = hoverY.value + dy
            if (!inBounds(x,y)) continue
            const k = keyOf(x,y)
            const prev = bannerOverlay.value.get(k) ?? 0
            const next = Math.max(0, prev + 1)
            if (next === 0) continue;
            ctx.fillRect(x*TILE, y*TILE, TILE, TILE)
          }
        }

        ctx.globalAlpha = 1
        ctx.strokeStyle = '#ef4444'
        ctx.lineWidth = 2 / scale.value
        ctx.setLineDash([6/scale.value, 4/scale.value])
        ctx.shadowColor = 'rgba(239,68,68,0.7)'   // rot
        ctx.shadowBlur  = 10 / scale.value

        for (let y= hoverY.value - stamp.bannerRange; y<= hoverY.value + stamp.bannerRange; y++) {
          for (let x= hoverX.value - stamp.bannerRange; x<= hoverX.value + stamp.bannerRange; x++) {
            const cov = bannerOverlay.value.get(keyOf(x,y)) ?? 0
            if (cov < 1) continue
            ctx.strokeRect(
                x*TILE + inset,
                y*TILE + inset,
                TILE - 2*inset,
                TILE - 2*inset
            )
          }
        }
        ctx.restore()
      }
      ctx.globalAlpha = 1.0
    }

    // Cursor-Rahmen
    ctx.strokeStyle = '#111827'
    ctx.lineWidth = 2 / scale.value
    ctx.strokeRect(
        hoverX.value*TILE + 1/scale.value,
        hoverY.value*TILE + 1/scale.value,
        TILE - 2/scale.value,
        TILE - 2/scale.value
    )
  }
}

/** === Actions === */
function clearAll() {
  tiles.value.clear()
  Session.placedTiles.value = []
  bannerOverlay.value.clear()
  Session.saveDraft();
  draw()
}

function loadFromFile() {
  clearAll()
  Session.load().then((result) => {
    console.log('Result:', result, 'Session:', Session.placedTiles.value)

    for (const obj of Session.placedTiles.value) {
      const editorPos = toEditorCoords(obj.origin.x, obj.origin.y)
      applyStampAt(editorPos.x, editorPos.y, false, obj)
    }
    draw()
  }).catch(err => {
    console.error('Error loading session:', err)
  })
}

/** === Lifecycle === */
onMounted(() => {
  Session.loadDraft();
  ro = new ResizeObserver(() => resizeCanvas())
  ro.observe(canvas.value!)
  resizeCanvas()
  centerView()
  for (const obj of Session.placedTiles.value) {
    const editorPos = toEditorCoords(obj.origin.x, obj.origin.y)
    applyStampAt(editorPos.x, editorPos.y, false, obj)
  }
  draw()
})
onBeforeUnmount(() => { ro?.disconnect() })


//zoom-in and out listener ($emit('zoom-in') and $emit('zoom-out')
function zoomIn() {
  scale.value = Math.min(6, scale.value + 0.1)
  draw()
}

function zoomOut() {
  scale.value = Math.max(0.2, scale.value - 0.1)
  draw()
}

function goTo(x:number, y:number) {
  const c = canvas.value!
  const editor = toEditorCoords(x, y)
  offset.value.x = c.clientWidth / 2 - (editor.x + 0.5) * TILE * scale.value
  offset.value.y = c.clientHeight / 2 - (editor.y + 0.5) * TILE * scale.value
  draw()
}


</script>

<style scoped>
html, body, #app { height: 100%; }
</style>
