/*
 * Adds the page's CSRF token to same-origin POST requests to /backend/*.php.
 * The backend endpoints reject state-changing requests without a valid token
 * (requireCsrfPost), so every script that POSTs to them is covered by this one file.
 * Load it before any other script that calls fetch().
 */
(function () {
    var nativeFetch = window.fetch;

    function currentToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function isBackendPost(url, init) {
        var method = ((init && init.method) || 'GET').toUpperCase();
        return method === 'POST' && /\/backend\/[^?#]+\.php/.test(url);
    }

    window.fetch = function (input, init) {
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        if (init && isBackendPost(url, init)) {
            var token = currentToken();
            var body = init.body;

            if (typeof FormData !== 'undefined' && body instanceof FormData) {
                if (!body.has('csrf_token')) body.append('csrf_token', token);
            } else if (typeof body === 'string' || body === undefined || body === null) {
                var text = typeof body === 'string' ? body : '';
                if (!/(^|&)csrf_token=/.test(text)) {
                    text += (text ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                }
                init.body = text;
                init.headers = Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, init.headers || {});
            }
        }
        return nativeFetch.call(this, input, init);
    };
})();