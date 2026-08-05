(function() {
    'use strict';
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
