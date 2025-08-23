import { createApp } from 'vue'
import './resources/css/app.css'
import App from './App.vue'
import router from "./router.ts";

createApp(App).use(router).mount('#app')
