(function() {
    'use strict';

    var form = document.getElementById('oso-director-form');
    if (!form) return;

    var captchaQ = document.querySelector('.oso-director-captcha-question');
    var captchaKey = document.getElementById('oso-director-captcha-key');
    var captchaA = document.getElementById('oso-director-captcha-answer');
    var messages = document.querySelector('.oso-director-messages');
    var submitBtn = document.querySelector('.oso-director-submit');

    function refreshCaptcha() {
        var body = new URLSearchParams();
        body.append('action', 'oso_director_captcha');
        body.append('nonce', (window.oso_director && window.oso_director.nonce) || '');

        fetch((window.oso_director && window.oso_director.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response && response.success && response.data) {
                captchaQ.textContent = response.data.question;
                captchaKey.value = response.data.key;
                captchaA.value = '';
            }
        })
        .catch(function() {});
    }

    function showMessages(html, kind) {
        messages.innerHTML = '<div class="oso-director-' + kind + '">' + html + '</div>';
    }

    document.querySelector('.oso-director-captcha-refresh').addEventListener('click', function(e) {
        e.preventDefault();
        refreshCaptcha();
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var s = (window.oso_director && window.oso_director.strings) || {};
        var errors = [];

        var messageEl = document.getElementById('oso-director-message');
        var emailEl = document.getElementById('oso-director-email');

        if (!messageEl.value.trim()) {
            errors.push(s.required || 'Это поле обязательно');
        }

        var email = emailEl.value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push(s.email || 'Введите корректный email');
        }

        if (errors.length) {
            showMessages(errors.join('<br>'), 'error');
            return;
        }

        var btn = submitBtn;
        var origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Отправка...';

        var formData = new FormData(form);
        formData.append('action', 'oso_director_submit');
        formData.append('nonce', (window.oso_director && window.oso_director.nonce) || '');

        fetch((window.oso_director && window.oso_director.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            btn.disabled = false;
            btn.textContent = origText;
            if (response && response.success) {
                showMessages(response.data.message || (s.success || 'Сообщение отправлено!'), 'success');
                form.reset();
                refreshCaptcha();
            } else {
                var err = (response && response.data && response.data.errors) ? Object.values(response.data.errors).join('<br>') : (s.error || 'Ошибка отправки.');
                showMessages(err, 'error');
                refreshCaptcha();
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = origText;
            showMessages(s.error || 'Ошибка отправки. Попробуйте позже.', 'error');
            refreshCaptcha();
        });
    });

    refreshCaptcha();
})();
