import axios from 'axios';

/**
 * Axios Configuration for Laravel Sanctum SPA Authentication
 * 
 * This configuration implements proper SPA authentication as per Laravel Sanctum documentation:
 * https://laravel.com/docs/12.x/sanctum#spa-authentication
 * 
 * For SPAs, Sanctum uses stateful authentication with CSRF protection:
 * 1. First request gets CSRF cookie from /sanctum/csrf-cookie
 * 2. Subsequent requests include CSRF token in headers
 * 3. Authentication uses session cookies (not Bearer tokens)
 * 4. CSRF protection prevents cross-site request forgery
 */

// Configure Axios for API communication
window.axios = axios;

// Set default headers for API requests
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.headers.common['Content-Type'] = 'application/json';

// Configure base URL for API (use same origin as SPA for Sanctum)
window.axios.defaults.baseURL = '/api';

// Enable credentials for SPA authentication (required for CSRF cookies and sessions)
window.axios.defaults.withCredentials = true;

// Request interceptor: Add locale headers to all API requests
window.axios.interceptors.request.use(
    (config) => {
        // Add request start time for performance logging
        if (import.meta.env.DEV) {
            config.metadata = { startTime: new Date() };
        }

        // Get current language from localStorage or default to 'en'
        const currentLanguage = localStorage.getItem('language') || 'en';
        
        // Add locale headers for proper content negotiation
        config.headers['X-Locale'] = currentLanguage;
        config.headers['Accept-Language'] = currentLanguage;
        
        return config;
    },
    (error) => {
        console.error('Request interceptor error:', error);
        return Promise.reject(error);
    }
);

// Response interceptor: Handle authentication errors and locale-related errors
window.axios.interceptors.response.use(
    (response) => {
        // Log response time in development
        if (import.meta.env.DEV && response.config.metadata) {
            const endTime = new Date();
            const duration = endTime.getTime() - response.config.metadata.startTime.getTime();
            console.log(`API Request: ${response.config.method?.toUpperCase()} ${response.config.url} - ${duration}ms`);
        }
        
        return response;
    },
    async (error) => {
        
        // Handle 401 Unauthorized responses
        if (error.response?.status === 401) {
            // For SPA authentication, redirect to login on 401
            // Session-based auth doesn't support token refresh like API tokens
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/login';
            }
            return Promise.reject(error);
        }
        
        // Handle 419 CSRF token mismatch
        if (error.response?.status === 419) {
            console.warn('CSRF token mismatch, refreshing page...');
            window.location.reload();
            return Promise.reject(error);
        }
        
        // Handle locale-related errors (422 with locale validation errors)
        if (error.response?.status === 422 && error.config?.url?.includes('/locale/')) {
            console.warn('Locale validation error:', error.response.data);
            // Dispatch custom event for locale error handling
            window.dispatchEvent(new CustomEvent('localeError', {
                detail: {
                    message: error.response.data.message || 'Invalid locale provided',
                    errors: error.response.data.errors
                }
            }));
        }
        
        // Handle other HTTP errors
        if (error.response?.status >= 500) {
            console.error('Server error:', error.response.status, error.response.data);
        }
        
        return Promise.reject(error);
    }
);

// Utility function to update locale headers dynamically
window.updateAxiosLocaleHeaders = (locale) => {
    // Update default headers for future requests
    window.axios.defaults.headers.common['X-Locale'] = locale;
    window.axios.defaults.headers.common['Accept-Language'] = locale;
    
    // Store in localStorage for persistence
    localStorage.setItem('language', locale);
};
