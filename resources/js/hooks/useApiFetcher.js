import { useState } from 'react';
import api from '../api';

/**
 * useApiFetcher
 * ─────────────
 * A shared hook that replaces the ad-hoc `useFetcher` copies scattered
 * across pages.  Uses the centralized axios instance so every call
 * automatically gets the right base-URL, headers, and CSRF token.
 *
 * Usage:
 *   const fetcher = useApiFetcher();
 *
 *   // GET
 *   fetcher.get('/dashboard').then(data => ...);
 *
 *   // POST (plain object → sent as JSON)
 *   fetcher.post('/smart_upload/create', { action: 'fetch_folders' });
 *
 *   // POST (FormData → multipart, e.g. file uploads)
 *   fetcher.post('/upload_item', formData);
 *
 * State:
 *   fetcher.state  → 'idle' | 'submitting' | 'loading'
 *   fetcher.data   → last response data (null initially)
 *   fetcher.error  → last error message (null if no error)
 */
export function useApiFetcher() {
    const [state, setState] = useState('idle');
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    // Mirror formData so callers can check fetcher.formData?.get('action')
    const [formData, setFormData] = useState(null);

    const request = async (method, url, payload = null) => {
        setState(method === 'get' ? 'loading' : 'submitting');
        setError(null);
        if (payload instanceof FormData || (payload && typeof payload === 'object' && !(payload instanceof URLSearchParams))) {
            setFormData(payload instanceof FormData ? payload : new URLSearchParams(payload));
        }

        try {
            let response;
            if (method === 'get') {
                response = await api.get(url, { params: payload });
            } else {
                const isFormData = payload instanceof FormData;
                response = await api.post(url, payload, isFormData ? {
                    headers: { 'Content-Type': 'multipart/form-data' }
                } : {});
            }
            setData(response.data);
            return response.data;
        } catch (err) {
            const msg = err.response?.data?.error || err.message || 'Request failed';
            setError(msg);
            setData(err.response?.data || null);
            console.error(`[useApiFetcher] ${method.toUpperCase()} ${url}:`, err);
            return null;
        } finally {
            setState('idle');
        }
    };

    return {
        state,
        data,
        error,
        formData,
        get:  (url, params)   => request('get', url, params),
        post: (url, payload)  => request('post', url, payload),
        /** Legacy-compat: submit({ action }, { method, action }) */
        submit: (payload, options = {}) => {
            const method = (options.method || 'post').toLowerCase();
            // derive path: options.action OR current path stripped of /app prefix
            let path = options.action
                ? options.action.replace('/app', '').replace('/api', '')
                : window.location.pathname.replace('/app', '').replace('/api', '');
            if (!path.startsWith('/')) path = '/' + path;
            return request(method, path, payload);
        },
    };
}
