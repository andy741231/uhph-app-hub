import axios from 'axios';
import { route } from '../../vendor/tightenco/ziggy';

window.axios = axios;

// Provide an asset() helper similar to Laravel's, using the request-derived app URL
// so URLs respect the /apps/doc-review mount point.
window.asset = (path) => {
    const appUrl = document.querySelector('meta[name="app-url"]')?.content;
    const baseUrl = appUrl || window.Ziggy?.url || window.location.origin;
    return `${baseUrl.replace(/\/$/, '')}/${(path || '').replace(/^\//, '')}`;
};

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

window.axios.interceptors.request.use((config) => {
    let isSameOrigin = false;
    try {
        const reqUrl = new URL(config.url, window.location.origin);
        isSameOrigin = reqUrl.origin === window.location.origin;

        if (isSameOrigin && window.location.protocol === 'https:' && reqUrl.protocol === 'http:') {
            reqUrl.protocol = 'https:';
            config.url = reqUrl.toString();
        }
    } catch (e) {
        isSameOrigin = true;
    }

    if (isSameOrigin) {
        config.headers['Accept'] = 'application/json';
        config.withCredentials = true;
    }

    return config;
});
