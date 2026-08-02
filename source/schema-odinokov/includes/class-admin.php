<?php
/**
 * Страница настроек плагина: данные Organization.
 *
 * @package Schema_Odinokov
 */

namespace Odinokov\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    const OPTION_KEY = 'schema_odinokov_organization';

    /** @var Admin|null */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media' ] );
    }

    public function add_menu() {
        global $menu;
        $e = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $i ) { if ( isset( $i[2] ) && 'odinokov-plugins' === $i[2] ) { $e = true; break; } }
        }
        if ( ! $e ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', __( 'Schema Odinokov', 'schema-odinokov' ), __( 'Schema', 'schema-odinokov' ), 'manage_options', 'schema-odinokov', [ $this, 'render_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Schema Odinokov</h3>
                <p>JSON-LD разметка Organization, LocalBusiness, Product, Article, BreadcrumbList, FAQ.</p>
            </div>
        </div></div>
        <?php
    }

    public function register_settings() {
        register_setting(
            'schema_odinokov_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize' ],
                'default'           => [
                    'name'        => '',
                    'url'         => '',
                    'description' => '',
                    'telephone'   => '',
                    'email'       => '',
                    'logo_id'     => 0,
                    'street'      => '',
                    'locality'    => '',
                    'region'      => '',
                    'postal_code' => '',
                    'country'     => '',
                    'price_range' => '',
                    'opening'     => [],
                ],
            ]
        );
    }

    /**
     * Подключаем медиа-загрузчик только на нашей странице настроек.
     */
    public function enqueue_media( $hook ) {
        if ( false === strpos( $hook, 'schema-odinokov' ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'schema-odinokov-admin',
            SCHEMA_ODINOKOV_URL . 'assets/admin.js',
            [ 'jquery' ],
            SCHEMA_ODINOKOV_VERSION,
            true
        );
        wp_enqueue_style(
            'schema-odinokov-admin',
            SCHEMA_ODINOKOV_URL . 'assets/admin.css',
            [],
            SCHEMA_ODINOKOV_VERSION
        );
    }

    /**
     * Санитизация.
     */
    public function sanitize( $input ) {
        $output = [
            'name'        => isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '',
            'url'         => isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : '',
            'description' => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
            'telephone'   => isset( $input['telephone'] ) ? sanitize_text_field( $input['telephone'] ) : '',
            'email'       => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
            'logo_id'     => isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0,
            'street'      => isset( $input['street'] ) ? sanitize_text_field( $input['street'] ) : '',
            'locality'    => isset( $input['locality'] ) ? sanitize_text_field( $input['locality'] ) : '',
            'region'      => isset( $input['region'] ) ? sanitize_text_field( $input['region'] ) : '',
            'postal_code' => isset( $input['postal_code'] ) ? sanitize_text_field( $input['postal_code'] ) : '',
            'country'     => isset( $input['country'] ) ? sanitize_text_field( $input['country'] ) : '',
            'price_range' => isset( $input['price_range'] ) ? sanitize_text_field( $input['price_range'] ) : '',
            'opening'     => $this->sanitize_opening_hours( isset( $input['opening'] ) ? (array) $input['opening'] : [] ),
        ];

        return $output;
    }

    /**
     * Санитизация часов работы.
     * Ожидаемая структура: $input['Mo'] = ['opens' => '09:00', 'closes' => '19:00'] и т.д.
     * Возвращает только валидные HH:MM-диапазоны.
     */
    private function sanitize_opening_hours( $input ) {
        $days  = [ 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ];
        $clean = [];

        foreach ( $days as $day ) {
            if ( empty( $input[ $day ]['enabled'] ) ) {
                continue;
            }
            $opens  = isset( $input[ $day ]['opens'] )  ? trim( (string) $input[ $day ]['opens'] )  : '';
            $closes = isset( $input[ $day ]['closes'] ) ? trim( (string) $input[ $day ]['closes'] ) : '';

            if ( ! preg_match( '/^\d{2}:\d{2}$/', $opens ) || ! preg_match( '/^\d{2}:\d{2}$/', $closes ) ) {
                continue;
            }
            if ( $opens === $closes ) {
                continue;
            }

            $clean[ $day ] = [
                'opens'  => $opens,
                'closes' => $closes,
            ];
        }

        return $clean;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $opt = wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'name'        => get_bloginfo( 'name' ),
            'url'         => home_url( '/' ),
            'description' => get_bloginfo( 'description' ),
            'telephone'   => '',
            'email'       => get_option( 'admin_email' ),
            'logo_id'     => 0,
            'street'      => '',
            'locality'    => '',
            'region'      => '',
            'postal_code' => '',
            'country'     => '',
            'price_range' => '',
            'opening'     => [],
        ] );

        $days = [
            'Mo' => __( 'Понедельник', 'schema-odinokov' ),
            'Tu' => __( 'Вторник', 'schema-odinokov' ),
            'We' => __( 'Среда', 'schema-odinokov' ),
            'Th' => __( 'Четверг', 'schema-odinokov' ),
            'Fr' => __( 'Пятница', 'schema-odinokov' ),
            'Sa' => __( 'Суббота', 'schema-odinokov' ),
            'Su' => __( 'Воскресенье', 'schema-odinokov' ),
        ];

        $logo_url = $opt['logo_id'] ? wp_get_attachment_image_url( (int) $opt['logo_id'], 'full' ) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Schema Odinokov — Organization', 'schema-odinokov' ); ?></h1>
            <p><?php esc_html_e( 'Данные используются в JSON-LD разметке Organization на всех страницах сайта.', 'schema-odinokov' ); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields( 'schema_odinokov_group' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-name"><?php esc_html_e( 'Название организации', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-name"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[name]"
                                   value="<?php echo esc_attr( $opt['name'] ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-url"><?php esc_html_e( 'URL сайта', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="url"
                                   id="schema-odinokov-url"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[url]"
                                   value="<?php echo esc_attr( $opt['url'] ); ?>"
                                   class="regular-text"
                                   placeholder="https://example.com" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-description"><?php esc_html_e( 'Описание', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <textarea id="schema-odinokov-description"
                                      name="<?php echo esc_attr( self::OPTION_KEY ); ?>[description]"
                                      rows="3"
                                      class="large-text"><?php echo esc_textarea( $opt['description'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-telephone"><?php esc_html_e( 'Телефон', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-telephone"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telephone]"
                                   value="<?php echo esc_attr( $opt['telephone'] ); ?>"
                                   class="regular-text"
                                   placeholder="+7 (495) 123-45-67" />
                            <p class="description"><?php esc_html_e( 'Рекомендуемый формат: +7 (XXX) XXX-XX-XX', 'schema-odinokov' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-email"><?php esc_html_e( 'Email', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   id="schema-odinokov-email"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email]"
                                   value="<?php echo esc_attr( $opt['email'] ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-logo"><?php esc_html_e( 'Логотип', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <div class="schema-odinokov-logo-wrap">
                                <input type="hidden"
                                       id="schema-odinokov-logo-id"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[logo_id]"
                                       value="<?php echo esc_attr( $opt['logo_id'] ); ?>" />
                                <div class="schema-odinokov-logo-preview" style="margin-bottom:10px;">
                                    <?php if ( $logo_url ) : ?>
                                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:200px;height:auto;display:block;" />
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button" id="schema-odinokov-logo-upload">
                                    <?php esc_html_e( 'Выбрать / заменить', 'schema-odinokov' ); ?>
                                </button>
                                <button type="button" class="button" id="schema-odinokov-logo-remove" <?php echo $opt['logo_id'] ? '' : 'style="display:none;"'; ?>>
                                    <?php esc_html_e( 'Удалить', 'schema-odinokov' ); ?>
                                </button>
                                <p class="description"><?php esc_html_e( 'Рекомендуемый размер: 600×60 px или квадрат 512×512.', 'schema-odinokov' ); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'LocalBusiness', 'schema-odinokov' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Дополнительная JSON-LD разметка LocalBusiness. Выводится отдельным блоком на всех страницах сайта. Если поля не заполнены — блок не выводится.', 'schema-odinokov' ); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row" colspan="2" style="padding-bottom:0;">
                            <strong><?php esc_html_e( 'Адрес', 'schema-odinokov' ); ?></strong>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-street"><?php esc_html_e( 'Улица, дом', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-street"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[street]"
                                   value="<?php echo esc_attr( $opt['street'] ); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( 'ул. Тверская, д. 1', 'schema-odinokov' ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-locality"><?php esc_html_e( 'Город', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-locality"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[locality]"
                                   value="<?php echo esc_attr( $opt['locality'] ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-region"><?php esc_html_e( 'Регион / область', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-region"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[region]"
                                   value="<?php echo esc_attr( $opt['region'] ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-postal"><?php esc_html_e( 'Почтовый индекс', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-postal"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[postal_code]"
                                   value="<?php echo esc_attr( $opt['postal_code'] ); ?>"
                                   class="small-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-country"><?php esc_html_e( 'Страна', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-country"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[country]"
                                   value="<?php echo esc_attr( $opt['country'] ); ?>"
                                   class="regular-text"
                                   placeholder="RU" />
                            <p class="description"><?php esc_html_e( 'Двухбуквенный код ISO 3166-1 alpha-2, например: RU, BY, KZ, US.', 'schema-odinokov' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema-odinokov-price-range"><?php esc_html_e( 'Ценовой диапазон', 'schema-odinokov' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="schema-odinokov-price-range"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[price_range]"
                                   value="<?php echo esc_attr( $opt['price_range'] ); ?>"
                                   class="regular-text"
                                   placeholder="$$" />
                            <p class="description"><?php esc_html_e( 'Например: $, $$, $$$, или 1000-5000 ₽.', 'schema-odinokov' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2" style="padding-bottom:0;">
                            <strong><?php esc_html_e( 'Часы работы', 'schema-odinokov' ); ?></strong>
                            <p class="description"><?php esc_html_e( 'Отметьте дни, в которые вы работаете, и укажите время. Неотмеченные дни не попадут в схему.', 'schema-odinokov' ); ?></p>
                        </th>
                    </tr>
                    <?php foreach ( $days as $code => $label ) :
                        $day = isset( $opt['opening'][ $code ] ) ? $opt['opening'][ $code ] : [];
                        $is_on  = ! empty( $day );
                        $opens  = $is_on ? $day['opens']  : '09:00';
                        $closes = $is_on ? $day['closes'] : '19:00';
                        ?>
                        <tr>
                            <th scope="row">
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::OPTION_KEY ); ?>[opening][<?php echo esc_attr( $code ); ?>][enabled]"
                                           value="1"
                                           <?php checked( $is_on ); ?> />
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="time"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[opening][<?php echo esc_attr( $code ); ?>][opens]"
                                       value="<?php echo esc_attr( $opens ); ?>"
                                       step="900" />
                                —
                                <input type="time"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[opening][<?php echo esc_attr( $code ); ?>][closes]"
                                       value="<?php echo esc_attr( $closes ); ?>"
                                       step="900" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
