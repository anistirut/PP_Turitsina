(function () {
    var form = document.querySelector('[data-live-form]');
    var results = document.getElementById('live-results');

    if (!form || !results) {
        return;
    }

    var DEBOUNCE_MS = 300;
    var timer = null;
    var controller = null;

    function buildUrl() {
        var params = new URLSearchParams(window.location.search);
        var data = new FormData(form);

        form.querySelectorAll('input[name], select[name]').forEach(function (field) {
            var value = (data.get(field.name) || '').toString().trim();
            if (value === '') {
                params.delete(field.name);
            } else {
                params.set(field.name, value);
            }
        });

        params.delete('page');

        var query = params.toString();
        return window.location.pathname + (query ? '?' + query : '');
    }

    function load(url) {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        results.classList.add('is-loading');

        fetch(url, { signal: controller.signal, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.getElementById('live-results');

                if (!fresh) {
                    window.location.href = url;
                    return;
                }

                results.innerHTML = fresh.innerHTML;

                var freshExport = doc.getElementById('export-link');
                var currentExport = document.getElementById('export-link');
                if (freshExport && currentExport) {
                    currentExport.setAttribute('href', freshExport.getAttribute('href'));
                }

                window.history.replaceState(null, '', url);
                results.classList.remove('is-loading');
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    results.classList.remove('is-loading');
                }
            });
    }

    function schedule(delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            load(buildUrl());
        }, delay);
    }

    form.addEventListener('input', function (event) {
        if (event.target.tagName === 'SELECT') {
            return;
        }
        schedule(DEBOUNCE_MS);
    });

    form.addEventListener('change', function (event) {
        if (event.target.tagName === 'SELECT') {
            schedule(0);
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        schedule(0);
    });

    results.addEventListener('click', function (event) {
        var link = event.target.closest('th a, .pagination a');

        if (!link || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        load(link.getAttribute('href'));
    });
})();
