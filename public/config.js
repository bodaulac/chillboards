// Frontend API Configuration
// Update this file to point to NAS backend via Cloudflare Tunnel

// BEFORE (Local/VPS backend)
// const API_URL = 'http://localhost:3000/api';

// Updated for VPS deployment (proxied by Nginx)
const API_URL = '/api';

// For development
// const API_URL = 'http://localhost:3000/api';

console.log('API URL:', API_URL);

// Export for use in app.js
window.API_CONFIG = {
    baseURL: API_URL,
    timeout: 30000, // 30 seconds
};
