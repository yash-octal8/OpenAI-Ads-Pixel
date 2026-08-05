import axios from 'axios';

/**
 * Centralized Axios instance for all API calls.
 * - Base URL is /api so every call is automatically prefixed.
 * - Sends / accepts JSON.
 * - Reads XSRF-TOKEN cookie and forwards it as X-XSRF-TOKEN header (Laravel CSRF).
 */
const api = axios.create({
    baseURL: '/api',
    adapter: 'fetch', // Use fetch so Shopify App Bridge intercepts and adds the session token
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,   // send cookies (session / CSRF)
});

// ── Request interceptor ──────────────────────────────────────────────────────
api.interceptors.request.use((config) => {
    // Read the XSRF-TOKEN cookie that Laravel sets and forward it as a header
    const xsrfToken = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    if (xsrfToken) {
        config.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }

    return config;
});

// ── Response interceptor ─────────────────────────────────────────────────────
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Session expired – reload so Laravel can redirect to re-auth
            window.location.reload();
        }
        return Promise.reject(error);
    }
);

export default api;
