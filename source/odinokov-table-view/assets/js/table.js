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

        var catItems = document.querySelectorAll('.product-category');
        catItems.forEach(function(item) {
            injectDescIntoItem(item, subcatDescs);
        });
    }

    function injectDescIntoItem(item, subcatDescs) {
        if (item.querySelector('.otv-hover-desc')) return;
        var link = item.querySelector('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        var parts = href.replace(/\/+$/, '').split('/');
        var slug = parts[parts.length - 1];
        if (!slug || !subcatDescs[slug]) return;

        var descDiv = document.createElement('div');
        descDiv.className = 'otv-hover-desc';
        descDiv.innerHTML = escapeHtml(subcatDescs[slug]) + ' <a href="' + escapeHtml(href) + '" class="otv-read-more">читать далее &gt;</a>';
        item.appendChild(descDiv);
    }

    function injectProductHoverDescs() {
        if (!window.otvData) return;
        var productDescs = otvData.productDescs;
        if (!productDescs || Object.keys(productDescs).length === 0) return;

        var items = document.querySelectorAll('.product, .porto-tb-item.product');
        items.forEach(function(item) {
            injectProductDescIntoItem(item, productDescs);
        });
    }

    function injectProductDescIntoItem(item, productDescs) {
        if (item.querySelector('.otv-hover-desc')) return;
        var link = item.querySelector('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        var m = href.match(/\/product\/([^\/]+)/);
        if (!m) return;

        // Ищем id товара по классу post-XXX
        var cls = item.className || '';
        var idMatch = cls.match(/post-(\d+)/);
        if (!idMatch) return;
        var pid = idMatch[1];
        if (!productDescs[pid]) return;

        var descDiv = document.createElement('div');
        descDiv.className = 'otv-hover-desc';
        descDiv.innerHTML = escapeHtml(productDescs[pid]) + ' <a href="' + escapeHtml(href) + '" class="otv-read-more">читать далее &gt;</a>';
        item.appendChild(descDiv);
    }

    function watchSubcatHoverDescs() {
        if (!window.otvData) return;
        var subcatDescs = otvData.subcatDescs;
        var productDescs = otvData.productDescs;

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    if (node.classList && node.classList.contains('product-category')) {
                        injectDescIntoItem(node, subcatDescs);
                    }
                    if (node.classList && (node.classList.contains('product') || node.classList.contains('porto-tb-item'))) {
                        injectProductDescIntoItem(node, productDescs);
                    }
                    if (node.querySelectorAll) {
                        var cats = node.querySelectorAll('.product-category');
                        for (var i = 0; i < cats.length; i++) {
                            injectDescIntoItem(cats[i], subcatDescs);
                        }
                        var prods = node.querySelectorAll('.product, .porto-tb-item.product');
                        for (var j = 0; j < prods.length; j++) {
                            injectProductDescIntoItem(prods[j], productDescs);
                        }
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function isZeroPrice(priceEl) {
        if (!priceEl) return true;
        var text = priceEl.textContent || '';
        text = text.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
        if (text === '') return true;
        var digits = text.replace(/[^0-9.,]/g, '');
        if (digits === '') return true;
        var num = parseFloat(digits.replace(',', '.'));
        return (!isNaN(num) && num === 0);
    }

    function buildTable() {
        if (!window.otvData || !otvData.isTable) return;
        if (!document.body.classList.contains('otv-table-view')) return;

        var products = null;
        var items = null;

        // Вариант 1: стандартный WooCommerce ul.products li.product
        var allProducts = document.querySelectorAll('ul.products');
        for (var i = 0; i < allProducts.length; i++) {
            if (!allProducts[i].closest('.otv-subcategories-wrapper')) {
                products = allProducts[i];
                break;
            }
        }
        if (products) {
            items = products.querySelectorAll('li.product');
        }

        // Вариант 2: Porto block (porto-posts-grid) — .porto-tb-item.product
        if (!items || items.length === 0) {
            var grid = document.querySelector('.porto-posts-grid, .archive-products');
            if (grid) {
                items = grid.querySelectorAll('.porto-tb-item.product, .product.product-col');
                products = grid;
            }
        }

        if (!items || items.length === 0) return;

        // Удаляем старую таблицу, если она есть
        var oldTable = document.querySelector('.otv-products-table');
        if (oldTable) {
            oldTable.remove();
        }

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
            var title = item.querySelector('.woocommerce-loop-product__title, h3, .product-loop-title, .product_title, .porto-heading, .post-title');
            var price = item.querySelector('.price');
            var cart = item.querySelector('.add_to_cart_button, .ajax_add_to_cart, .button.product_type_simple, .porto-tb-addcart');
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
                var wrap = orderBtn.closest('.oso-order-btn-wrap');
                if (wrap) {
                    orderWrap = wrap.cloneNode(true);
                }
            }

            var priceHtml = '';
            if (price && !isZeroPrice(price)) {
                priceHtml = price.innerHTML;
                // Убираем кнопку order-share из цены — она переносится в ячейку корзины
                var tmp = document.createElement('div');
                tmp.innerHTML = priceHtml;
                var btnInPrice = tmp.querySelector('.oso-order-btn-wrap');
                if (btnInPrice) {
                    btnInPrice.remove();
                }
                priceHtml = tmp.innerHTML;
            } else {
                priceHtml = '<span class="otv-price-on-request">Цена по запросу</span>';
            }

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

    function watchProductsForRebuild() {
        if (!window.otvData || !otvData.isTable) return;

        var observer = new MutationObserver(function(mutations) {
            var shouldRebuild = false;
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    if (node.classList && (node.classList.contains('product') || node.classList.contains('porto-tb-item'))) {
                        shouldRebuild = true;
                    }
                    if (node.querySelectorAll) {
                        if (node.querySelectorAll('.product, .porto-tb-item').length > 0) {
                            shouldRebuild = true;
                        }
                    }
                });
            });
            if (shouldRebuild) {
                buildTable();
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function moveSubcategoriesToTop() {
        var wrapper = document.querySelector('.otv-subcategories-wrapper');
        if (!wrapper) return;

        // Ищем контейнер товаров (Porto block или стандартный)
        var target = document.querySelector('.porto-posts-grid, .archive-products, ul.products');
        if (!target) return;

        // Если wrapper уже перед target — ничего не делаем
        if (target.previousElementSibling === wrapper) return;

        target.parentNode.insertBefore(wrapper, target);
    }

    function init() {
        injectShopLoopBeforeStyle();
        hideShopLoopBefore();
        watchShopLoopBefore();
        injectSubcatHoverDescs();
        injectProductHoverDescs();
        watchSubcatHoverDescs();
        moveSubcategoriesToTop();
        buildTable();
        watchProductsForRebuild();
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
