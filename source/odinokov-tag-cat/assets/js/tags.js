(function() {
    'use strict';

    function moveTagsUnderDescription() {
        var wrap = document.querySelector('.otc-tags-wrap');
        if (!wrap) return;

        var termDesc = document.querySelector('.term-description');
        if (!termDesc) return;

        // Собираем блок меток + кнопку «Показать полностью» (если есть)
        var container = document.createElement('div');
        container.className = 'otc-tags-container';
        container.appendChild(wrap);

        var btn = wrap.nextElementSibling;
        if (btn && btn.classList && btn.classList.contains('otc-toggle-btn')) {
            container.appendChild(btn);
        }

        termDesc.parentNode.insertBefore(container, termDesc.nextSibling);
    }

    function init() {
        moveTagsUnderDescription();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('otc-toggle-btn')) return;
        e.preventDefault();
        var btn = e.target;
        var wrap = btn.previousElementSibling;
        if (!wrap || !wrap.classList.contains('otc-tags-wrap')) return;
        var isCollapsed = wrap.classList.contains('otc-tags-collapsed');
        if (isCollapsed) {
            wrap.classList.remove('otc-tags-collapsed');
            wrap.classList.add('otc-tags-expanded');
            btn.textContent = btn.getAttribute('data-otc-hide');
        } else {
            wrap.classList.add('otc-tags-collapsed');
            wrap.classList.remove('otc-tags-expanded');
            btn.textContent = btn.getAttribute('data-otc-show');
        }
    });
})();
