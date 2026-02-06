import './bootstrap';
import Alpine from 'alpinejs';
import { marked } from 'marked';

// Configure marked for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
});

window.Alpine = Alpine;
window.marked = marked;
Alpine.start();
