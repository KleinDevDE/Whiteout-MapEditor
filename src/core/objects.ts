import type { Stamp } from './types';

export const PALETTE = ['#ef4444','#3b82f6','#22c55e','#f59e0b','#8b5cf6','#94a3b8', '#3a3a3a'];

export const STAMPS: Record<string, Stamp> = {
    brush: {
        id: 'brush', label: 'Brush',
        shape: [{x:0,y:0}], color: null, bbox: { w:1, h:1 }
    },
    city: {
        id: 'city', label: 'City',
        shape: [{x:0,y:0},{x:1,y:0},{x:0,y:1},{x:1,y:1}],
        color: '#3b82f6', bbox: { w:2, h:2 }
    },
    mountain: {
        id: 'mountain', label: 'Mountain',
        shape: [{x:0,y:0},{x:1,y:0},{x:0,y:1},{x:1,y:1}],
        color: '#3a3a3a', bbox: { w:2, h:2 }
    },
    alliance_farm: {
        id: 'alliance_farm', label: 'Alliance farm',
        shape: [{x:0,y:0},{x:1,y:0},{x:0,y:1},{x:1,y:1}],
        color: '#9d6011', bbox: { w:2, h:2 }
        //TODO Type wood, coal etc.
    },
    bear_trap: {
        id: 'bear_trap', label: 'Bear trap',
        shape: [
            {x:-1,y:-1},{x:0,y:-1},{x:1,y:-1},
            {x:-1,y: 0},{x:0,y: 0},{x:1,y: 0},
            {x:-1,y: 1},{x:0,y: 1},{x:1,y: 1},
        ],
        color: PALETTE[0], bbox: { w:3, h:3 }
    },
    banner: {
        id: 'banner', label: 'Banner',
        shape: [{x:0,y:0}],
        color: PALETTE[5], bbox: { w:1, h:1 },
        bannerRange: 3
    },
    hq: {
        id: 'hq', label: 'HQ',
        shape: [
            {x:-1,y:-1},{x:0,y:-1},{x:1,y:-1},
            {x:-1,y: 0},{x:0,y: 0},{x:1,y: 0},
            {x:-1,y: 1},{x:0,y: 1},{x:1,y: 1},
        ],
        color: PALETTE[5], bbox: { w:3, h:3 },
        bannerRange: 7
    }
};
