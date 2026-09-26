<?php
/**
 * Админ-страница настройки защиты.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ODHLV_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_odhlv_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_odhlv_clear_log', array( __CLASS__, 'handle_clear_log' ) );
		add_action( 'admin_post_odhlv_clear_traffic', array( __CLASS__, 'handle_clear_traffic' ) );
		add_action( 'admin_post_odhlv_install_mu', array( __CLASS__, 'handle_install_mu' ) );
		add_action( 'admin_post_odhlv_force_check', array( __CLASS__, 'force_check' ) );
	}

	public static function add_menu() {
		global $menu;
		$exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
			}
		}
		if ( ! $exists ) {
			add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', array( __CLASS__, 'dashboard' ), 'dashicons-admin-settings', 30 );
		}
		add_submenu_page( 'odinokov-plugins', 'High-Load VPS', 'High-Load VPS', 'manage_options', 'odhlv', array( __CLASS__, 'render_page' ) );
		add_submenu_page( 'odinokov-plugins', 'High-Load VPS — Log', 'High-Load Log', 'manage_options', 'odhlv-log', array( __CLASS__, 'render_log_page' ) );
		add_submenu_page( 'odinokov-plugins', 'High-Load VPS — Traffic', 'High-Load Traffic', 'manage_options', 'odhlv-traffic', array( __CLASS__, 'render_traffic_page' ) );
	}

	public static function dashboard() {
		?>
		<div class="wrap"><h1>Плагины Одиноков</h1>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
			<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
				<h3 style="margin-top:0;">Odinokov High-Load VPS</h3>
				<p>Защита от ботов: rate limiting, пагинация, фильтры, User-Agent, geo.</p>
			</div>
		</div></div>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$config = ODHLV_Core::get_config();
		$db = ODHLV_Core::get_db_info();

		$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
		$mu_file = $mu_dir . '/odinokov-high-load-vps-mu.php';
		$mu_exists = file_exists( $mu_file );

		$saved = isset( $_GET['saved'] );
		?>
		<div class="wrap">
			<h1>Odinokov High-Load VPS</h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="odhlv_save">
				<?php wp_nonce_field( 'odhlv_save', 'odhlv_nonce' ); ?>

				<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
					<h2>Общие</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="odhlv-enabled">Включить защиту</label></th>
							<td><label><input type="checkbox" id="odhlv-enabled" name="config[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ), true ); ?>> Активно</label></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
					<h2>Rate Limiting (по IP)</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="odhlv-rl-enabled">Включить</label></th>
							<td><label><input type="checkbox" id="odhlv-rl-enabled" name="config[rate_limit_enabled]" value="1" <?php checked( ! empty( $config['rate_limit_enabled'] ), true ); ?>> Ограничивать частоту запросов</label></td>
						</tr>
						<tr>
							<th><label for="odhlv-rl-max">Макс. запросов</label></th>
							<td><input type="number" id="odhlv-rl-max" name="config[rate_limit_max_requests]" value="<?php echo esc_attr( $config['rate_limit_max_requests'] ); ?>" min="1" max="10000" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="odhlv-rl-window">Окно (сек)</label></th>
							<td><input type="number" id="odhlv-rl-window" name="config[rate_limit_window]" value="<?php echo esc_attr( $config['rate_limit_window'] ); ?>" min="1" max="3600" class="small-text">
							<p class="description">Если IP делает больше указанного числа запросов за окно — блокировка.</p></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
					<h2>Пагинация и объём (/wp-json/wc/store, каталог)</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="odhlv-pg-enabled">Включить</label></th>
							<td><label><input type="checkbox" id="odhlv-pg-enabled" name="config[block_pagination_enabled]" value="1" <?php checked( ! empty( $config['block_pagination_enabled'] ), true ); ?>> Блокировать глубокую пагинацию и большой per_page</label></td>
						</tr>
						<tr>
							<th><label for="odhlv-pg-maxpage">Макс. page</label></th>
							<td><input type="number" id="odhlv-pg-maxpage" name="config[max_page]" value="<?php echo esc_attr( $config['max_page'] ); ?>" min="1" max="1000" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="odhlv-pg-maxper">Макс. per_page</label></th>
							<td><input type="number" id="odhlv-pg-maxper" name="config[max_per_page]" value="<?php echo esc_attr( $config['max_per_page'] ); ?>" min="1" max="200" class="small-text"></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
					<h2>Длинные filter-строки</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="odhlv-q-enabled">Включить</label></th>
							<td><label><input type="checkbox" id="odhlv-q-enabled" name="config[block_long_query_enabled]" value="1" <?php checked( ! empty( $config['block_long_query_enabled'] ), true ); ?>> Блокировать длинные query-строки</label></td>
						</tr>
						<tr>
							<th><label for="odhlv-q-len">Макс. длина query (символов)</label></th>
							<td><input type="number" id="odhlv-q-len" name="config[max_query_length]" value="<?php echo esc_attr( $config['max_query_length'] ); ?>" min="50" max="5000" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="odhlv-q-params">Макс. параметров</label></th>
							<td><input type="number" id="odhlv-q-params" name="config[max_query_params]" value="<?php echo esc_attr( $config['max_query_params'] ); ?>" min="1" max="100" class="small-text"></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
					<h2>User-Agent</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="odhlv-ua-enabled">Включить</label></th>
							<td><label><input type="checkbox" id="odhlv-ua-enabled" name="config[block_bad_ua_enabled]" value="1" <?php checked( ! empty( $config['block_bad_ua_enabled'] ), true ); ?>> Блокировать ботов по User-Agent</label></td>
						</tr>
						<tr>
							<th><label for="odhlv-ua-patterns">Паттерны (по одному на строку)</label></th>
							<td>
								<textarea id="odhlv-ua-patterns" name="config[blocked_ua_patterns]" rows="8" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $config['blocked_ua_patterns'] ) ); ?></textarea>
								<p class="description">Подстрока в User-Agent (регистронезависимо). Поисковики (Googlebot, Bingbot, YandexBot и т.д.) в белом списке и не блокируются.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
					<h2>Geo-блокировка (опционально)</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="odhlv-geo-enabled">Включить</label></th>
							<td><label><input type="checkbox" id="odhlv-geo-enabled" name="config[geo_enabled]" value="1" <?php checked( ! empty( $config['geo_enabled'] ), true ); ?>> Блокировать по стране</label></td>
						</tr>
						<tr>
							<th><label for="odhlv-geo-countries">Разрешённые страны (коды)</label></th>
							<td><input type="text" id="odhlv-geo-countries" name="config[allowed_countries]" value="<?php echo esc_attr( implode( ', ', (array) $config['allowed_countries'] ) ); ?>" class="regular-text" placeholder="RU, BY, KZ, UZ"></td>
						</tr>
						<tr>
							<th>База GeoIP</th>
							<td>
								<?php if ( $db['exists'] ) : ?>
									<span style="color:green;font-weight:600;">Загружена</span> — <?php echo esc_html( $db['build_date'] ); ?> (<?php echo esc_html( $db['size'] ); ?>)
								<?php else : ?>
									<span style="color:red;font-weight:600;">Отсутствует</span>
								<?php endif; ?>
								<p class="description">Файл: <code><?php echo esc_html( $db['file'] ); ?></code></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( 'Сохранить настройки' ); ?>
			</form>

			<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
				<h2>Must-Use плагин</h2>
				<p>Статус: <?php echo $mu_exists ? '<span style="color:green">Установлен</span>' : '<span style="color:red">Не установлен</span>'; ?></p>
				<?php if ( ! $mu_exists ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'odhlv_install_mu', 'odhlv_nonce' ); ?>
						<input type="hidden" name="action" value="odhlv_install_mu">
						<button type="submit" class="button button-primary">Установить MU-плагин</button>
					</form>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
				<?php wp_nonce_field( 'odhlv_force_check', 'odhlv_force_check_nonce' ); ?>
				<input type="hidden" name="action" value="odhlv_force_check">
				<?php submit_button( __( 'Проверить обновления', 'odinokov-high-load-vps' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
		check_admin_referer( 'odhlv_save', 'odhlv_nonce' );

		$input = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : array();
		if ( ! is_array( $input ) ) $input = array();

		$defaults = ODHLV_Core::default_config();

		$config = array(
			'enabled'                  => ! empty( $input['enabled'] ) ? 1 : 0,
			'rate_limit_enabled'       => ! empty( $input['rate_limit_enabled'] ) ? 1 : 0,
			'rate_limit_max_requests'  => max( 1, min( 10000, (int) ( $input['rate_limit_max_requests'] ?? $defaults['rate_limit_max_requests'] ) ) ),
			'rate_limit_window'        => max( 1, min( 3600, (int) ( $input['rate_limit_window'] ?? $defaults['rate_limit_window'] ) ) ),
			'block_pagination_enabled' => ! empty( $input['block_pagination_enabled'] ) ? 1 : 0,
			'max_page'                 => max( 1, min( 1000, (int) ( $input['max_page'] ?? $defaults['max_page'] ) ) ),
			'max_per_page'             => max( 1, min( 200, (int) ( $input['max_per_page'] ?? $defaults['max_per_page'] ) ) ),
			'block_long_query_enabled' => ! empty( $input['block_long_query_enabled'] ) ? 1 : 0,
			'max_query_length'         => max( 50, min( 5000, (int) ( $input['max_query_length'] ?? $defaults['max_query_length'] ) ) ),
			'max_query_params'         => max( 1, min( 100, (int) ( $input['max_query_params'] ?? $defaults['max_query_params'] ) ) ),
			'block_bad_ua_enabled'     => ! empty( $input['block_bad_ua_enabled'] ) ? 1 : 0,
			'geo_enabled'              => ! empty( $input['geo_enabled'] ) ? 1 : 0,
			'allowed_countries'        => array(),
			'blocked_ua_patterns'      => array(),
		);

		// Страны.
		$countries = isset( $input['allowed_countries'] ) ? trim( (string) $input['allowed_countries'] ) : '';
		if ( '' !== $countries ) {
			$config['allowed_countries'] = array_values( array_filter( array_map( function ( $c ) {
				$c = strtoupper( trim( $c ) );
				return ( preg_match( '/^[A-Z]{2}$/', $c ) ) ? $c : '';
			}, preg_split( '/[\s,;]+/', $countries ) ) ) );
		}
		if ( empty( $config['allowed_countries'] ) ) {
			$config['allowed_countries'] = $defaults['allowed_countries'];
		}

		// UA-паттерны.
		$patterns = isset( $input['blocked_ua_patterns'] ) ? (string) $input['blocked_ua_patterns'] : '';
		if ( '' !== trim( $patterns ) ) {
			$config['blocked_ua_patterns'] = array_values( array_filter( array_map( 'trim', explode( "\n", $patterns ) ) ) );
		}
		if ( empty( $config['blocked_ua_patterns'] ) ) {
			$config['blocked_ua_patterns'] = $defaults['blocked_ua_patterns'];
		}

		ODHLV_Core::save_config( $config );

		wp_safe_redirect( admin_url( 'admin.php?page=odhlv&saved=1' ) );
		exit;
	}

	public static function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
		check_admin_referer( 'odhlv_clear_log', 'odhlv_nonce' );
		ODHLV_Core::clear_block_log();
		wp_safe_redirect( admin_url( 'admin.php?page=odhlv-log&cleared=1' ) );
		exit;
	}

	public static function handle_clear_traffic() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
		check_admin_referer( 'odhlv_clear_traffic', 'odhlv_nonce' );
		ODHLV_Core::clear_traffic_log();
		wp_safe_redirect( admin_url( 'admin.php?page=odhlv-traffic&cleared=1' ) );
		exit;
	}

	public static function handle_install_mu() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
		check_admin_referer( 'odhlv_install_mu', 'odhlv_nonce' );
		$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $mu_dir ) ) wp_mkdir_p( $mu_dir );
		copy( ODHLV_DIR . 'odinokov-high-load-vps-mu.php', $mu_dir . '/odinokov-high-load-vps-mu.php' );
		wp_safe_redirect( admin_url( 'admin.php?page=odhlv&mu_installed=1' ) );
		exit;
	}

	public static function force_check() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
		check_admin_referer( 'odhlv_force_check', 'odhlv_force_check_nonce' );
		delete_transient( 'odhlv_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-high-load-vps.json' ) );
		set_site_transient( 'update_plugins', null );
		wp_safe_redirect( admin_url( 'plugins.php?odhlv_force_check_done=1' ) );
		exit;
	}

	public static function render_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$entries = array_reverse( ODHLV_Core::get_block_log() );
		?>
		<div class="wrap">
			<h1>Журнал блокировок</h1>
			<?php if ( empty( $entries ) ) : ?>
				<p>Журнал пуст.</p>
			<?php else : ?>
				<div style="margin:16px 0;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'odhlv_clear_log', 'odhlv_nonce' ); ?>
						<input type="hidden" name="action" value="odhlv_clear_log">
						<button type="submit" class="button" onclick="return confirm('Очистить журнал?')">Очистить</button>
					</form>
					<span style="margin-left:12px;color:#666;">Записей: <?php echo count( $entries ); ?> / 2000</span>
				</div>
				<table class="wp-list-table widefat fixed striped" style="max-width:1000px;">
					<thead><tr><th>Время</th><th>IP</th><th>Причина</th><th>Детали</th><th>User-Agent</th></tr></thead>
					<tbody>
						<?php foreach ( $entries as $e ) : ?>
							<tr>
								<td style="white-space:nowrap;"><?php echo esc_html( $e['time'] ?? '' ); ?></td>
								<td><code><?php echo esc_html( $e['ip'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $e['reason'] ?? '' ); ?></td>
								<td style="max-width:300px;word-break:break-word;"><?php echo esc_html( $e['detail'] ?? '' ); ?></td>
								<td style="max-width:200px;word-break:break-word;font-size:11px;"><?php echo esc_html( $e['ua'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_traffic_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$entries = array_reverse( ODHLV_Core::get_traffic_log() );

		$names = array(
			'RU' => 'Россия', 'BY' => 'Беларусь', 'UA' => 'Украина', 'KZ' => 'Казахстан', 'UZ' => 'Узбекистан',
			'US' => 'США', 'CN' => 'Китай', 'DE' => 'Германия', 'NL' => 'Нидерланды', 'FR' => 'Франция',
			'GB' => 'Великобритания', 'IN' => 'Индия', 'BR' => 'Бразилия', 'JP' => 'Япония', 'KR' => 'Корея',
		);
		$reason_labels = array(
			'rate_limit' => 'Rate Limit',
			'pagination' => 'Пагинация',
			'long_query' => 'Длинный filter',
			'bad_ua'     => 'User-Agent',
			'geo'        => 'Geo',
		);
		?>
		<div class="wrap">
			<h1>Трафик заблокированных</h1>
			<?php if ( empty( $entries ) ) : ?>
				<p>Журнал пуст.</p>
			<?php else : ?>
				<div style="margin:16px 0;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'odhlv_clear_traffic', 'odhlv_nonce' ); ?>
						<input type="hidden" name="action" value="odhlv_clear_traffic">
						<button type="submit" class="button" onclick="return confirm('Очистить журнал?')">Очистить</button>
					</form>
					<span style="margin-left:12px;color:#666;">Записей: <?php echo count( $entries ); ?> / 2000</span>
				</div>
				<table class="wp-list-table widefat fixed striped" style="max-width:100%;">
					<thead>
						<tr>
							<th style="width:130px;">Время</th>
							<th style="width:120px;">IP</th>
							<th style="width:90px;">Страна</th>
							<th style="width:80px;">Метод</th>
							<th style="width:110px;">Причина</th>
							<th>URL</th>
							<th>User-Agent</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $e ) : ?>
							<?php
							$c = isset( $e['country'] ) ? strtoupper( (string) $e['country'] ) : '';
							$country_label = ( $c && isset( $names[ $c ] ) ) ? $names[ $c ] . ' (' . $c . ')' : ( $c ?: '—' );
							$reason = isset( $e['reason'] ) ? $e['reason'] : '';
							$reason_label = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : $reason;
							?>
							<tr>
								<td style="white-space:nowrap;font-size:12px;"><?php echo esc_html( $e['time'] ?? '' ); ?></td>
								<td><code><?php echo esc_html( $e['ip'] ?? '' ); ?></code></td>
								<td style="white-space:nowrap;"><?php echo esc_html( $country_label ); ?></td>
								<td><?php echo esc_html( $e['method'] ?? '' ); ?></td>
								<td><span class="odhlv-reason odhlv-reason--<?php echo esc_attr( $reason ); ?>"><?php echo esc_html( $reason_label ); ?></span></td>
								<td style="max-width:320px;word-break:break-all;font-size:12px;"><?php echo esc_html( $e['url'] ?? '' ); ?></td>
								<td style="max-width:200px;word-break:break-word;font-size:11px;"><?php echo esc_html( $e['ua'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
