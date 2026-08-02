(function() {
    'use strict';

    var currentPage = 1;
    var hasMore = false;
    var selectedCategory = '';
    var loadTimer = null;

    var el = {
        category:   document.getElementById('oso-sert-category'),
        step2:      document.querySelector('.oso-sert-step-2'),
        step3:      document.querySelector('.oso-sert-step-3'),
        step4:      document.querySelector('.oso-sert-step-4'),
        product:    document.getElementById('oso-sert-product'),
        search:     document.getElementById('oso-sert-product-search'),
        count:      document.querySelector('.oso-sert-product-count'),
        loadMore:   document.querySelector('.oso-sert-load-more'),
        otherWrap:  document.querySelector('.oso-sert-other-wrap'),
        otherText:  document.getElementById('oso-sert-other-text'),
        captchaQ:   document.querySelector('.oso-sert-captcha-question'),
        captchaKey: document.getElementById('oso-sert-captcha-key'),
        captchaA:   document.getElementById('oso-sert-captcha-answer'),
        messages:   document.querySelector('.oso-sert-messages'),
        submitBtn:  document.querySelector('.oso-sert-submit'),
    };

    if (!el.category) return;

    el.category.addEventListener('change', function() {
        selectedCategory = this.value;
        currentPage = 1;
        if (!selectedCategory) {
            el.step2.style.display = 'none';
            el.step3.style.display = 'none';
            el.step4.style.display = 'none';
            return;
        }
        el.step2.style.display = '';
        el.step3.style.display = 'none';
        el.step4.style.display = 'none';
        el.search.value = '';
        loadProducts();
    });

    el.search.addEventListener('input', function() {
        clearTimeout(loadTimer);
        currentPage = 1;
        loadTimer = setTimeout(loadProducts, 300);
    });

    el.loadMore.addEventListener('click', function() {
        currentPage++;
        loadProducts(true);
    });

    el.product.addEventListener('change', function() {
        if (this.value) {
            el.step3.style.display = '';
            el.step4.style.display = 'none';
            el.otherWrap.style.display = 'none';
            el.otherText.value = '';
            uncheckAllDocs();
        } else {
            el.step3.style.display = 'none';
            el.step4.style.display = 'none';
        }
    });

    var docCheckboxes = document.querySelectorAll('.oso-sert-check input[type="checkbox"]');
    for (var i = 0; i < docCheckboxes.length; i++) {
        docCheckboxes[i].addEventListener('change', function() {
            if (this.value === 'other') {
                el.otherWrap.style.display = this.checked ? '' : 'none';
                if (!this.checked) el.otherText.value = '';
            }
            toggleStep4();
        });
    }

    function uncheckAllDocs() {
        for (var i = 0; i < docCheckboxes.length; i++) {
            docCheckboxes[i].checked = false;
        }
    }

    function toggleStep4() {
        var anyChecked = false;
        for (var i = 0; i < docCheckboxes.length; i++) {
            if (docCheckboxes[i].checked) { anyChecked = true; break; }
        }
        if (anyChecked) {
            el.step4.style.display = '';
            refreshCaptcha();
        } else {
            el.step4.style.display = 'none';
        }
    }

    function loadProducts(append) {
        if (!selectedCategory) return;

        el.product.disabled = true;
        if (!append) {
            el.product.innerHTML = '<option value="">' + (window.oso_sert && window.oso_sert.strings && window.oso_sert.strings.loading || 'Загрузка...') + '</option>';
        }

        var body = new URLSearchParams();
        body.append('action', 'oso_sert_products');
        body.append('nonce', (window.oso_sert && window.oso_sert.nonce) || '');
        body.append('category', selectedCategory);
        body.append('search', el.search.value);
        body.append('page', currentPage);

        fetch((window.oso_sert && window.oso_sert.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (!response || !response.success) return;

            var data = response.data;
            hasMore = data.has_more;

            if (!append) {
                el.product.innerHTML = '<option value="">' + (window.oso_sert && window.oso_sert.strings && window.oso_sert.strings.select_product || '— Выберите товар —') + '</option>';
            }

            for (var i = 0; i < data.products.length; i++) {
                var opt = document.createElement('option');
                opt.value = data.products[i].id;
                opt.textContent = data.products[i].name;
                el.product.appendChild(opt);
            }

            el.count.textContent = 'Показано: ' + el.product.options.length + ' из ' + data.total;
            el.loadMore.style.display = hasMore ? '' : 'none';
            el.product.disabled = false;
        })
        .catch(function() {
            el.product.disabled = false;
        });
    }

    function refreshCaptcha() {
        var body = new URLSearchParams();
        body.append('action', 'oso_sert_captcha');
        body.append('nonce', (window.oso_sert && window.oso_sert.nonce) || '');

        fetch((window.oso_sert && window.oso_sert.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response && response.success && response.data) {
                el.captchaQ.textContent = response.data.question;
                el.captchaKey.value = response.data.key;
                el.captchaA.value = '';
            }
        })
        .catch(function() {});
    }

    document.querySelector('.oso-sert-captcha-refresh').addEventListener('click', function(e) {
        e.preventDefault();
        refreshCaptcha();
    });

    el.submitBtn.addEventListener('click', function() {
        var errors = [];
        var s = (window.oso_sert && window.oso_sert.strings) || {};

        var nameEl  = document.getElementById('oso-sert-name');
        var innEl   = document.getElementById('oso-sert-inn');
        var emailEl = document.getElementById('oso-sert-email');

        if (!nameEl.value.trim()) {
            errors.push(s.required || 'Это поле обязательно');
        }
        if (!innEl.value.trim()) {
            errors.push(s.required || 'Это поле обязательно');
        }
        var email = emailEl.value.trim();
        if (!email) {
            errors.push(s.required || 'Это поле обязательно');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push(s.email || 'Введите корректный email');
        }

        var checkedDocs = [];
        for (var i = 0; i < docCheckboxes.length; i++) {
            if (docCheckboxes[i].checked) checkedDocs.push(docCheckboxes[i].value);
        }
        if (!checkedDocs.length) {
            errors.push(s.select_docs || 'Выберите хотя бы один тип документации');
        }

        if (errors.length) {
            showMessages(errors.join('<br>'), 'error');
            return;
        }

        var btn = el.submitBtn;
        var origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Отправка...';

        var formData = new FormData();
        formData.append('action', 'oso_sert_submit');
        formData.append('nonce', (window.oso_sert && window.oso_sert.nonce) || '');
        formData.append('category', selectedCategory);
        formData.append('product_id', el.product.value);
        formData.append('name', nameEl.value.trim());
        formData.append('inn', innEl.value.trim());
        formData.append('email', email);
        formData.append('phone', document.getElementById('oso-sert-phone').value.trim());
        formData.append('other_text', el.otherText.value.trim());
        formData.append('captcha_answer', el.captchaA.value);
        formData.append('captcha_key', el.captchaKey.value);
        for (var j = 0; j < checkedDocs.length; j++) {
            formData.append('doc_types[]', checkedDocs[j]);
        }

        fetch((window.oso_sert && window.oso_sert.ajax_url) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            btn.disabled = false;
            btn.textContent = origText;
            if (response && response.success) {
                showMessages(response.data.message || (s.success || 'Заявка отправлена!'), 'success');
                resetForm();
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

    function showMessages(html, kind) {
        el.messages.innerHTML = '<div class="oso-sert-' + kind + '">' + html + '</div>';
    }

    function resetForm() {
        el.category.value = '';
        el.step2.style.display = 'none';
        el.step3.style.display = 'none';
        el.step4.style.display = 'none';
        el.search.value = '';
        el.product.innerHTML = '<option value="">— Выберите товар —</option>';
        uncheckAllDocs();
        el.otherWrap.style.display = 'none';
        el.otherText.value = '';
        document.getElementById('oso-sert-name').value = '';
        document.getElementById('oso-sert-inn').value = '';
        document.getElementById('oso-sert-email').value = '';
        document.getElementById('oso-sert-phone').value = '';
        el.captchaA.value = '';
        el.captchaQ.textContent = '';
    }
})();
