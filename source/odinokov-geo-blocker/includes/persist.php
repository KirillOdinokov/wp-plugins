<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Персистентное хранение данных плагина.
 *
 * Папка data/ находится внутри папки плагина, а WordPress при обновлении
 * удаляет её и распаковывает новый ZIP. Поэтому база GeoLite2, список
 * исключений и журнал блокировок стираются при каждом апдейте.
 *
 * Решение: держим резервную копию этих файлов в wp-content/uploads/odinokov-geo-blocker/.
 * При загрузке (в т.ч. на этапе mu-плагина) синхронизируем в обе стороны:
 *  - если рабочий файл пропал, а копия есть — восстанавливаем;
 *  - если рабочий файл есть, а копии нет — создаём копию.
 */
if ( ! function_exists( 'odgk_persist_sync' ) ) {
	function odgk_persist_sync() {
		if ( ! defined( 'ODGK_DATA_DIR' ) || ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}

		$runtime = rtrim( ODGK_DATA_DIR, '/\\' );
		$backup  = rtrim( WP_CONTENT_DIR, '/\\' ) . '/uploads/odinokov-geo-blocker';
		$files   = array( 'GeoLite2-Country.mmdb', 'exceptions.json', 'block-log.json' );

		foreach ( $files as $file ) {
			$rt = $runtime . '/' . $file;
			$bk = $backup . '/' . $file;

			$rt_exists = @file_exists( $rt );
			$bk_exists = @file_exists( $bk );

			if ( $rt_exists === $bk_exists ) {
				continue;
			}

			if ( $rt_exists && ! $bk_exists ) {
				// Создаём / обновляем резервную копию.
				if ( ! is_dir( $backup ) ) {
					@wp_mkdir_p( $backup );
				}
				if ( is_dir( $backup ) ) {
					if ( ! @file_exists( $backup . '/index.php' ) ) {
						@file_put_contents( $backup . '/index.php', '<?php // Silence is golden.' );
					}
					if ( ! @file_exists( $backup . '/.htaccess' ) ) {
						@file_put_contents( $backup . '/.htaccess', 'Deny from all' );
					}
				}
				@copy( $rt, $bk );
			} elseif ( ! $rt_exists && $bk_exists ) {
				// Восстанавливаем рабочий файл из резервной копии.
				if ( ! is_dir( $runtime ) ) {
					@wp_mkdir_p( $runtime );
				}
				if ( is_dir( $runtime ) ) {
					if ( ! @file_exists( $runtime . '/index.php' ) ) {
						@file_put_contents( $runtime . '/index.php', '<?php // Silence is golden.' );
					}
					if ( ! @file_exists( $runtime . '/.htaccess' ) ) {
						@file_put_contents( $runtime . '/.htaccess', 'Deny from all' );
					}
				}
				@copy( $bk, $rt );
			}
		}
	}
}
