import {ref} from "vue";
import type {XY} from "../core/types.ts";

export class MapGenerationService {
    configuration= {
        world: {
            alias: 'default',
            width: 1199,
            height: 1199,
            tileSize: 24
        }
    }
    canvas  = ref<HTMLCanvasElement|null>(null);
    dpr = window.devicePixelRatio || 1
    scale   = ref(1)
    offset  = ref({ x: 0, y: 0 })

    public getCanvas(): HTMLCanvasElement {
        return this.canvas.value || this.createCanvas();
    }

    private createCanvas(): HTMLCanvasElement {
        const canvas = document.createElement('canvas');
        canvas.width = this.configuration.world.width;
        canvas.height = this.configuration.world.height;
        this.canvas.value = canvas;
        return canvas;
    }

    public fillTiles(tiles: XY[], color: string) {
        const ctx = this.getCanvas().getContext('2d');
        if (!ctx) return;
        ctx.fillStyle = color;
        for (const tile of tiles) {
            ctx.fillRect(tile.x * this.configuration.world.tileSize, tile.y * this.configuration.world.tileSize, this.configuration.world.tileSize, this.configuration.world.tileSize);
        }
    }

    public resizeCanvas() {
        const c = this.getCanvas(), ctx = c.getContext('2d')!
        this.dpr = window.devicePixelRatio || 1
        const w = c.clientWidth, h = c.clientHeight
        c.width  = Math.floor(w * this.dpr)
        c.height = Math.floor(h * this.dpr)
        ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0)
        ctx.imageSmoothingEnabled = false
    }

    public centerView() {
        const c = this.getCanvas()
        this.offset.value.x = (c.clientWidth  - this.configuration.world.width * this.configuration.world.tileSize * this.scale.value) / 2
        this.offset.value.y = (c.clientHeight - this.configuration.world.height * this.configuration.world.tileSize * this.scale.value) / 2
    }

    public visibleBounds() {
        const c = this.getCanvas()
        const inv = 1 / this.scale.value
        const x1 = Math.max(0, Math.floor((-this.offset.value.x) * inv / this.configuration.world.tileSize) - 1)
        const y1 = Math.max(0, Math.floor((-this.offset.value.y) * inv / this.configuration.world.tileSize) - 1)
        const x2 = Math.min(this.configuration.world.width-1, Math.ceil((c.clientWidth  - this.offset.value.x) * inv / this.configuration.world.tileSize) + 1)
        const y2 = Math.min(this.configuration.world.height-1, Math.ceil((c.clientHeight - this.offset.value.y) * inv / this.configuration.world.tileSize) + 1)
        return { x1, y1, x2, y2 }
    }

    public toGrid(e: MouseEvent) {
        const rect = this.getCanvas().getBoundingClientRect()
        const px = (e.clientX - rect.left - this.offset.value.x) / this.scale.value
        const py = (e.clientY - rect.top  - this.offset.value.y) / this.scale.value
        return { gx: Math.floor(px / this.configuration.world.tileSize), gy: Math.floor(py / this.configuration.world.tileSize) }
    }
}