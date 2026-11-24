/*
 * EchoCall client area for WHMCS, module 2.0.0.
 *
 * - "auto" theme: reads the background of the WHMCS page around the module
 *   and picks light or dark to match, falling back to the system setting.
 * - Forms marked data-ec-action post to the module with the per service
 *   token and show the answer in place. Without JavaScript nothing is sent.
 *
 * No dependencies, no inline handlers, works with a strict CSP.
 */
(function () {
    'use strict';

    var root = document.querySelector('.ec-root');
    if (!root) {
        return;
    }

    // ── Theme ────────────────────────────────────────────────────────────

    function pageBrightness() {
        var el = root.parentElement;
        while (el) {
            var match = /rgba?\(([^)]+)\)/.exec(window.getComputedStyle(el).backgroundColor || '');
            if (match) {
                var parts = match[1].split(/[\s,/]+/).filter(Boolean).map(parseFloat);
                var alpha = parts.length > 3 ? parts[3] : 1;
                if (alpha > 0.5) {
                    return (0.2126 * parts[0] + 0.7152 * parts[1] + 0.0722 * parts[2]) / 255;
                }
            }
            el = el.parentElement;
        }
        return null;
    }

    var systemDark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function applyScheme() {
        var theme = root.getAttribute('data-ec-theme');
        var dark;
        if (theme === 'dark' || theme === 'light') {
            dark = theme === 'dark';
        } else {
            var brightness = pageBrightness();
            dark = brightness === null ? !!(systemDark && systemDark.matches) : brightness < 0.5;
        }
        root.setAttribute('data-ec-scheme', dark ? 'dark' : 'light');
    }

    applyScheme();
    if (root.getAttribute('data-ec-theme') === 'auto') {
        // Themes with their own light and dark switch flip a class on html or body.
        if (window.MutationObserver) {
            var observer = new MutationObserver(applyScheme);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'style'] });
            if (document.body) {
                observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'style'] });
            }
        }
        if (systemDark && systemDark.addEventListener) {
            systemDark.addEventListener('change', applyScheme);
        }
    }

    // ── Feedback ─────────────────────────────────────────────────────────

    var toast = root.querySelector('.ec-toast');
    var toastTimer = 0;
    var msgError = root.getAttribute('data-ec-msg-error') || 'Error';
    var msgSession = root.getAttribute('data-ec-msg-session') || msgError;
    var msgCopied = root.getAttribute('data-ec-msg-copied') || 'OK';

    function show(message, kind) {
        if (!toast) {
            return;
        }
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.setAttribute('data-kind', kind);
        toast.hidden = false;
        toastTimer = window.setTimeout(function () {
            toast.hidden = true;
        }, kind === 'error' ? 7000 : 4000);
    }

    function setBusy(form, busy) {
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        var controls = form.querySelectorAll('button, input, select');
        for (var i = 0; i < controls.length; i++) {
            controls[i].disabled = busy;
        }
    }

    // ── Actions ──────────────────────────────────────────────────────────

    var endpoint = root.getAttribute('data-ec-endpoint') || '';
    var token = root.getAttribute('data-ec-token') || '';
    var whmcsToken = root.getAttribute('data-ec-whmcs-token') || '';
    var lastSubmitter = null;

    root.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('button[type="submit"]') : null;
        lastSubmitter = button && root.contains(button) ? button : null;
    }, true);

    root.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-ec-action')) {
            return;
        }
        event.preventDefault();

        var question = form.getAttribute('data-ec-confirm');
        if (question && !window.confirm(question)) {
            return;
        }

        var data = new FormData(form);
        var submitter = event.submitter || lastSubmitter;
        if (submitter && submitter.name && form.contains(submitter)) {
            data.set(submitter.name, submitter.value);
        }
        data.set('ec_action', form.getAttribute('data-ec-action'));
        data.set('ec_token', token);
        if (whmcsToken) {
            data.set('token', whmcsToken);
        }

        setBusy(form, true);
        window.fetch(endpoint, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.text().then(function (text) {
                var body = null;
                try {
                    body = JSON.parse(text);
                } catch (e) {
                    body = null;
                }
                return { ok: response.ok, body: body };
            });
        }).then(function (result) {
            if (!result.body || typeof result.body !== 'object') {
                // WHMCS answered with a page, usually the login after the session ran out.
                show(msgSession, 'error');
                return;
            }
            if (!result.ok || !result.body.ok) {
                show(typeof result.body.error === 'string' ? result.body.error : msgError, 'error');
                return;
            }
            handle(form, result.body);
        }).catch(function () {
            show(msgError, 'error');
        }).then(function () {
            setBusy(form, false);
        });
    });

    function handle(form, body) {
        if (typeof body.redirect === 'string') {
            // Only ever the invoice of a top up.
            if (/^viewinvoice\.php\?id=\d+$/.test(body.redirect)) {
                window.location.href = body.redirect;
            }
            return;
        }
        if (typeof body.code === 'string') {
            var target = document.getElementById(form.getAttribute('data-ec-target') || '');
            var area = target ? target.querySelector('textarea') : null;
            if (area) {
                area.value = body.code;
                target.hidden = false;
                area.focus();
                area.select();
            }
            return;
        }
        if (typeof body.message === 'string') {
            show(body.message, 'ok');
        }
        if (body.reload) {
            window.setTimeout(function () {
                window.location.reload();
            }, 700);
        }
    }

    // ── Copy ─────────────────────────────────────────────────────────────

    root.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-ec-copy]') : null;
        if (!button) {
            return;
        }
        var area = document.getElementById(button.getAttribute('data-ec-copy'));
        if (!area) {
            return;
        }
        var copied = function () {
            show(msgCopied, 'ok');
        };
        var fallback = function () {
            area.focus();
            area.select();
            try {
                if (document.execCommand('copy')) {
                    copied();
                }
            } catch (e) {
                // Selected, so the client can copy by hand.
            }
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(area.value).then(copied, fallback);
        } else {
            fallback();
        }
    });
})();
