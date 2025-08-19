import {ref} from "vue";
import type {Building, Configuration} from "./types.ts";
import {BUILDING_TYPES} from "./objects.ts";

export class Session {
    static buildings = ref<Building[]>([])
    static configuration = ref<Configuration>({
        palette: {
            0: { hex: '#ef4444', alpha: 1 }, // red
            1: { hex: '#3b82f6', alpha: 1 }, // blue
            2: { hex: '#22c55e', alpha: 1 }, // green
            3: { hex: '#f59e0b', alpha: 1 }, // yellow
            4: { hex: '#8b5cf6', alpha: 1 }, // purple
            5: { hex: '#94a3b8', alpha: 1 }  // gray
        },
        buildingTypes: BUILDING_TYPES
    });

    static saveDraft(): void {
        //Save current stata in LocalStorage
        localStorage.setItem('map-draft', this.toJSON());
    }

    static loadDraft(): void {
        const savedData = localStorage.getItem('map-draft');
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                if (data.placedTiles) {
                    Session.placedTiles.value = data.placedTiles;
                }
            } catch (e) {
                console.error('Error parsing saved data:', e);
            }
        }
    }

    static save(): void {
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(this.toJSON());
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href",     dataStr);
        downloadAnchorNode.setAttribute("download", "map.json");
        document.body.appendChild(downloadAnchorNode); // required for firefox
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }

    static async load(): Promise<boolean> {
        return new Promise((resolve) => {
//Open file
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.addEventListener("change", (e) => {
                const file = (e.target as HTMLInputElement).files?.[0];
                console.log('Selected file:', file);
                if (!file) return;
                const reader = new FileReader();
                console.log('Reading file:', file);
                reader.onload = (e) => {
                    const text = e.target?.result as string;
                    try {
                        const data = JSON.parse(text);
                        console.log('Loaded data:', data);
                        Session.buildings.value = data.placedTiles ?? this.buildings;
                        Session.configuration.value = data.configuration ?? this.configuration;
                        Session.saveDraft();
                        resolve(true);
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        alert('Error parsing JSON!');
                        resolve(false);
                    }
                }
                reader.readAsText(file);
            });
            //close/exit/cancel
            input.addEventListener("cancel", () => {
                console.log('File loading cancelled');
                resolve(false);
            })

            input.click();
        });
    }

    private static toJSON(): string {
        return JSON.stringify({
            placedTiles: Session.buildings.value,
            configuration: Session.configuration.value
        })
    }
}