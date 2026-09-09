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
        var m = document.getElementById('osoe-modal');
        if (!m) return false;

        var type = btn.getAttribute('data-type') || '';
        var label = btn.getAttribute('data-label') || '';
        var block = btn.closest('.osoe-block');
        var material = block ? block.getAttribute('data-material') || '' : '';

        document.getElementById('osoe-type').value = type;
        var title = m.querySelector('.osoe-modal-title');
        if (title) title.textContent = label;
        var materialEl = document.getElementById('osoe-material');
        if (materialEl) materialEl.value = material;

        m.classList.add('osoe-modal-open');
        m.style.display = 'flex';
        m.style.opacity = '1';
        m.style.visibility = 'visible';
        m.style.pointerEvents = 'auto';
        document.documentElement.style.overflow = 'hidden';
        refreshCaptcha();
        return true;
    }

    function closeModal() {
        var m = document.getElementById('osoe-modal');
        var f = document.getElementById('osoe-form');
        if (m) {
            m.classList.remove('osoe-modal-open');
            m.style.display = 'none';
            m.setAttribute('aria-hidden', 'true');
        }
        document.documentElement.style.overflow = '';
        if (f) {
            f.reset();
            var msgs = f.querySelector('.osoe-form-messages');
            if (msgs) msgs.innerHTML = '';
        }
    }

    function refreshCaptcha() {
        var f = document.getElementById('osoe-form');
        if (!f) return;
        var q = f.querySelector('.osoe-captcha-question');
        var k = document.getElementById('osoe-captcha-key');
        var a = document.getElementById('osoe-captcha');
        if (!q || !k || !a) return;

        var body = new URLSearchParams();
        body.append('action', 'osoe_captcha');
        body.append('nonce', (window.osoe && window.osoe.nonce) || '');

        fetch((window.osoe && window.osoe.ajax_url) || '/wp-admin/admin-ajax.php', {
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
        var f = document.getElementById('osoe-form');
        if (!f) return;
        var m = f.querySelector('.osoe-form-messages');
        if (!m) return;
        m.innerHTML = '<div class="osoe-' + kind + '">' + html + '</div>';
    }

    function validate() {
        var errors = [];
        var s = (window.osoe && window.osoe.strings) || {};
        var addressEl = document.getElementById('osoe-address');
        var nameEl = document.getElementById('osoe-name');
        var emailEl = document.getElementById('osoe-email');
        var phoneEl = document.getElementById('osoe-phone');

        if (addressEl && !addressEl.value.trim()) {
            errors.push(s.required || 'Это поле обязательно');
        }
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
        if (phoneEl && !phoneEl.value.trim()) {
            errors.push(s.required || 'Это поле обязательно');
        }

        return errors.length ? { error: errors.join('<br>') } : null;
    }

    function submitForm() {
        var f = document.getElementById('osoe-form');
        if (!f) return;
        var v = validate();
        if (v) {
            showMessages(v.error, 'error');
            return;
        }
        var btn = f.querySelector('.osoe-submit-btn');
        var originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Отправка...';
        }

        var formData = new FormData(f);
        formData.append('action', 'osoe_submit');
        formData.append('nonce', (window.osoe && window.osoe.nonce) || '');

        fetch((window.osoe && window.osoe.ajax_url) || '/wp-admin/admin-ajax.php', {
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
                showMessages(response.data.message || ((window.osoe && window.osoe.strings && window.osoe.strings.success) || 'Заявка отправлена!'), 'success');
                f.reset();
                setTimeout(closeModal, 2500);
            } else {
                var err = (response && response.data && response.data.errors) ? Object.values(response.data.errors).join('<br>') : ((window.osoe && window.osoe.strings && window.osoe.strings.error) || 'Ошибка отправки.');
                showMessages(err, 'error');
                refreshCaptcha();
            }
        })
        .catch(function() {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            var s = (window.osoe && window.osoe.strings) || {};
            showMessages(s.error || 'Ошибка отправки. Попробуйте позже.', 'error');
            refreshCaptcha();
        });
    }

    function bindModal() {
        var m = document.getElementById('osoe-modal');
        if (!m) return;
        m.addEventListener('click', function(e) {
            var t = e.target;
            if (!t) return;
            if (t.classList && t.classList.contains('osoe-modal-close')) {
                closeModal();
                return;
            }
            if (t === m) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && m.classList.contains('osoe-modal-open')) {
                closeModal();
            }
        });
    }

    function bindButtons() {
        var btns = document.querySelectorAll('.osoe-btn');
        for (var i = 0; i < btns.length; i++) {
            (function(b) {
                if (b.__osoeBound) return;
                b.__osoeBound = true;
                var onActivate = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openModal(b);
                };
                b.addEventListener('click', onActivate, false);
            })(btns[i]);
        }
    }

    ready(function() {
        bindModal();
        bindButtons();
        var f = document.getElementById('osoe-form');
        if (f) {
            f.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm();
            });
            var refreshLink = f.querySelector('.osoe-captcha-refresh');
            if (refreshLink) {
                refreshLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    refreshCaptcha();
                });
            }
        }
    });
})();
