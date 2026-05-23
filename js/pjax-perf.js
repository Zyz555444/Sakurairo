(function () {
    'use strict';

    if (!window._iro || !_iro.pjax) {
        return;
    }

    var prefetched = new Set();
    var loadedStylesheets = new Set();

    function normalizeUrl(url) {
        try {
            return new URL(url, window.location.origin).href;
        } catch (e) {
            return url;
        }
    }

    function prefetchUrl(url) {
        var normalized = normalizeUrl(url);
        if (!normalized || prefetched.has(normalized) || normalized === window.location.href) {
            return;
        }
        prefetched.add(normalized);
        var link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = normalized;
        document.head.appendChild(link);
    }

    function trackStylesheets() {
        document.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
            if (link.href) {
                loadedStylesheets.add(normalizeUrl(link.href));
            }
        });
    }

    function dedupeStylesheets(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href) {
                return;
            }
            var normalized = normalizeUrl(href);
            if (loadedStylesheets.has(normalized)) {
                link.remove();
                return;
            }
            loadedStylesheets.add(normalized);
        });
    }

    trackStylesheets();

    document.addEventListener('mouseover', function (event) {
        var anchor = event.target.closest('a[href]');
        if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download')) {
            return;
        }
        var href = anchor.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
            return;
        }
        try {
            var url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) {
                return;
            }
            prefetchUrl(url.href);
        } catch (e) {
            return;
        }
    }, { passive: true });

    document.addEventListener('pjax:complete', function () {
        dedupeStylesheets(document.head);
        trackStylesheets();
    });
})();
