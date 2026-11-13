/**
 * HTTP requests for the Pay. storefront plugins.
 *
 * Behaves like Shopware's HttpClient (which is removed in Shopware 6.8), so the
 * bundle does not depend on Shopware's storefront sources.
 */
export default class HttpClient {
    /**
     * Sends a POST request. The callback runs once the request has ended,
     * also for error responses, with the response text and the request.
     *
     * @param {string} url
     * @param {FormData|string|null} data
     * @param {function(string, XMLHttpRequest): void} callback
     * @param {string} contentType
     * @returns {XMLHttpRequest}
     */
    post(url, data, callback, contentType = 'application/json') {
        const request = new XMLHttpRequest();

        request.open('POST', url);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // For FormData the browser sets the content type itself, including the multipart boundary.
        if (!(data instanceof FormData) && contentType) {
            request.setRequestHeader('Content-type', contentType);
        }

        if (callback) {
            request.addEventListener('loadend', () => callback(request.responseText, request));
        }

        request.send(data);

        return request;
    }
}
