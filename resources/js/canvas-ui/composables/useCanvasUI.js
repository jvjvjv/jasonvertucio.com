import { computed } from 'vue';
import axios from 'axios';

export function useCanvasUI() {
    const CanvasUI = computed(() => window.CanvasUI);

    const isEditor = computed(() => {
        return CanvasUI.value?.user ? CanvasUI.value.user.role === 2 : false;
    });

    const isAdmin = computed(() => {
        return CanvasUI.value?.user ? CanvasUI.value.user.role === 3 : false;
    });

    function request() {
        const instance = axios.create();

        instance.defaults.headers.common['X-CSRF-TOKEN'] =
            document.head.querySelector('meta[name="csrf-token"]').content;
        instance.defaults.baseURL = '/canvas-ui';

        const requestHandler = (request) => {
            return request;
        };

        const errorHandler = (error) => {
            switch (error.response?.status) {
                case 401:
                case 405:
                    window.location.href = `/${CanvasUI.value.canvasPath}/logout`;
                    break;
                default:
                    break;
            }

            return Promise.reject({ ...error });
        };

        const successHandler = (response) => {
            return response;
        };

        instance.interceptors.request.use((request) => requestHandler(request));

        instance.interceptors.response.use(
            (response) => successHandler(response),
            (error) => errorHandler(error)
        );

        return instance;
    }

    /**
     * Parse a given url and return the different components.
     *
     * @param url
     * @link https://www.abeautifulsite.net/parsing-urls-in-javascript
     */
    function parseURL(url) {
        const parser = document.createElement('a');
        const searchObject = {};

        parser.href = url;
        const queries = parser.search.replace(/^\?/, '').split('&');

        for (let i = 0; i < queries.length; i++) {
            const split = queries[i].split('=');
            searchObject[split[0]] = split[1];
        }

        return {
            protocol: parser.protocol,
            host: parser.host,
            hostname: parser.hostname,
            port: parser.port,
            pathname: parser.pathname,
            search: parser.search,
            searchObject: searchObject,
            hash: parser.hash,
        };
    }

    return {
        CanvasUI,
        isEditor,
        isAdmin,
        request,
        parseURL,
    };
}
