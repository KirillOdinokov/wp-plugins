<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$is_auth = is_user_logged_in();
?>
<div class="osoc-wrap">

	<h2 class="osoc-title"><?php esc_html_e( 'Личный кабинет', 'order-share-cart' ); ?></h2>

	<?php if ( ! $is_auth ) : ?>
		<div class="osoc-auth">
			<p class="osoc-auth-note"><?php esc_html_e( 'Войдите или зарегистрируйтесь, чтобы видеть историю Ваших заявок.', 'order-share-cart' ); ?></p>

			<div class="osoc-auth-cols">
				<div class="osoc-auth-col">
					<h3><?php esc_html_e( 'Вход', 'order-share-cart' ); ?></h3>
					<form class="osoc-login-form">
						<label><?php esc_html_e( 'Логин или email', 'order-share-cart' ); ?>
							<input type="text" name="log" autocomplete="username" required>
						</label>
						<label><?php esc_html_e( 'Пароль', 'order-share-cart' ); ?>
							<input type="password" name="pwd" autocomplete="current-password" required>
						</label>
						<label class="osoc-inline"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Запомнить меня', 'order-share-cart' ); ?></label>
						<button type="submit" class="osoc-btn osoc-btn-primary"><?php esc_html_e( 'Войти', 'order-share-cart' ); ?></button>
						<div class="osoc-form-msg"></div>
					</form>
				</div>

				<div class="osoc-auth-col">
					<h3><?php esc_html_e( 'Регистрация', 'order-share-cart' ); ?></h3>
					<form class="osoc-register-form">
						<label><?php esc_html_e( 'Логин', 'order-share-cart' ); ?>
							<input type="text" name="username" autocomplete="username" required>
						</label>
						<label><?php esc_html_e( 'Email', 'order-share-cart' ); ?>
							<input type="email" name="email" autocomplete="email" required>
						</label>
						<label><?php esc_html_e( 'Пароль', 'order-share-cart' ); ?>
							<input type="password" name="password" autocomplete="new-password" required>
						</label>
						<button type="submit" class="osoc-btn osoc-btn-primary"><?php esc_html_e( 'Создать аккаунт', 'order-share-cart' ); ?></button>
						<div class="osoc-form-msg"></div>
					</form>
				</div>
			</div>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	global $wpdb;
	$table = $wpdb->prefix . 'osoc_orders';
	$user_id = get_current_user_id();
	$orders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ) );
	?>

	<p class="osoc-auth-note">
		<?php
		printf(
			/* translators: %s имя пользователя */
			esc_html__( 'Вы вошли как %s.', 'order-share-cart' ),
			'<strong>' . esc_html( wp_get_current_user()->display_name ) . '</strong>'
		);
		?>
		<a href="<?php echo esc_url( wp_logout_url( OSOC_Main::account_url() ) ); ?>"><?php esc_html_e( 'Выйти', 'order-share-cart' ); ?></a>
	</p>

	<?php if ( empty( $orders ) ) : ?>
		<div class="osoc-empty">
			<p><?php esc_html_e( 'У Вас пока нет заявок.', 'order-share-cart' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="osoc-btn osoc-btn-primary"><?php esc_html_e( 'Перейти к выбору товаров', 'order-share-cart' ); ?></a>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="osoc-orders">
		<h3><?php esc_html_e( 'История заявок', 'order-share-cart' ); ?></h3>
		<?php foreach ( $orders as $order ) : ?>
			<?php
			$items = json_decode( $order->items, true );
			if ( ! is_array( $items ) ) {
				$items = array();
			}
			?>
			<div class="osoc-order">
				<div class="osoc-order-head">
					<span class="osoc-order-date"><?php echo esc_html( $order->created_at ); ?></span>
					<span class="osoc-order-status"><?php esc_html_e( 'В обработке', 'order-share-cart' ); ?></span>
				</div>
				<ul class="osoc-order-items">
					<?php foreach ( $items as $item ) : ?>
						<li><?php echo esc_html( $item['product_name'] ); ?> — <?php echo esc_html( $item['quantity'] ); ?> <?php esc_html_e( 'шт.', 'order-share-cart' ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</div>
</div>
