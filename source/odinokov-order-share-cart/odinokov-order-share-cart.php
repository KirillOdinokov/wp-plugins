<?php
/**
 * Plugin Name:       Order Share Odinokov Cart
 * Plugin URI:        https://github.com/KirillOdinokov/wp-plugins
 * Description:       Расширение Order Share Odinokov: корзина заявок вместо PopUp-окна. При клике «Добавить в заявку» товар попадает в корзину с уведомлением у курсора. Страницы «Оформление заявки» и «Личный кабинет» (история заявок) с авторизацией на базе пользователей WordPress.
 * Version:           1.0.0
 * Author:            Odinokov
 * Author URI:        https://github.com/KirillOdinokov/wp-plugins
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       order-share-cart
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OSOC_VERSION' ) ) {
	define( 'OSOC_VERSION', '1.0.0' );
}
if ( ! defined( 'OSOC_FILE' ) ) {
	define( 'OSOC_FILE', __FILE__ );
}
if ( ! defined( 'OSOC_DIR' ) ) {
	define( 'OSOC_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'OSOC_URL' ) ) {
	define( 'OSOC_URL', plugin_dir_url( __FILE__ ) );
}

require_once OSOC_DIR . 'includes/class-osoc-updater.php';
require_once OSOC_DIR . 'includes/class-osoc.php';

new OSOC_Plugin_Updater(
	__FILE__,
	'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-order-share-cart.json',
	OSOC_VERSION,
	array(
		'name'        => 'Order Share Odinokov Cart',
		'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
		'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
		'description' => 'Корзина заявок, оформление заявки и личный кабинет вместо PopUp-окна.',
	)
);

add_action( 'plugins_loaded', 'osoc_init', 20 );
function osoc_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'osoc_wc_missing_notice' );
		return;
	}
	load_plugin_textdomain( 'order-share-cart', false, dirname( plugin_basename( OSOC_FILE ) ) . '/languages' );
	new OSOC_Main();

	if ( get_option( 'osoc_db_version' ) !== OSOC_VERSION ) {
		OSOC_Main::create_table();
		OSOC_Main::ensure_pages();
		update_option( 'osoc_db_version', OSOC_VERSION );
	}
}

function osoc_wc_missing_notice() {
	echo '<div class="error notice"><p>' . esc_html__( 'Для плагина Order Share Odinokov Cart необходимо установить и активировать WooCommerce.', 'order-share-cart' ) . '</p></div>';
}

register_activation_hook( __FILE__, 'osoc_activate' );
function osoc_activate() {
	$opts = get_option( 'osoc_settings', array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	if ( empty( $opts ) ) {
		update_option( 'osoc_settings', osoc_defaults() );
	}
	if ( class_exists( 'OSOC_Main' ) ) {
		OSOC_Main::create_table();
		OSOC_Main::ensure_pages();
	}
}

register_deactivation_hook( __FILE__, 'osoc_deactivate' );
function osoc_deactivate() {
	// Ничего не удаляем: корзина в cookie, страницы и заявки остаются.
}

function osoc_defaults() {
	return array(
		'toast_title'          => 'Товар добавлен в заявку',
		'toast_question'       => 'Продолжить оформление заявки или Вам нужно что-то ещё?',
		'toast_checkout_label' => 'Оформить заявку',
		'toast_continue_label' => 'Продолжить выбор',
		'email_to'             => '',
	);
}

function osoc_get_settings() {
	$defaults = osoc_defaults();
	$opts     = get_option( 'osoc_settings', array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	$merged = array_merge( $defaults, $opts );

	$merged['toast_title']          = osoc_sanitize_text( $merged['toast_title'] );
	$merged['toast_question']       = osoc_sanitize_text( $merged['toast_question'] );
	$merged['toast_checkout_label'] = osoc_sanitize_text( $merged['toast_checkout_label'] );
	$merged['toast_continue_label'] = osoc_sanitize_text( $merged['toast_continue_label'] );

	if ( '' === $merged['toast_title'] ) {
		$merged['toast_title'] = $defaults['toast_title'];
	}
	if ( '' === $merged['toast_question'] ) {
		$merged['toast_question'] = $defaults['toast_question'];
	}
	if ( '' === $merged['toast_checkout_label'] ) {
		$merged['toast_checkout_label'] = $defaults['toast_checkout_label'];
	}
	if ( '' === $merged['toast_continue_label'] ) {
		$merged['toast_continue_label'] = $defaults['toast_continue_label'];
	}

	$merged['email_to'] = sanitize_email( wp_unslash( $merged['email_to'] ) );

	return $merged;
}

function osoc_sanitize_text( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	$v = sanitize_text_field( wp_unslash( $v ) );
	return substr( $v, 0, 300 );
}

add_action( 'admin_init', 'osoc_register_settings' );
add_action( 'admin_post_osoc_force_check', 'osoc_force_check' );
function osoc_register_settings() {
	register_setting(
		'osoc_settings_group',
		'osoc_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'osoc_sanitize_settings',
			'default'           => osoc_defaults(),
		)
	);
}

function osoc_sanitize_settings( $input ) {
	$defaults = osoc_defaults();
	$input    = is_array( $input ) ? $input : array();
	$out      = array();

	foreach ( array( 'toast_title', 'toast_question', 'toast_checkout_label', 'toast_continue_label' ) as $k ) {
		$out[ $k ] = osoc_sanitize_text( $input[ $k ] ?? $defaults[ $k ] );
		if ( '' === $out[ $k ] ) {
			$out[ $k ] = $defaults[ $k ];
		}
	}

	$out['email_to'] = isset( $input['email_to'] ) ? sanitize_email( wp_unslash( $input['email_to'] ) ) : '';

	return $out;
}

function osoc_force_check() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Access denied.' );
	}
	check_admin_referer( 'osoc_force_check', 'osoc_force_check_nonce' );
	delete_transient( 'osoc_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-order-share-cart.json' ) );
	set_site_transient( 'update_plugins', null );
	wp_safe_redirect( admin_url( 'plugins.php?osoc_checked=1' ) );
	exit;
}

add_action( 'admin_menu', 'osoc_register_menu' );
function osoc_register_menu() {
	global $menu;
	$e = false;
	if ( is_array( $menu ) ) {
		foreach ( $menu as $i ) {
			if ( isset( $i[2] ) && 'odinokov-plugins' === $i[2] ) {
				$e = true;
				break;
			}
		}
	}
	if ( ! $e ) {
		add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', 'osoc_dashboard', 'dashicons-admin-settings', 30 );
	}

	add_submenu_page(
		'odinokov-plugins',
		esc_html__( 'Order Share Cart', 'order-share-cart' ),
		esc_html__( 'Order Share Cart', 'order-share-cart' ),
		'manage_options',
		'order-share-cart',
		'osoc_render_settings_page'
	);
}

function osoc_dashboard() {
	?>
	<div class="wrap"><h1>Плагины Одиноков</h1>
	<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
		<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
			<h3 style="margin-top:0;">Order Share Odinokov Cart</h3>
			<p>Корзина заявок, оформление заявки и личный кабинет.</p>
		</div>
	</div></div>
	<?php
}

function osoc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = osoc_get_settings();
	?>
	<div class="wrap osoc-admin">
		<h1>Order Share Odinokov Cart — настройки</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'osoc_settings_group' ); ?>

			<h2>Уведомление о добавлении в корзину</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Заголовок уведомления</th>
					<td><input type="text" class="regular-text" name="osoc_settings[toast_title]" value="<?php echo esc_attr( $s['toast_title'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Текст вопроса</th>
					<td><input type="text" class="large-text" name="osoc_settings[toast_question]" value="<?php echo esc_attr( $s['toast_question'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Кнопка «Оформить заявку»</th>
					<td><input type="text" class="regular-text" name="osoc_settings[toast_checkout_label]" value="<?php echo esc_attr( $s['toast_checkout_label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Кнопка «Продолжить выбор»</th>
					<td><input type="text" class="regular-text" name="osoc_settings[toast_continue_label]" value="<?php echo esc_attr( $s['toast_continue_label'] ); ?>"></td>
				</tr>
			</table>

			<h2>Email</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Email для получения заявок</th>
					<td>
						<input type="email" class="regular-text" name="osoc_settings[email_to]" value="<?php echo esc_attr( $s['email_to'] ); ?>" placeholder="<?php echo esc_attr( osoc_get_email_to() ); ?>">
						<p class="description">Пусто — используется email из плагина Order Share Odinokov или адрес администратора.</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
			<?php wp_nonce_field( 'osoc_force_check', 'osoc_force_check_nonce' ); ?>
			<input type="hidden" name="action" value="osoc_force_check">
			<?php submit_button( __( 'Проверить обновления', 'order-share-cart' ), 'secondary' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Возвращает email получателя заявок: свой, затем базового плагина, затем админ.
 */
function osoc_get_email_to() {
	$s = osoc_get_settings();
	if ( ! empty( $s['email_to'] ) ) {
		return $s['email_to'];
	}
	$base = get_option( 'oso_email_to', '' );
	if ( ! empty( $base ) ) {
		return $base;
	}
	return get_option( 'admin_email' );
}
