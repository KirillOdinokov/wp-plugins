<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cart    = OSOC_Main::get_cart();
$fields  = OSOC_Main::field_settings();
$is_auth = is_user_logged_in();

$prefill_name  = '';
$prefill_email = '';
if ( $is_auth ) {
	$u = wp_get_current_user();
	$prefill_name  = $u->display_name;
	$prefill_email = $u->user_email;
}
?>
<div class="osoc-wrap">

	<h2 class="osoc-title"><?php esc_html_e( 'Оформление заявки', 'order-share-cart' ); ?></h2>

	<?php if ( empty( $cart ) ) : ?>
		<div class="osoc-empty">
			<p><?php esc_html_e( 'Ваша корзина пуста.', 'order-share-cart' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="osoc-btn osoc-btn-primary"><?php esc_html_e( 'Перейти к выбору товаров', 'order-share-cart' ); ?></a>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( ! $is_auth ) : ?>
		<div class="osoc-auth">
			<p class="osoc-auth-note"><?php esc_html_e( 'Чтобы отслеживать историю заявок, авторизуйтесь или создайте аккаунт. Это необязательно — можно оформить заявку без регистрации.', 'order-share-cart' ); ?></p>

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
	<?php else : ?>
		<p class="osoc-auth-note">
			<?php
			printf(
				/* translators: %s имя пользователя */
				esc_html__( 'Вы вошли как %s.', 'order-share-cart' ),
				'<strong>' . esc_html( $prefill_name ) . '</strong>'
			);
			?>
			<a href="<?php echo esc_url( wp_logout_url( OSOC_Main::checkout_url() ) ); ?>"><?php esc_html_e( 'Выйти', 'order-share-cart' ); ?></a>
		</p>
	<?php endif; ?>

	<div class="osoc-cart">
		<h3><?php esc_html_e( 'Ваша заявка', 'order-share-cart' ); ?></h3>
		<div class="osoc-cart-items">
			<?php foreach ( $cart as $item ) : ?>
				<div class="osoc-cart-item" data-product-id="<?php echo esc_attr( $item['product_id'] ); ?>">
					<div class="osoc-cart-name">
						<?php echo esc_html( $item['product_name'] ); ?>
						<?php if ( ! empty( $item['product_id'] ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $item['product_id'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'смотреть', 'order-share-cart' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="osoc-cart-controls">
						<div class="osoc-qty-row">
							<button type="button" class="osoc-qty-btn" data-delta="-1" aria-label="<?php esc_attr_e( 'Уменьшить', 'order-share-cart' ); ?>">&minus;</button>
							<input type="number" class="osoc-qty-input" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" step="1">
							<button type="button" class="osoc-qty-btn" data-delta="1" aria-label="<?php esc_attr_e( 'Увеличить', 'order-share-cart' ); ?>">&plus;</button>
						</div>
						<button type="button" class="osoc-remove-item"><?php esc_html_e( 'Удалить', 'order-share-cart' ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<form id="osoc-order-form" method="post" enctype="multipart/form-data">
		<input type="hidden" name="captcha_key" id="osoc-captcha-key" value="">

		<?php if ( ! empty( $fields['field_inn'] ) ) : ?>
		<div class="osoc-form-group">
			<label for="osoc-inn"><?php esc_html_e( 'ИНН компании', 'order-share-cart' ); ?></label>
			<input type="number" name="inn" id="osoc-inn" placeholder="<?php esc_attr_e( 'Если Вы — физическое лицо, оставьте пустым', 'order-share-cart' ); ?>">
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $fields['field_name'] ) ) : ?>
		<div class="osoc-form-group">
			<label for="osoc-name"><?php esc_html_e( 'Как к Вам обращаться', 'order-share-cart' ); ?> <span class="osoc-required">*</span></label>
			<input type="text" name="name" id="osoc-name" value="<?php echo esc_attr( $prefill_name ); ?>" required>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $fields['field_email'] ) ) : ?>
		<div class="osoc-form-group">
			<label for="osoc-email"><?php esc_html_e( 'Email', 'order-share-cart' ); ?> <span class="osoc-required">*</span></label>
			<input type="email" name="email" id="osoc-email" value="<?php echo esc_attr( $prefill_email ); ?>" required>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $fields['field_accessories'] ) ) : ?>
		<div class="osoc-form-group">
			<label for="osoc-accessories"><?php esc_html_e( 'Комплектующие и расходники', 'order-share-cart' ); ?></label>
			<textarea name="accessories" id="osoc-accessories" rows="3" placeholder="<?php esc_attr_e( 'Напишите в простой форме, если ещё что-то нужно', 'order-share-cart' ); ?>"></textarea>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $fields['field_files'] ) ) : ?>
		<div class="osoc-form-group">
			<label for="osoc-files"><?php esc_html_e( 'Вложения', 'order-share-cart' ); ?></label>
			<input type="file" name="files[]" id="osoc-files" multiple accept=".jpg,.jpeg,.pdf,.dwg,.png,.webp,.doc,.xls,.csv">
			<p class="osoc-hint"><?php esc_html_e( 'До 3 файлов, до 20 МБ. Форматы: JPG, JPEG, PDF, DWG, PNG, WEBP, DOC, XLS, CSV.', 'order-share-cart' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $fields['field_delivery'] ) ) : ?>
		<div class="osoc-form-group">
			<label><?php esc_html_e( 'Доставка', 'order-share-cart' ); ?></label>
			<label class="osoc-radio"><input type="radio" name="delivery" value="no" checked> <?php esc_html_e( 'Нет', 'order-share-cart' ); ?></label>
			<label class="osoc-radio"><input type="radio" name="delivery" value="yes"> <?php esc_html_e( 'Да', 'order-share-cart' ); ?></label>
		</div>
		<div class="osoc-form-group osoc-delivery-address" style="display:none;">
			<label for="osoc-delivery-address"><?php esc_html_e( 'Адрес доставки', 'order-share-cart' ); ?></label>
			<textarea name="delivery_address" id="osoc-delivery-address" rows="2"></textarea>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $fields['field_captcha'] ) ) : ?>
		<div class="osoc-form-group">
			<label for="osoc-captcha"><?php esc_html_e( 'Капча', 'order-share-cart' ); ?></label>
			<div class="osoc-captcha-row">
				<div class="osoc-captcha-wrap">
					<span class="osoc-captcha-question"></span>
					<a href="#" class="osoc-captcha-refresh"><?php esc_html_e( 'Обновить', 'order-share-cart' ); ?></a>
				</div>
				<input type="number" name="captcha_answer" id="osoc-captcha" required>
			</div>
		</div>
		<?php endif; ?>

		<div class="osoc-form-group">
			<button type="submit" class="osoc-submit-btn"><?php esc_html_e( 'Отправить заявку', 'order-share-cart' ); ?></button>
		</div>

		<div class="osoc-form-messages"></div>
	</form>
</div>
