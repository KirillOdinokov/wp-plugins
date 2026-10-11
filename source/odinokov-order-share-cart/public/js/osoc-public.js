(function () {
	'use strict';

	if (!window.osoc) {
		return;
	}

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function escapeHtml(text) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(text == null ? '' : String(text)));
		return d.innerHTML;
	}

	function post(action, params, extra) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', osoc.nonce);
		if (params) {
			Object.keys(params).forEach(function (k) {
				body.append(k, params[k]);
			});
		}
		var opts = {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
			credentials: 'same-origin'
		};
		if (extra) {
			for (var key in extra) {
				opts[key] = extra[key];
			}
		}
		return fetch(osoc.ajax_url, opts).then(function (r) { return r.json(); });
	}

	/* =====================================================================
	 * Перехват кнопки «Оставить заявку» -> корзина + toast
	 * ================================================================== */

	function showToast(data, x, y) {
		var t = document.getElementById('osoc-toast');
		if (!t) {
			t = document.createElement('div');
			t.id = 'osoc-toast';
			t.className = 'osoc-toast';
			document.body.appendChild(t);
		}
		var toast = osoc.toast || {};
		var html = '<div class="osoc-toast-title">' + escapeHtml(toast.title || '') + '</div>';
		if (data && data.product_name) {
			html += '<div style="font-size:12px;color:#888;margin-bottom:6px;">' + escapeHtml(data.product_name) + '</div>';
		}
		html += '<p class="osoc-toast-question">' + escapeHtml(toast.question || '') + '</p>';
		html += '<div class="osoc-toast-actions">';
		html += '<button type="button" class="osoc-btn osoc-btn-primary" data-role="checkout">' + escapeHtml(toast.checkout_label || '') + '</button>';
		html += '<button type="button" class="osoc-btn" data-role="continue">' + escapeHtml(toast.continue_label || '') + '</button>';
		html += '</div>';
		t.innerHTML = html;
		positionToast(t, x, y);
		t.style.display = 'block';

		var ck = t.querySelector('[data-role="checkout"]');
		var cn = t.querySelector('[data-role="continue"]');
		if (ck) ck.addEventListener('click', function () {
			window.location.href = (data && data.checkout_url) || osoc.checkout_url;
		});
		if (cn) cn.addEventListener('click', function () { hideToast(); });

		if (t.__timer) clearTimeout(t.__timer);
		t.__timer = setTimeout(hideToast, 8000);
	}

	function positionToast(t, x, y) {
		var vw = window.innerWidth || document.documentElement.clientWidth;
		var vh = window.innerHeight || document.documentElement.clientHeight;
		var w = 300;
		var left = (x == null) ? (vw - w - 16) : (x + 14);
		var top = (y == null) ? (vh - 120) : (y + 14);
		if (left + w > vw - 8) left = vw - w - 8;
		if (left < 8) left = 8;
		if (top < 8) top = 8;
		if (top > vh - 8) top = vh - 8;
		t.style.left = left + 'px';
		t.style.top = top + 'px';
	}

	function hideToast() {
		var t = document.getElementById('osoc-toast');
		if (t) t.style.display = 'none';
	}

	function addToCart(btn, e) {
		var productId = btn.getAttribute('data-product-id') || '';
		var productName = btn.getAttribute('data-product-name') || '';
		var x = (e && typeof e.clientX === 'number') ? e.clientX : null;
		var y = (e && typeof e.clientY === 'number') ? e.clientY : null;
		if (x == null) {
			var r = btn.getBoundingClientRect();
			x = r.left + r.width / 2;
			y = r.top + r.height / 2;
		}
		post('osoc_add_to_cart', { product_id: productId, product_name: productName, quantity: '1' })
			.then(function (res) {
				if (res && res.success) {
					showToast(res.data, x, y);
				}
			})
			.catch(function () {});
	}

	function bindIntercept() {
		var lastAdd = 0;
		function onIntercept(e) {
			var btn = (e.target && e.target.closest) ? e.target.closest('.oso-order-btn') : null;
			if (!btn) return;
			e.stopPropagation();
			if (e.stopImmediatePropagation) e.stopImmediatePropagation();
			if (e.type === 'click' && e.cancelable) e.preventDefault();
			var now = Date.now();
			if (now - lastAdd < 400) return;
			lastAdd = now;
			addToCart(btn, e);
		}
		document.addEventListener('click', onIntercept, true);
		document.addEventListener('pointerup', onIntercept, true);
		document.addEventListener('touchend', onIntercept, true);
	}

	/* =====================================================================
	 * Капча
	 * ================================================================== */

	function refreshCaptcha() {
		var q = document.querySelector('.osoc-captcha-question');
		var k = document.getElementById('osoc-captcha-key');
		var a = document.getElementById('osoc-captcha');
		if (!q || !k) return;
		post('osoc_refresh_captcha')
			.then(function (res) {
				if (res && res.success && res.data) {
					q.textContent = res.data.question;
					k.value = res.data.key;
					if (a) a.value = '';
				}
			})
			.catch(function () {});
	}

	/* =====================================================================
	 * Страница оформления
	 * ================================================================== */

	function bindCheckout() {
		refreshCaptcha();

		var form = document.getElementById('osoc-order-form');
		if (!form) return;

		// Доставка.
		form.addEventListener('change', function (e) {
			var t = e.target;
			if (t && t.name === 'delivery') {
				var addr = form.querySelector('.osoc-delivery-address');
				if (addr) addr.style.display = (t.value === 'yes') ? '' : 'none';
			}
		});

		// Обновить капчу.
		form.addEventListener('click', function (e) {
			var t = e.target;
			if (t && t.classList && t.classList.contains('osoc-captcha-refresh')) {
				e.preventDefault();
				refreshCaptcha();
			}
		});

		// Отправка.
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			submitOrder();
		});
	}

	function submitOrder() {
		var form = document.getElementById('osoc-order-form');
		if (!form) return;
		var btn = form.querySelector('.osoc-submit-btn');
		var msg = form.querySelector('.osoc-form-messages');
		var fd = new FormData(form);
		fd.append('action', 'osoc_submit_order');
		fd.append('nonce', osoc.nonce);

		if (btn) {
			btn.disabled = true;
			btn.textContent = 'Отправка...';
		}
		if (msg) msg.innerHTML = '';

		fetch(osoc.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (btn) {
					btn.disabled = false;
					btn.textContent = 'Отправить заявку';
				}
				if (res && res.success) {
					if (res.data.redirect) {
						window.location.href = res.data.redirect;
						return;
					}
					if (msg) msg.innerHTML = '<div class="osoc-success">' + escapeHtml(res.data.message || '') + '</div>';
					form.reset();
					var addr = form.querySelector('.osoc-delivery-address');
					if (addr) addr.style.display = 'none';
					refreshCaptcha();
				} else {
					var errs = (res && res.data && res.data.errors) ? Object.values(res.data.errors).join('<br>') : (osoc.strings.error || 'Ошибка отправки.');
					if (msg) msg.innerHTML = '<div class="osoc-error">' + escapeHtml(errs) + '</div>';
					refreshCaptcha();
				}
			})
			.catch(function () {
				if (btn) {
					btn.disabled = false;
					btn.textContent = 'Отправить заявку';
				}
				if (msg) msg.innerHTML = '<div class="osoc-error">' + escapeHtml(osoc.strings.error || '') + '</div>';
			});
	}

	function bindCartControls() {
		var items = document.querySelectorAll('.osoc-cart-item');
		items.forEach(function (item) {
			var pid = item.getAttribute('data-product-id') || '';
			var input = item.querySelector('.osoc-qty-input');
			var remove = item.querySelector('.osoc-remove-item');
			var btns = item.querySelectorAll('.osoc-qty-btn');

			btns.forEach(function (b) {
				b.addEventListener('click', function () {
					if (!input) return;
					var delta = parseInt(b.getAttribute('data-delta'), 10) || 0;
					var val = Math.max(1, (parseInt(input.value, 10) || 1) + delta);
					input.value = val;
					updateCartQty(pid, val);
				});
			});

			if (input) {
				input.addEventListener('change', function () {
					var val = Math.max(1, parseInt(input.value, 10) || 1);
					input.value = val;
					updateCartQty(pid, val);
				});
			}

			if (remove) {
				remove.addEventListener('click', function () {
					updateCart(pid, 1, 'remove');
				});
			}
		});
	}

	function updateCartQty(pid, qty) {
		post('osoc_update_cart', { update_action: 'set', product_id: pid, quantity: String(qty) })
			.then(function () { window.location.reload(); })
			.catch(function () { window.location.reload(); });
	}

	function updateCart(pid, qty, action) {
		post('osoc_update_cart', { update_action: action, product_id: pid, quantity: String(qty) })
			.then(function () { window.location.reload(); })
			.catch(function () { window.location.reload(); });
	}

	/* =====================================================================
	 * Авторизация
	 * ================================================================== */

	function bindAuthForms() {
		var loginForms = document.querySelectorAll('.osoc-login-form');
		var regForms = document.querySelectorAll('.osoc-register-form');

		loginForms.forEach(function (f) {
			f.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = f.querySelector('.osoc-form-msg');
				var log = f.querySelector('[name="log"]');
				var pwd = f.querySelector('[name="pwd"]');
				var rem = f.querySelector('[name="remember"]');
				post('osoc_login', {
					log: log ? log.value : '',
					pwd: pwd ? pwd.value : '',
					remember: (rem && rem.checked) ? '1' : ''
				}).then(function (res) {
					if (res && res.success) {
						window.location.reload();
					} else {
						var m = (res && res.data && res.data.message) || (osoc.strings.login_error || '');
						if (msg) msg.innerHTML = '<div class="osoc-error">' + escapeHtml(m) + '</div>';
					}
				}).catch(function () {
					if (msg) msg.innerHTML = '<div class="osoc-error">' + escapeHtml(osoc.strings.login_error || '') + '</div>';
				});
			});
		});

		regForms.forEach(function (f) {
			f.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = f.querySelector('.osoc-form-msg');
				var username = f.querySelector('[name="username"]');
				var email = f.querySelector('[name="email"]');
				var password = f.querySelector('[name="password"]');
				post('osoc_register', {
					username: username ? username.value : '',
					email: email ? email.value : '',
					password: password ? password.value : ''
				}).then(function (res) {
					if (res && res.success) {
						window.location.reload();
					} else {
						var m = (res && res.data && res.data.message) || (osoc.strings.register_error || '');
						if (msg) msg.innerHTML = '<div class="osoc-error">' + escapeHtml(m) + '</div>';
					}
				}).catch(function () {
					if (msg) msg.innerHTML = '<div class="osoc-error">' + escapeHtml(osoc.strings.register_error || '') + '</div>';
				});
			});
		});
	}

	/* =====================================================================
	 * Init
	 * ================================================================== */

	ready(function () {
		if (osoc.is_order) {
			bindIntercept();
			// Перепривязка на случай динамической подгрузки кнопок.
			setTimeout(bindIntercept, 1000);
		}
		if (osoc.is_checkout) {
			bindCheckout();
			bindCartControls();
		}
		if (osoc.is_checkout || osoc.is_account) {
			bindAuthForms();
		}
	});
})();
