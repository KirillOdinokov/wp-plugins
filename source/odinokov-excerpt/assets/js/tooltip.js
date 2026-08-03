(function() {
    'use strict';

    if (typeof ODEX === 'undefined') return;

    var tooltipEl = null;
    var currentTarget = null;
    var hideTimer = null;

    function createTooltip() {
        if (tooltipEl) return;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'odex-floating-tooltip';

        var arrow = document.createElement('div');
        arrow.className = 'odex-tooltip-arrow';
        tooltipEl.appendChild(arrow);

        var text = document.createElement('p');
        text.className = 'odex-tooltip-text';
        tooltipEl.appendChild(text);

        var btn = document.createElement('a');
        btn.className = 'odex-tooltip-btn';
        btn.setAttribute('rel', 'noopener');
        tooltipEl.appendChild(btn);

        document.body.appendChild(tooltipEl);

        tooltipEl.addEventListener('mouseenter', function() {
            if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        });
        tooltipEl.addEventListener('mouseleave', function() {
            hideTooltip();
        });
    }

    function showTooltip(el) {
        var dataEl = el.querySelector('.odex-tooltip-data') || el;
        if (!dataEl || !dataEl.dataset.odexExcerpt) return;

        currentTarget = el;
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }

        createTooltip();

        tooltipEl.querySelector('.odex-tooltip-text').textContent = dataEl.dataset.odexExcerpt;

        var btn = tooltipEl.querySelector('.odex-tooltip-btn');
        btn.href = dataEl.dataset.odexUrl || '#';
        btn.textContent = dataEl.dataset.odexType === 'product'
            ? ODEX.i18n_goto_prod
            : ODEX.i18n_goto_cat;

        positionTooltip(el);

        tooltipEl.classList.add('odex-visible');
    }

    function positionTooltip(el) {
        var rect = el.getBoundingClientRect();
        var scrollX = window.pageXOffset || document.documentElement.scrollLeft || 0;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;

        var top = rect.bottom + scrollY + 8;
        var left = rect.left + scrollX;

        var tooltipWidth = tooltipEl.offsetWidth || 300;
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;

        if (left + tooltipWidth > viewportWidth - 10) {
            left = viewportWidth - tooltipWidth - 10;
        }
        if (left < 10) {
            left = 10;
        }

        tooltipEl.style.top = top + 'px';
        tooltipEl.style.left = left + 'px';
    }

    function hideTooltip() {
        if (hideTimer) return;
        hideTimer = setTimeout(function() {
            if (tooltipEl) {
                tooltipEl.classList.remove('odex-visible');
            }
            currentTarget = null;
            hideTimer = null;
        }, 150);
    }

    function init() {
        var items = document.querySelectorAll('.product, .product-category');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (!item.querySelector('.odex-tooltip-data')) continue;

            item.addEventListener('mouseenter', function(e) {
                showTooltip(this);
            });
            item.addEventListener('mouseleave', function() {
                hideTooltip();
            });
            item.addEventListener('focusin', function() {
                showTooltip(this);
            });
            item.addEventListener('focusout', function() {
                hideTooltip();
            });
        }

        window.addEventListener('scroll', function() {
            if (tooltipEl && tooltipEl.classList.contains('odex-visible') && currentTarget) {
                positionTooltip(currentTarget);
            }
        }, { passive: true });

        window.addEventListener('resize', function() {
            if (tooltipEl && tooltipEl.classList.contains('odex-visible') && currentTarget) {
                positionTooltip(currentTarget);
            }
        }, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
