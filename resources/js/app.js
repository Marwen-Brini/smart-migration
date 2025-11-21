import { createApp } from 'vue';
import Dashboard from './components/Dashboard.vue';
import '../css/app.css';

const app = createApp(Dashboard);

app.mount('#app');
