(function() {
    'use strict';

    function init() {
        var products = document.querySelector('ul.products');
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
                imgHtml = '<a href="' + href + '"><img src="' + src + '" alt="' + alt + '" class="otv-table-img" loading="lazy"></a>';
            }

            var titleHtml = '';
            if (title) {
                var titleLink = title.querySelector('a') || (link ? link : null);
                var titleHref = titleLink ? titleLink.getAttribute('href') : '#';
                var titleText = title.textContent.trim();
                titleHtml = '<a href="' + titleHref + '" class="otv-table-link">' + titleText + '</a>';
            }

            var priceHtml = price ? price.innerHTML : '';
            var cartHtml = cart ? cart.outerHTML : '';

            var row = document.createElement('tr');
            row.innerHTML =
                '<td class="otv-td-img">' + imgHtml + '</td>' +
                '<td class="otv-td-name">' + titleHtml + '</td>' +
                '<td class="otv-td-price">' + priceHtml + '</td>' +
                '<td class="otv-td-cart">' + cartHtml + '</td>';

            table.appendChild(row);
        });

        table.innerHTML += '</tbody>';

        products.parentNode.insertBefore(table, products);
        products.style.display = 'none';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
