import {createRouter, createWebHistory, useRoute} from 'vue-router'
import GridPainter from "./components/GridPainter.vue";

declare global {
    interface Window {
        DRAFT_ID?: string;
    }
}

const routes = [
    {path: '/', component: GridPainter},
    {
        path: '/save/:id', component: () => {
            window.DRAFT_ID = useRoute().params.id as string;
            return GridPainter;
        }
    },
    // Optional: 404
    // {path: '/:pathMatch(.*)*', name: 'NotFound', component: () => import('./views/NotFound.vue')},
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
