export type CellColorIndex = number;
export type XY = { x: number; y: number };

export interface Stamp {
    id: string;
    label: string;
    /** relative Zellen (dx, dy) */
    shape: XY[];
    /** feste Farbe oder null = aktuelle Palette */
    color: string | null;
    /** optional: Rechteckgröße fürs Grid-Masking (wenn shape ein Rechteck darstellt) */
    bbox?: { w: number; h: number };
    /** Banner-Range in Tiles (z. B. 3 = 7×7) */
    bannerRange?: number;
}

export interface PlacedObject {
    id: string;          // laufende ID
    stampId: string;     // z. B. 'city2', 'trap3'
    origin: XY;          // Grid-Position, auf die der Stamp angewendet wurde
    color: string;    // tatsächlich verwendete Palettenfarbe
    bbox?: { w: number; h: number };
    bannerRange?: number;
}

export interface Color {
    hex: string;
    alpha: number;
}

export interface ColorPalette {
    [key: number]: Color;
}

export interface Tile {
    x: number;
    y: number;
    is_solid: boolean;
}

export interface Building {
    type: string;
    tiles: Tile[];
    center: XY;
}

export interface BuildingType {
    id: string;
    label: string; //TODO Multilingual (use object or lang-key or something else)
    color: string;
    shape: XY[];
    extras: {
        level: number|null;
        is_banner: boolean;
    }
}

export interface Configuration {
    palette: ColorPalette;
    buildingTypes: Record<string, BuildingType>;
}
