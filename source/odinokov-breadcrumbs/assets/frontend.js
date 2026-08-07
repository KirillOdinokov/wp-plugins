(function() {
    'use strict';

    var HIDE_DELAY = 300;
    var currentRequest = null;
    var hideTimer = null;
    var menuEl = null;
    var activeLi = null;

    function init() {
        var breadcrumb = document.querySelector('.breadcrumb');
        if (!breadcrumb) {
            return;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'obm-breadcrumb-wrapper';
        breadcrumb.parentNode.insertBefore(wrapper, breadcrumb);
        wrapper.appendChild(breadcrumb);

        menuEl = document.createElement('div');
        menuEl.className = 'obm-mega-menu';
        menuEl.setAttribute('role', 'menu');
        menuEl.setAttribute('aria-hidden', 'true');
        wrapper.appendChild(menuEl);

        menuEl.addEventListener('mouseenter', function() {
            clearTimeout(hideTimer);
        });

        menuEl.addEventListener('mouseleave', function() {
            scheduleHide();
        });

        var items = breadcrumb.querySelectorAll('li');
        items.forEach(function(li) {
            var arrow = document.createElement('span');
            arrow.className = 'obm-arrow';
            arrow.setAttribute('aria-hidden', 'true');
            li.appendChild(arrow);

            li.addEventListener('mouseenter', function(e) {
                handleItemHover(li);
            });
            li.addEventListener('mouseleave', function() {
                scheduleHide();
            });
        });

        wrapper.addEventListener('mouseleave', function() {
            scheduleHide();
        });
    }

    function handleItemHover(li) {
        clearTimeout(hideTimer);

        if (activeLi === li && menuEl.classList.contains('is-visible')) {
            return;
        }

        var link = li.querySelector('a');
        var url = null;
        var termId = null;

        if (link) {
            url = link.getAttribute('href');
        } else {
            termId = getCurrentTermId();
            if (!termId) {
                url = window.location.href;
            }
        }

        if (!url && !termId) {
            hideMenu();
            return;
        }

        if (currentRequest) {
            currentRequest.abort();
        }

        showLoading();

        var formData = new FormData();
        formData.append('action', 'obm_load_menu');
        formData.append('nonce', OBM.nonce);
        if (termId) {
            formData.append('term_id', termId);
        } else {
            formData.append('url', url);
        }

        currentRequest = new XMLHttpRequest();
        currentRequest.open('POST', OBM.ajaxUrl, true);

        currentRequest.onload = function() {
            currentRequest = null;
            if (this.status >= 200 && this.status < 400) {
                try {
                    var response = JSON.parse(this.responseText);
                    if (response.success) {
                        var hasItems = renderMenu(response.data);
                        if (hasItems) {
                            positionMenu(li);
                            menuEl.classList.add('is-visible');
                            menuEl.setAttribute('aria-hidden', 'false');
                            setActive(li);
                            li.classList.remove('obm-no-children');
                        } else {
                            li.classList.add('obm-no-children');
                            hideMenu();
                        }
                    } else {
                        li.classList.add('obm-no-children');
                        hideMenu();
                    }
                } catch (e) {
                    hideMenu();
                }
            } else {
                hideMenu();
            }
        };

        currentRequest.onerror = function() {
            currentRequest = null;
            hideMenu();
        };

        currentRequest.send(formData);
    }

    function getCurrentTermId() {
        var bodyClasses = document.body.className.split(/\s+/);
        for (var i = 0; i < bodyClasses.length; i++) {
            var match = bodyClasses[i].match(/^term-(\d+)$/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }
        return null;
    }

    function getColumns(count) {
        if (count <= 20) return 2;
        if (count <= 30) return 3;
        if (count <= 40) return 4;
        return 5;
    }

    function showLoading() {
        menuEl.innerHTML = '<div class="obm-mega-menu__loading">' + OBM.i18n.loading + '</div>';
        menuEl.classList.add('is-visible');
        menuEl.setAttribute('aria-hidden', 'false');
    }

    function renderMenu(data) {
        var items = data.items;
        var hasMore = data.has_more;
        var termUrl = data.term_url;

        if (!items || items.length === 0) {
            menuEl.innerHTML = '';
            menuEl.classList.remove('is-visible');
            menuEl.setAttribute('aria-hidden', 'true');
            return false;
        }

        var cols = getColumns(items.length);
        var perCol = Math.ceil(items.length / cols);

        var html = '<div class="obm-mega-menu__grid" style="grid-template-columns:repeat(' + cols + ', 1fr);">';

        for (var col = 0; col < cols; col++) {
            html += '<div class="obm-mega-menu__column">';
            var start = col * perCol;
            var end = Math.min(start + perCol, items.length);
            for (var i = start; i < end; i++) {
                var item = items[i];
                html += '<a href="' + escapeAttr(item.url) + '" class="obm-mega-menu__item';
                if (item.price !== undefined) {
                    html += ' obm-mega-menu__item--product';
                }
                html += '">' + escapeHtml(item.name);
                if (item.price !== undefined && item.price) {
                    html += '<span class="obm-mega-menu__price">' + item.price + '</span>';
                }
                html += '</a>';
            }
            html += '</div>';
        }

        html += '</div>';

        if (hasMore && termUrl) {
            html += '<div class="obm-mega-menu__footer">';
            html += '<a href="' + escapeAttr(termUrl) + '" class="obm-mega-menu__footer-btn">' + OBM.i18n.goToCategory + '</a>';
            html += '</div>';
        }

        menuEl.innerHTML = html;
        return true;
    }

    function positionMenu(li) {
        menuEl.style.left = '0';
        menuEl.style.right = '0';
        menuEl.style.width = '100%';
    }

    function setActive(li) {
        if (activeLi) {
            activeLi.classList.remove('obm-active');
        }
        activeLi = li;
        li.classList.add('obm-active');
    }

    function scheduleHide() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hideMenu, HIDE_DELAY);
    }

    function hideMenu() {
        clearTimeout(hideTimer);
        if (currentRequest) {
            currentRequest.abort();
            currentRequest = null;
        }
        menuEl.classList.remove('is-visible');
        menuEl.setAttribute('aria-hidden', 'true');
        menuEl.innerHTML = '';
        if (activeLi) {
            activeLi.classList.remove('obm-active');
            activeLi = null;
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
