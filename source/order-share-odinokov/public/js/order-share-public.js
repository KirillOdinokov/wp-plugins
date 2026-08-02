(function() {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function openModal(btn) {
        var m = document.getElementById('oso-order-modal');
        if (!m) {
            return false;
        }
        var pn = document.getElementById('oso-product-name');
        if (pn) pn.value = btn.getAttribute('data-product-name') || '';
        var tt = m.querySelector('.oso-product-title');
        if (tt) tt.textContent = btn.getAttribute('data-product-name') || '';
        m.classList.add('oso-modal-open');
        m.style.display = 'flex';
        m.style.opacity = '1';
        m.style.visibility = 'visible';
        m.style.pointerEvents = 'auto';
        m.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        return true;
    }

    function bindAll() {
        var btns = document.querySelectorAll('.oso-order-btn');
        for (var i = 0; i < btns.length; i++) {
            (function(b) {
                if (b.__osoOrderBound) return;
                b.__osoOrderBound = true;
                var onActivate = function(e) {
                    try {
                        e.preventDefault();
                        e.stopPropagation();
                        if (typeof e.stopImmediatePropagation === 'function') {
                            e.stopImmediatePropagation();
                        }
                        if (openModal(b)) {
                            refreshCaptcha();
                        } else {
                            var d = document.createElement('div');
                            d.style.cssText = 'position:fixed;top:10px;left:10px;background:#c00;color:#fff;padding:10px;z-index:2147483647;font:14px sans-serif;';
                            d.textContent = 'OSO: modal #oso-order-modal not found in DOM.';
                            document.body.appendChild(d);
                            setTimeout(function(){ d.remove(); }, 5000);
                        }
                    } catch(err) {
                        var d2 = document.createElement('div');
                        d2.style.cssText = 'position:fixed;top:10px;left:10px;background:#c00;color:#fff;padding:10px;z-index:2147483647;font:14px sans-serif;max-width:90%;';
                        d2.textContent = 'OSO error: ' + (err && err.message ? err.message : String(err));
                        document.body.appendChild(d2);
                        setTimeout(function(){ d2.remove(); }, 5000);
                    }
                };
                b.addEventListener('click', onActivate, false);
                b.addEventListener('touchend', onActivate, false);
                b.addEventListener('pointerup', onActivate, false);
            })(btns[i]);
        }
    }

    function getShareData(btn) {
        var wrap = btn.closest('.oso-btn-row');
        if (!wrap) return null;
        try {
            var raw = wrap.getAttribute('data-oso-share');
            if (!raw) return null;
            return JSON.parse(raw) || null;
        } catch(e) { return null; }
    }

    function fallbackShare(d) {
        if (!d || !d.url) return false;
        try {
            var sep = '%0D%0A%0D%0A';
            var u = 'mailto:?subject=' + encodeURIComponent(d.title || '') + '&body=' + encodeURIComponent((d.url || '') + (d.desc ? sep + d.desc : ''));
            window.location.href = u;
            return true;
        } catch(e) { return false; }
    }

    function openPrint(d) {
        try {
            if (d && d.pdfName) {
                var prev = document.title;
                document.title = d.pdfName;
                setTimeout(function(){ window.print(); }, 0);
                setTimeout(function(){ document.title = prev; }, 1500);
            } else {
                window.print();
            }
        } catch(e) { try { window.print(); } catch(_){} }
    }

    function bindShareButtons() {
        var btns = document.querySelectorAll('.oso-btn-share, .oso-btn-pdf');
        for (var i = 0; i < btns.length; i++) {
            (function(b) {
                if (b.__osoShareBound) return;
                b.__osoShareBound = true;
                var onActivate = function(e) {
                    try {
                        e.preventDefault();
                        e.stopPropagation();
                        if (typeof e.stopImmediatePropagation === 'function') {
                            e.stopImmediatePropagation();
                        }
                        var d = getShareData(b) || {};
                        if (b.classList.contains('oso-btn-share')) {
                            if (navigator.share && d.title && d.url) {
                                navigator.share({ title: d.title, text: d.desc || d.title, url: d.url }).catch(function(){});
                            } else if (navigator.clipboard && navigator.clipboard.writeText && d.url) {
                                navigator.clipboard.writeText(d.url).then(function(){
                                    b.classList.add('is-shared');
                                    setTimeout(function(){ b.classList.remove('is-shared'); }, 1200);
                                }).catch(function(){ fallbackShare(d); });
                            } else {
                                fallbackShare(d);
                            }
                        } else if (b.classList.contains('oso-btn-pdf')) {
                            openPrint(d);
                        }
                    } catch(err) {
                        var d2 = document.createElement('div');
                        d2.style.cssText = 'position:fixed;top:10px;left:10px;background:#c00;color:#fff;padding:10px;z-index:2147483647;font:14px sans-serif;max-width:90%;';
                        d2.textContent = 'OSO share error: ' + (err && err.message ? err.message : String(err));
                        document.body.appendChild(d2);
                        setTimeout(function(){ d2.remove(); }, 5000);
                    }
                };
                b.addEventListener('click', onActivate, false);
                b.addEventListener('touchend', onActivate, false);
                b.addEventListener('pointerup', onActivate, false);
            })(btns[i]);
        }
    }

    ready(function() {
        bindAll();
        bindShareButtons();
        bindModal();
        bindForm();
        // Re-bind periodically in case buttons are added dynamically.
        setTimeout(bindAll, 200);
        setTimeout(bindShareButtons, 200);
        setTimeout(bindAll, 1000);
        setTimeout(bindShareButtons, 1000);
    });

    function bindModal() {
        var m = document.getElementById('oso-order-modal');
        if (!m) return;
        m.addEventListener('click', function(e) {
            var t = e.target;
            if (!t) return;
            if (t.classList && t.classList.contains('oso-modal-close')) {
                closeModal();
                return;
            }
            if (t === m) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && m.classList.contains('oso-modal-open')) {
                closeModal();
            }
        });
    }

    function closeModal() {
        var m = document.getElementById('oso-order-modal');
        var f = document.getElementById('oso-order-form');
        if (m) {
            m.classList.remove('oso-modal-open');
            m.style.display = 'none';
            m.setAttribute('aria-hidden', 'true');
        }
        document.documentElement.style.overflow = '';
        if (f) {
            f.reset();
            var msgs = f.querySelector('.oso-form-messages');
            if (msgs) msgs.innerHTML = '';
            var da = f.querySelector('.oso-delivery-address');
            if (da) da.style.display = 'none';
        }
    }

    function bindForm() {
        var f = document.getElementById('oso-order-form');
        if (!f) return;
        f.addEventListener('change', function(e) {
            var t = e.target;
            if (t && t.name === 'delivery') {
                var addr = f.querySelector('.oso-delivery-address');
                if (addr) {
                    addr.style.display = (t.value === 'yes') ? '' : 'none';
                }
            }
        });
        f.addEventListener('click', function(e) {
            var t = e.target;
            if (t && t.classList && t.classList.contains('oso-captcha-refresh')) {
                e.preventDefault();
                refreshCaptcha();
            }
        });
        f.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm();
        });
    }

    function refreshCaptcha() {
        var f = document.getElementById('oso-order-form');
        if (!f) return;
        var q = f.querySelector('.oso-captcha-question');
        var k = document.getElementById('oso-captcha-key');
        var a = document.getElementById('oso-captcha');
        if (!q || !k || !a) return;

        var body = new URLSearchParams();
        body.append('action', 'oso_refresh_captcha');
        body.append('nonce', (window.oso_order && window.oso_order.nonce) || '');

        fetch((window.oso_order && window.oso_order.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response && response.success && response.data) {
                q.textContent = response.data.question;
                k.value = response.data.key;
                a.value = '';
            }
        })
        .catch(function() {});
    }

    function showMessages(html, kind) {
        var f = document.getElementById('oso-order-form');
        if (!f) return;
        var m = f.querySelector('.oso-form-messages');
        if (!m) return;
        m.innerHTML = '<div class="oso-' + kind + '">' + html + '</div>';
    }

    function validate() {
        var errors = [];
        var nameEl  = document.getElementById('oso-name');
        var emailEl = document.getElementById('oso-email');
        var s = (window.oso_order && window.oso_order.strings) || {};

        if (nameEl && !nameEl.value.trim()) {
            errors.push(s.required || 'Это поле обязательно');
        }
        if (emailEl) {
            var email = emailEl.value.trim();
            if (!email) {
                errors.push(s.required || 'Это поле обязательно');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push(s.email || 'Введите корректный email');
            }
        }

        var fileInput = document.getElementById('oso-files');
        if (fileInput && fileInput.files && fileInput.files.length) {
            var files = fileInput.files;
            if (files.length > 3) {
                return { error: (s.maxfiles || 'Максимум 3 файла.') };
            }
            var allowed = ['jpg','jpeg','pdf','dwg','png','webp','doc','xls','csv'];
            var maxSize = 20 * 1024 * 1024;
            for (var i = 0; i < files.length; i++) {
                var ext = (files[i].name.split('.').pop() || '').toLowerCase();
                if (allowed.indexOf(ext) === -1) {
                    return { error: (s.filetype || 'Недопустимый формат файла.') + ' (' + files[i].name + ')' };
                }
                if (files[i].size > maxSize) {
                    return { error: (s.filesize || 'Файл слишком большой. Максимум 20 МБ.') + ' (' + files[i].name + ')' };
                }
            }
        }
        return errors.length ? { error: errors.join('<br>') } : null;
    }

    function submitForm() {
        var f = document.getElementById('oso-order-form');
        if (!f) return;
        var v = validate();
        if (v) {
            showMessages(v.error, 'error');
            return;
        }
        var btn = f.querySelector('.oso-submit-btn');
        var originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Отправка...';
        }

        var formData = new FormData(f);
        formData.append('action', 'oso_order_submit');
        formData.append('nonce', (window.oso_order && window.oso_order.nonce) || '');

        fetch((window.oso_order && window.oso_order.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            if (response && response.success) {
                showMessages(response.data.message || (window.oso_order && window.oso_order.strings && window.oso_order.strings.success) || 'Заявка отправлена!', 'success');
                f.reset();
                var da = f.querySelector('.oso-delivery-address');
                if (da) da.style.display = 'none';
                refreshCaptcha();
                setTimeout(closeModal, 2500);
            } else {
                var err = (response && response.data && response.data.errors) ? Object.values(response.data.errors).join('<br>') : ((window.oso_order && window.oso_order.strings && window.oso_order.strings.error) || 'Ошибка отправки.');
                showMessages(err, 'error');
                refreshCaptcha();
            }
        })
        .catch(function() {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            var s = (window.oso_order && window.oso_order.strings) || {};
            showMessages(s.error || 'Ошибка отправки. Попробуйте позже.', 'error');
            refreshCaptcha();
        });
    }
})();
