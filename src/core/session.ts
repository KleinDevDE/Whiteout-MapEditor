import { ref } from 'vue';
import type { PlacedObject } from './types.ts';
import { STAMPS } from './objects.ts';

export class Session {
    static placedTiles = ref<PlacedObject[]>([]);
    static view = ref<{ x: number; y: number; zoom: number } | null>({x: 0, y: 0, zoom: 1});

    static saveDraft(): void {
        //Save current stata in LocalStorage
        localStorage.setItem('draft:'+window.location, this.toJSON());
    }

    static async loadDraft(): Promise<void> {
        if (window.DRAFT_ID !== undefined) {
            try {
                const res = await fetch(`/share/load.php?id=${window.DRAFT_ID}`);
                if (res.ok) {
                    const data = await res.json();
                    if (data.status && data.data.placedTiles) {
                        Session.placedTiles.value = data.data.placedTiles;
                        for (const obj of Session.placedTiles.value) {
                            obj.color = STAMPS[obj.stampId].color ?? obj.color;
                        }
                        if (data.data.view) {
                            Session.view.value = data.data.view;
                        }
                    }
                }
            } catch (e) {
                console.error('Error loading shared draft:', e);
            }
            return;
        }

        const savedData = localStorage.getItem('draft:'+window.location);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                if (data.placedTiles) {
                    Session.placedTiles.value = data.placedTiles;

                    //Reset colors to the ones from STAMPS
                    for (const obj of Session.placedTiles.value) {
                        obj.color = STAMPS[obj.stampId].color ?? obj.color;
                    }
                }
                if (data.view) {
                    Session.view.value = data.view;
                }
            } catch (e) {
                console.error('Error parsing saved data:', e);
            }
        }
    }

    static save(): void {
        const dataStr =
            'data:text/json;charset=utf-8,' + encodeURIComponent(this.toJSON());
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute('href', dataStr);
        downloadAnchorNode.setAttribute('download', 'map.json');
        document.body.appendChild(downloadAnchorNode); // required for firefox
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }

    static async share(): Promise<string | null> {
        try {
            const res = await fetch('share/store.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: this.toJSON(),
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const data = await res.json();
            if (data.url) {
                return data.url as string;
            }
        } catch (e) {
            console.error('Error sharing draft:', e);
        }
        return null;
    }

    static async load(): Promise<boolean> {
        return new Promise((resolve) => {
            //Open file
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.addEventListener('change', (e) => {
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
                        if (data.placedTiles) {
                            console.log(
                                'Loaded placedTiles:',
                                data.placedTiles
                            );
                            for (const obj of Session.placedTiles.value) {
                                obj.color = STAMPS[obj.stampId].color ?? obj.color;
                            }
                            Session.placedTiles.value = data.placedTiles;
                        }
                        if (data.view) {
                            Session.view.value = data.view;
                        }
                        Session.saveDraft();
                        resolve(true);
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        alert('Error parsing JSON!');
                        resolve(false);
                    }
                };
                reader.readAsText(file);
            });
            //close/exit/cancel
            input.addEventListener('cancel', () => {
                console.log('File loading cancelled');
                resolve(false);
            });

            input.click();
        });
    }

    private static toJSON(): string {
        return JSON.stringify({
            placedTiles: Session.placedTiles.value,
            view: Session.view.value,
        });
    }
}
