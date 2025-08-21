// RealtimeKit UI Kit Initialization
import { defineCustomElements } from 'https://cdn.jsdelivr.net/npm/@cloudflare/realtimekit-ui@latest/loader/index.es2017.js';

// Define custom elements when script loads
defineCustomElements();

// Make it available globally for other scripts
window.RealtimeKitUILoaded = true;
