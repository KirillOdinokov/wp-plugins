(function() {
    'use strict';

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function hideShopLoopBefore() {
        if (!window.otvData || !otvData.isTable) return;
        var els = document.querySelectorAll('.shop-loop-before');
        for (var i = 0; i < els.length; i++) {
            els[i].style.setProperty('display', 'none', 'important');
        }
    }

    function injectShopLoopBeforeStyle() {
        if (!window.otvData || !otvData.isTable) return;
        var id = 'otv-hide-slb';
        if (document.getElementById(id)) return;
        var s = document.createElement('style');
        s.id = id;
        s.textContent = '.shop-loop-before,.shop-loop-after{display:none!important}';
        document.head.appendChild(s);
    }

    function watchShopLoopBefore() {
        if (!window.otvData || !otvData.isTable) return;
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    if (node.classList && node.classList.contains('shop-loop-before')) {
                        node.style.setProperty('display', 'none', 'important');
                    }
                    if (node.querySelectorAll) {
                        var slbs = node.querySelectorAll('.shop-loop-before');
                        for (var i = 0; i < slbs.length; i++) {
                            slbs[i].style.setProperty('display', 'none', 'important');
                        }
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function injectSubcatHoverDescs() {
        if (!window.otvData) return;
        var subcatDescs = otvData.subcatDescs;
        if (!subcatDescs || Object.keys(subcatDescs).length === 0) return;

        var wrapper = document.querySelector('.otv-subcategories-wrapper');
        if (!wrapper) return;

        var catItems = wrapper.querySelectorAll('.product-category');
        catItems.forEach(function(item) {
            if (item.querySelector('.otv-hover-desc')) return;
            var link = item.querySelector('a[href]');
            if (!link) return;
            var href = link.getAttribute('href');
            var parts = href.replace(/\/+$/, '').split('/');
            var slug = parts[parts.length - 1];
            if (!slug || !subcatDescs[slug]) return;

            var descDiv = document.createElement('div');
            descDiv.className = 'otv-hover-desc';
            descDiv.textContent = subcatDescs[slug];
            item.appendChild(descDiv);
        });
    }

    function buildTable() {
        if (!window.otvData || !otvData.isTable) return;
        if (!document.body.classList.contains('otv-table-view')) return;

        var allProducts = document.querySelectorAll('ul.products');
        var products = null;
        for (var i = 0; i < allProducts.length; i++) {
            if (!allProducts[i].closest('.otv-subcategories-wrapper')) {
                products = allProducts[i];
                break;
            }
        }
        if (!products) return;

        var items = products.querySelectorAll('li.product');
        if (items.length === 0) return;

        var table = document.createElement('table');
        table.className = 'otv-products-table';

        var thead = '<thead><tr>' +
            '<th class="otv-th-img">Фото</th>' +
            '<th class="otv-th-name">Наименование</th>' +
            '<th class="otv-th-price">Цена</th>' +
            '<th class="otv-th-cart">В корзину</th>' +
            '</tr></thead><tbody>';

        table.innerHTML = thead;

        items.forEach(function(item) {
            var img = item.querySelector('img');
            var title = item.querySelector('.woocommerce-loop-product__title, h3, .product-loop-title, .product_title');
            var price = item.querySelector('.price');
            var cart = item.querySelector('.add_to_cart_button, .ajax_add_to_cart, .button.product_type_simple');
            var link = item.querySelector('a[href]');

            var imgHtml = '';
            if (img) {
                var src = img.getAttribute('src') || img.getAttribute('data-src') || '';
                var alt = img.getAttribute('alt') || '';
                var href = link ? link.getAttribute('href') : '#';
                imgHtml = '<a href="' + escapeHtml(href) + '"><img src="' + escapeHtml(src) + '" alt="' + escapeHtml(alt) + '" class="otv-table-img" loading="lazy"></a>';
            }

            var titleHtml = '';
            if (title) {
                var titleLink = title.querySelector('a') || (link ? link : null);
                var titleHref = titleLink ? titleLink.getAttribute('href') : '#';
                var titleText = title.textContent.trim();
                titleHtml = '<a href="' + escapeHtml(titleHref) + '" class="otv-table-link">' + escapeHtml(titleText) + '</a>';
            }

            var cartHtml = cart ? cart.outerHTML : '';

            var orderWrap = null;
            var orderBtn = item.querySelector('.oso-order-btn');
            if (orderBtn) {
                orderWrap = orderBtn.closest('.oso-order-btn-wrap');
                if (orderWrap) {
                    orderWrap.remove();
                }
            }

            var priceHtml = price ? price.innerHTML : '';

            var row = document.createElement('tr');
            row.innerHTML =
                '<td class="otv-td-img" data-title="Фото">' + imgHtml + '</td>' +
                '<td class="otv-td-name" data-title="Наименование">' + titleHtml + '</td>' +
                '<td class="otv-td-price" data-title="Цена">' + priceHtml + '</td>' +
                '<td class="otv-td-cart" data-title="В корзину">' + cartHtml + '</td>';

            if (orderWrap) {
                var cartTd = row.querySelector('.otv-td-cart');
                cartTd.appendChild(orderWrap);
            }

            table.appendChild(row);
        });

        table.innerHTML += '</tbody>';

        products.parentNode.insertBefore(table, products);
        products.style.display = 'none';
    }

    function init() {
        injectShopLoopBeforeStyle();
        hideShopLoopBefore();
        watchShopLoopBefore();
        injectSubcatHoverDescs();
        buildTable();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    if (window.jQuery) {
        jQuery(document.body).on('porto_refresh_vc_content', function() {
            setTimeout(hideShopLoopBefore, 50);
        });
    }
})();
