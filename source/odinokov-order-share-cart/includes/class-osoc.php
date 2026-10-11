<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OSOC_Main {

	const CART_COOKIE    = 'osoc_cart';
	const CHECKOUT_SLUG  = 'oformlenie-zayavki';
	const ACCOUNT_SLUG   = 'lichnyy-kabinet';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_shortcode( 'osoc_checkout', array( $this, 'render_checkout' ) );
		add_shortcode( 'osoc_account', array( $this, 'render_account' ) );

		// Корзина.
		add_action( 'wp_ajax_osoc_add_to_cart', array( $this, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_osoc_add_to_cart', array( $this, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_osoc_update_cart', array( $this, 'handle_update_cart' ) );
		add_action( 'wp_ajax_nopriv_osoc_update_cart', array( $this, 'handle_update_cart' ) );

		// Оформление.
		add_action( 'wp_ajax_osoc_refresh_captcha', array( $this, 'ajax_refresh_captcha' ) );
		add_action( 'wp_ajax_nopriv_osoc_refresh_captcha', array( $this, 'ajax_refresh_captcha' ) );
		add_action( 'wp_ajax_osoc_submit_order', array( $this, 'handle_submit_order' ) );
		add_action( 'wp_ajax_nopriv_osoc_submit_order', array( $this, 'handle_submit_order' ) );

		// Авторизация.
		add_action( 'wp_ajax_osoc_login', array( $this, 'handle_login' ) );
		add_action( 'wp_ajax_nopriv_osoc_login', array( $this, 'handle_login' ) );
		add_action( 'wp_ajax_osoc_register', array( $this, 'handle_register' ) );
		add_action( 'wp_ajax_nopriv_osoc_register', array( $this, 'handle_register' ) );
	}

	/* ---------------------------------------------------------------------
	 * Таблица заявок
	 * ------------------------------------------------------------------ */

	public static function create_table() {
		global $wpdb;
		$table   = $wpdb->prefix . 'osoc_orders';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			email VARCHAR(255) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			inn VARCHAR(64) NOT NULL DEFAULT '',
			accessories TEXT NULL,
			delivery VARCHAR(8) NOT NULL DEFAULT 'no',
			delivery_address TEXT NULL,
			items TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/* ---------------------------------------------------------------------
	 * Страницы
	 * ------------------------------------------------------------------ */

	public static function ensure_pages() {
		$pages = get_option( 'osoc_pages', array() );
		if ( ! is_array( $pages ) ) {
			$pages = array();
		}

		$defs = array(
			'checkout' => array(
				'title'   => 'Оформление заявки',
				'slug'    => self::CHECKOUT_SLUG,
				'content' => '[osoc_checkout]',
			),
			'account'  => array(
				'title'   => 'Личный кабинет',
				'slug'    => self::ACCOUNT_SLUG,
				'content' => '[osoc_account]',
			),
		);

		foreach ( $defs as $key => $def ) {
			if ( ! empty( $pages[ $key ] ) && get_post( (int) $pages[ $key ] ) ) {
				continue;
			}
			$page = get_page_by_path( $def['slug'], OBJECT, 'page' );
			if ( $page ) {
				$pages[ $key ] = (int) $page->ID;
				continue;
			}
			$id = wp_insert_post( array(
				'post_title'     => $def['title'],
				'post_name'      => $def['slug'],
				'post_content'   => $def['content'],
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				$pages[ $key ] = (int) $id;
			}
		}

		update_option( 'osoc_pages', $pages );
	}

	public static function get_page_id( $key ) {
		$pages = get_option( 'osoc_pages', array() );
		if ( is_array( $pages ) && ! empty( $pages[ $key ] ) ) {
			return (int) $pages[ $key ];
		}
		$slug = ( 'checkout' === $key ) ? self::CHECKOUT_SLUG : self::ACCOUNT_SLUG;
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		return $page ? (int) $page->ID : 0;
	}

	public static function checkout_url() {
		$id = self::get_page_id( 'checkout' );
		return $id ? get_permalink( $id ) : home_url( '/' . self::CHECKOUT_SLUG . '/' );
	}

	public static function account_url() {
		$id = self::get_page_id( 'account' );
		return $id ? get_permalink( $id ) : home_url( '/' . self::ACCOUNT_SLUG . '/' );
	}

	public static function is_osoc_page( $key ) {
		if ( ! is_page() ) {
			return false;
		}
		$id = self::get_page_id( $key );
		if ( $id && get_queried_object_id() === $id ) {
			return true;
		}
		global $post;
		if ( is_a( $post, 'WP_Post' ) ) {
			$sc = ( 'checkout' === $key ) ? 'osoc_checkout' : 'osoc_account';
			if ( has_shortcode( $post->post_content, $sc ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Настройки полей (наследуются из базового плагина)
	 * ------------------------------------------------------------------ */

	public static function field_settings() {
		$defaults = array(
			'field_inn'         => 1,
			'field_name'        => 1,
			'field_email'       => 1,
			'field_accessories' => 1,
			'field_files'       => 1,
			'field_delivery'    => 1,
			'field_captcha'     => 1,
		);
		if ( function_exists( 'oso_get_settings' ) ) {
			$base = oso_get_settings();
			foreach ( $defaults as $k => $v ) {
				if ( isset( $base[ $k ] ) ) {
					$defaults[ $k ] = ! empty( $base[ $k ] ) ? 1 : 0;
				}
			}
		}
		return $defaults;
	}

	/* ---------------------------------------------------------------------
	 * Корзина (cookie)
	 * ------------------------------------------------------------------ */

	public static function get_cart() {
		$cart = array();
		if ( isset( $_COOKIE[ self::CART_COOKIE ] ) && '' !== $_COOKIE[ self::CART_COOKIE ] ) {
			$decoded = json_decode( wp_unslash( $_COOKIE[ self::CART_COOKIE ] ), true );
			if ( is_array( $decoded ) ) {
				$cart = $decoded;
			}
		}
		$clean = array();
		foreach ( $cart as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$pid  = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
			$qty  = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
			if ( $pid > 0 || '' !== $name ) {
				$clean[] = array(
					'product_id'   => $pid,
					'product_name' => $name,
					'quantity'     => $qty,
				);
			}
		}
		return $clean;
	}

	public static function set_cart( $cart ) {
		$json   = wp_json_encode( $cart );
		$expire = time() + 30 * DAY_IN_SECONDS;
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		setcookie( self::CART_COOKIE, $json, $expire, $path, $domain, is_ssl(), true );
		$_COOKIE[ self::CART_COOKIE ] = $json;
	}

	public static function clear_cart() {
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		setcookie( self::CART_COOKIE, '', time() - 3600, $path, $domain, is_ssl(), true );
		unset( $_COOKIE[ self::CART_COOKIE ] );
	}

	public static function cart_count( $cart = null ) {
		if ( null === $cart ) {
			$cart = self::get_cart();
		}
		$count = 0;
		foreach ( $cart as $item ) {
			$count += max( 1, (int) $item['quantity'] );
		}
		return $count;
	}

	/* ---------------------------------------------------------------------
	 * Ассеты
	 * ------------------------------------------------------------------ */

	public function enqueue_assets() {
		$on_order    = is_shop() || is_product_taxonomy() || ( function_exists( 'is_product' ) && is_product() );
		$on_checkout = self::is_osoc_page( 'checkout' );
		$on_account  = self::is_osoc_page( 'account' );

		if ( ! $on_order && ! $on_checkout && ! $on_account ) {
			return;
		}

		$s = osoc_get_settings();

		wp_enqueue_style( 'osoc-public', OSOC_URL . 'public/css/osoc-public.css', array(), OSOC_VERSION );
		wp_enqueue_script( 'osoc-public', OSOC_URL . 'public/js/osoc-public.js', array(), OSOC_VERSION, true );

		wp_localize_script( 'osoc-public', 'osoc', array(
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'osoc_nonce' ),
			'checkout_url' => self::checkout_url(),
			'account_url'  => self::account_url(),
			'logged_in'    => is_user_logged_in(),
			'is_checkout'  => $on_checkout,
			'is_account'   => $on_account,
			'is_order'     => $on_order,
			'toast'        => array(
				'title'          => $s['toast_title'],
				'question'       => $s['toast_question'],
				'checkout_label' => $s['toast_checkout_label'],
				'continue_label' => $s['toast_continue_label'],
			),
			'strings'      => array(
				'required'      => __( 'Это поле обязательно', 'order-share-cart' ),
				'email'         => __( 'Введите корректный email', 'order-share-cart' ),
				'filesize'      => __( 'Файл слишком большой. Максимум 20 МБ.', 'order-share-cart' ),
				'filetype'      => __( 'Недопустимый формат файла.', 'order-share-cart' ),
				'maxfiles'      => __( 'Максимум 3 файла.', 'order-share-cart' ),
				'success'       => __( 'Заявка отправлена!', 'order-share-cart' ),
				'error'         => __( 'Ошибка. Попробуйте позже.', 'order-share-cart' ),
				'login_error'   => __( 'Неверный логин или пароль.', 'order-share-cart' ),
				'register_error'=> __( 'Не удалось создать аккаунт. Проверьте данные.', 'order-share-cart' ),
			),
		) );
	}

	/* ---------------------------------------------------------------------
	 * AJAX: корзина
	 * ------------------------------------------------------------------ */

	public function handle_add_to_cart() {
		check_ajax_referer( 'osoc_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product_name = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$quantity     = isset( $_POST['quantity'] ) ? max( 1, (int) $_POST['quantity'] ) : 1;

		if ( $product_id <= 0 && '' === $product_name ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось определить товар.', 'order-share-cart' ) ) );
		}

		$cart  = self::get_cart();
		$found = false;
		foreach ( $cart as &$item ) {
			if ( (int) $item['product_id'] === $product_id && $item['product_name'] === $product_name ) {
				$item['quantity'] += $quantity;
				$found = true;
				break;
			}
		}
		unset( $item );
		if ( ! $found ) {
			$cart[] = array(
				'product_id'   => $product_id,
				'product_name' => $product_name,
				'quantity'     => $quantity,
			);
		}

		self::set_cart( $cart );

		wp_send_json_success( array(
			'count'        => self::cart_count( $cart ),
			'cart'         => $cart,
			'checkout_url' => self::checkout_url(),
			'product_name' => $product_name,
		) );
	}

	public function handle_update_cart() {
		check_ajax_referer( 'osoc_nonce', 'nonce' );

		$action    = isset( $_POST['update_action'] ) ? sanitize_key( $_POST['update_action'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity  = isset( $_POST['quantity'] ) ? max( 1, (int) $_POST['quantity'] ) : 1;

		$cart = self::get_cart();

		if ( 'remove' === $action ) {
			$cart = array_values( array_filter( $cart, function ( $item ) use ( $product_id ) {
				return (int) $item['product_id'] !== $product_id;
			} ) );
		} elseif ( 'set' === $action ) {
			foreach ( $cart as &$item ) {
				if ( (int) $item['product_id'] === $product_id ) {
					$item['quantity'] = $quantity;
					break;
				}
			}
			unset( $item );
		}

		self::set_cart( $cart );

		wp_send_json_success( array(
			'count' => self::cart_count( $cart ),
			'cart'  => $cart,
		) );
	}

	/* ---------------------------------------------------------------------
	 * AJAX: капча
	 * ------------------------------------------------------------------ */

	public function ajax_refresh_captcha() {
		check_ajax_referer( 'osoc_nonce', 'nonce' );
		$captcha = $this->generate_captcha();
		wp_send_json_success( $captcha );
	}

	private function generate_captcha() {
		$num1 = wp_rand( 1, 10 );
		$num2 = wp_rand( 1, 10 );
		$key  = wp_rand( 100000, 999999 ) . '_' . time();
		$data = array(
			'num1'   => $num1,
			'num2'   => $num2,
			'answer' => $num1 + $num2,
			'time'   => time(),
		);
		set_transient( 'osoc_captcha_' . $key, $data, 5 * MINUTE_IN_SECONDS );
		return array(
			'question' => sprintf( __( '%1$d + %2$d = ?', 'order-share-cart' ), $num1, $num2 ),
			'key'      => $key,
		);
	}

	/* ---------------------------------------------------------------------
	 * AJAX: авторизация
	 * ------------------------------------------------------------------ */

	public function handle_login() {
		check_ajax_referer( 'osoc_nonce', 'nonce' );

		$log      = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$pwd      = isset( $_POST['pwd'] ) ? (string) $_POST['pwd'] : '';
		$remember = ! empty( $_POST['remember'] );

		if ( '' === $log || '' === $pwd ) {
			wp_send_json_error( array( 'message' => __( 'Заполните логин и пароль.', 'order-share-cart' ) ) );
		}

		$user = get_user_by( 'login', $log );
		if ( ! $user && is_email( $log ) ) {
			$user = get_user_by( 'email', $log );
		}
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Неверный логин или пароль.', 'order-share-cart' ) ) );
		}

		$res = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $pwd,
			'remember'      => $remember,
		), is_ssl() );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => __( 'Неверный логин или пароль.', 'order-share-cart' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Вы вошли.', 'order-share-cart' ), 'reload' => true ) );
	}

	public function handle_register() {
		check_ajax_referer( 'osoc_nonce', 'nonce' );

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

		if ( '' === $username || '' === $email || ! is_email( $email ) || strlen( $password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Проверьте данные. Пароль — не менее 6 символов.', 'order-share-cart' ) ) );
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Пользователь с таким логином или email уже существует.', 'order-share-cart' ) ) );
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		wp_send_json_success( array( 'message' => __( 'Аккаунт создан.', 'order-share-cart' ), 'reload' => true ) );
	}

	/* ---------------------------------------------------------------------
	 * AJAX: отправка заявки
	 * ------------------------------------------------------------------ */

	public function handle_submit_order() {
		check_ajax_referer( 'osoc_nonce', 'nonce' );

		$cart = self::get_cart();
		if ( empty( $cart ) ) {
			wp_send_json_error( array( 'errors' => array( 'general' => __( 'Корзина пуста.', 'order-share-cart' ) ) ) );
		}

		$fields = self::field_settings();

		$inn            = isset( $_POST['inn'] ) ? sanitize_text_field( wp_unslash( $_POST['inn'] ) ) : '';
		$name           = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$accessories    = isset( $_POST['accessories'] ) ? sanitize_textarea_field( wp_unslash( $_POST['accessories'] ) ) : '';
		$delivery       = isset( $_POST['delivery'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery'] ) ) : 'no';
		$delivery_addr  = isset( $_POST['delivery_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['delivery_address'] ) ) : '';
		$captcha_answer = isset( $_POST['captcha_answer'] ) ? intval( $_POST['captcha_answer'] ) : 0;
		$captcha_key    = isset( $_POST['captcha_key'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_key'] ) ) : '';

		// Для авторизованного пользователя — контакты из профиля.
		if ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			if ( '' === $name ) {
				$name = $u->display_name;
			}
			if ( '' === $email ) {
				$email = $u->user_email;
			}
		}

		$errors = array();
		if ( ! empty( $fields['field_name'] ) && '' === $name ) {
			$errors['name'] = __( 'Укажите, как к Вам обращаться', 'order-share-cart' );
		}
		if ( ! empty( $fields['field_email'] ) && ( '' === $email || ! is_email( $email ) ) ) {
			$errors['email'] = __( 'Введите корректный email', 'order-share-cart' );
		}
		if ( ! empty( $fields['field_captcha'] ) ) {
			$captcha_data = get_transient( 'osoc_captcha_' . $captcha_key );
			if ( empty( $captcha_data ) || ! isset( $captcha_data['answer'] ) || $captcha_answer !== intval( $captcha_data['answer'] ) ) {
				$errors['captcha'] = __( 'Неверный ответ. Попробуйте снова.', 'order-share-cart' );
			}
			delete_transient( 'osoc_captcha_' . $captcha_key );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		// Вложения.
		$attachments = array();
		if ( ! empty( $fields['field_files'] ) && ! empty( $_FILES['files'] ) ) {
			$attachments = $this->process_attachments( $_FILES['files'] );
		}

		$to = osoc_get_email_to();
		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$subject = __( 'Новая заявка (корзина)', 'order-share-cart' ) . ': ' . self::cart_summary_text( $cart );

		$message  = __( 'Новая заявка на сайте', 'order-share-cart' ) . "\r\n\r\n";
		$message .= __( 'Состав заявки:', 'order-share-cart' ) . "\r\n";
		foreach ( $cart as $item ) {
			$message .= ' - ' . $item['product_name'] . ' — ' . $item['quantity'] . ' ' . __( 'шт.', 'order-share-cart' ) . "\r\n";
		}
		$message .= "\r\n";
		if ( ! empty( $fields['field_inn'] ) ) {
			$message .= __( 'ИНН:', 'order-share-cart' ) . ' ' . ( $inn ? $inn : __( 'Физическое лицо', 'order-share-cart' ) ) . "\r\n";
		}
		$message .= __( 'Имя:', 'order-share-cart' ) . ' ' . $name . "\r\n";
		$message .= __( 'Email:', 'order-share-cart' ) . ' ' . $email . "\r\n";
		if ( ! empty( $fields['field_accessories'] ) && $accessories ) {
			$message .= __( 'Комплектующие и расходники:', 'order-share-cart' ) . "\r\n" . $accessories . "\r\n";
		}
		if ( ! empty( $fields['field_delivery'] ) ) {
			$message .= __( 'Доставка:', 'order-share-cart' ) . ' ' . ( 'yes' === $delivery ? __( 'Да', 'order-share-cart' ) : __( 'Нет', 'order-share-cart' ) ) . "\r\n";
			if ( 'yes' === $delivery && $delivery_addr ) {
				$message .= __( 'Адрес доставки:', 'order-share-cart' ) . "\r\n" . $delivery_addr . "\r\n";
			}
		}
		$message .= "\r\n" . __( '--- Отправлено с сайта ---', 'order-share-cart' );

		$from_email = get_option( 'oso_email_from', '' );
		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			$from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$from_name = get_bloginfo( 'name' );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);
		if ( '' !== $email ) {
			$headers[] = 'Reply-To: ' . $email;
		}

		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		foreach ( $attachments as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}
		}

		$this->save_order( $cart, array(
			'inn'              => $inn,
			'name'             => $name,
			'email'            => $email,
			'accessories'      => $accessories,
			'delivery'         => $delivery,
			'delivery_address' => $delivery_addr,
		) );

		$this->send_client_email( $email, $name, $cart );

		if ( ! $sent ) {
			wp_send_json_error( array( 'errors' => array( 'general' => __( 'Ошибка при отправке письма. Попробуйте позже.', 'order-share-cart' ) ) ) );
		}

		self::clear_cart();

		wp_send_json_success( array(
			'message'  => __( 'Заявка отправлена! Мы свяжемся с Вами в ближайшее время.', 'order-share-cart' ),
			'redirect' => self::account_url(),
		) );
	}

	private function process_attachments( $files ) {
		$attachments  = array();
		$allowed_ext  = array( 'jpg', 'jpeg', 'pdf', 'dwg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv' );
		$max_size     = 20 * 1024 * 1024;

		$upload_dir = wp_upload_dir();
		$tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'osoc-order-tmp/';
		if ( ! file_exists( $tmp_dir ) ) {
			wp_mkdir_p( $tmp_dir );
			file_put_contents( $tmp_dir . 'index.php', '<?php // Silence is golden.' );
			file_put_contents( $tmp_dir . '.htaccess', 'Deny from all' );
		}

		if ( is_array( $files['name'] ) ) {
			$count = count( $files['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( $i >= 3 ) {
					break;
				}
				if ( empty( $files['name'][ $i ] ) ) {
					continue;
				}
				if ( $files['size'][ $i ] > $max_size ) {
					continue;
				}
				$ext = strtolower( pathinfo( $files['name'][ $i ], PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, $allowed_ext, true ) ) {
					continue;
				}
				$tmp = $files['tmp_name'][ $i ];
				if ( is_uploaded_file( $tmp ) ) {
					$safe_name = sanitize_file_name( $files['name'][ $i ] );
					$dest      = $tmp_dir . time() . '_' . $i . '_' . $safe_name;
					if ( move_uploaded_file( $tmp, $dest ) ) {
						$attachments[] = $dest;
					}
				}
			}
		}

		return $attachments;
	}

	private static function cart_summary_text( $cart ) {
		$parts = array();
		foreach ( $cart as $item ) {
			$parts[] = $item['product_name'];
		}
		$text = implode( ', ', $parts );
		return mb_substr( $text, 0, 120 );
	}

	private function save_order( $cart, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'osoc_orders';
		$wpdb->insert(
			$table,
			array(
				'user_id'          => get_current_user_id(),
				'email'            => $data['email'],
				'name'             => $data['name'],
				'inn'              => $data['inn'],
				'accessories'      => $data['accessories'],
				'delivery'         => $data['delivery'],
				'delivery_address' => $data['delivery_address'],
				'items'            => wp_json_encode( $cart ),
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private function send_client_email( $email, $name, $cart ) {
		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		$enabled = 1;
		$subject = 'Ваша заявка принята';
		$message_html = '<p>Добрый день, ' . esc_html( $name ) . '!</p><p>Мы получили Вашу заявку, спасибо!</p>';

		if ( function_exists( 'oso_get_settings' ) ) {
			$base = oso_get_settings();
			if ( isset( $base['client_email_enabled'] ) ) {
				$enabled = ! empty( $base['client_email_enabled'] ) ? 1 : 0;
			}
			if ( isset( $base['client_email_subject'] ) && '' !== $base['client_email_subject'] ) {
				$subject = $base['client_email_subject'];
			}
			if ( isset( $base['client_email_message'] ) && '' !== $base['client_email_message'] ) {
				$message_html = $base['client_email_message'];
				$message_html = str_replace( '{name}', esc_html( $name ), $message_html );
				$message_html = str_replace( '{product}', esc_html( self::cart_summary_text( $cart ) ), $message_html );
				$message_html = str_replace( '{director_url}', esc_url( home_url( '/napisat-directoru/' ) ), $message_html );
			}
		}

		if ( ! $enabled ) {
			return false;
		}

		$from_email = get_option( 'oso_email_from', '' );
		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			$from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$from_name = get_bloginfo( 'name' );

		$reply_to = osoc_get_email_to();
		if ( empty( $reply_to ) || ! is_email( $reply_to ) ) {
			$reply_to = get_option( 'admin_email' );
		}

		$body  = '<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;">';
		$body .= $message_html;
		$body .= '</body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $reply_to,
		);

		return wp_mail( $email, $subject, $body, $headers );
	}

	/* ---------------------------------------------------------------------
	 * Рендер: шорткоды
	 * ------------------------------------------------------------------ */

	public function render_checkout() {
		ob_start();
		include OSOC_DIR . 'public/partials/checkout.php';
		return ob_get_clean();
	}

	public function render_account() {
		ob_start();
		include OSOC_DIR . 'public/partials/account.php';
		return ob_get_clean();
	}
}
