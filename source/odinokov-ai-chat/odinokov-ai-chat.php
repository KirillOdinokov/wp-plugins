<?php
/**
 * Plugin Name: Odinokov AI Chat
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Чат-консультант с ИИ (DeepSeek). Отвечает по ГОСТ, СНиП, СП. Шорткод: [odinokov_chat]
 * Version:     1.3.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0+
 * Text Domain: odinokov-ai-chat
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ODINOKOV_AI_VERSION', '1.3.1');
define('ODINOKOV_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ODINOKOV_AI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ODINOKOV_AI_PLUGIN_DIR . 'includes/settings.php';
require_once ODINOKOV_AI_PLUGIN_DIR . 'includes/ajax.php';
require_once ODINOKOV_AI_PLUGIN_DIR . 'includes/logger.php';
require_once ODINOKOV_AI_PLUGIN_DIR . 'includes/updater/updater.php';

new Odinokov_AI_Updater(__FILE__, 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-ai-chat.json');

register_activation_hook(__FILE__, 'odinokov_ai_activate');
register_deactivation_hook(__FILE__, 'odinokov_ai_deactivate');

function odinokov_ai_activate() {
    odinokov_ai_create_logs_table();
}

function odinokov_ai_deactivate() {
    if (function_exists('wp_next_scheduled')) {
        $ts = wp_next_scheduled('odinokov_ai_cleanup_logs');
        if ($ts) wp_unschedule_event($ts, 'odinokov_ai_cleanup_logs');
        wp_clear_scheduled_hook('odinokov_ai_cleanup_logs');
    }
}

function odinokov_ai_generate_captcha() {
    $a = wp_rand(1, 10);
    $b = wp_rand(1, 10);
    $answer = $a + $b;
    $hash = wp_hash($answer . 'odinokov_captcha_salt');
    return [
        'question' => "Сколько будет {$a} + {$b}?",
        'hash'     => $hash,
    ];
}

function odinokov_ai_verify_captcha($user_answer, $hash) {
    for ($a = 1; $a <= 10; $a++) {
        for ($b = 1; $b <= 10; $b++) {
            if (wp_hash(($a + $b) . 'odinokov_captcha_salt') === $hash) {
                return (int) $user_answer === ($a + $b);
            }
        }
    }
    return false;
}

function odinokov_ai_enqueue_assets() {
    wp_enqueue_style(
        'odinokov-ai-chat',
        ODINOKOV_AI_PLUGIN_URL . 'assets/css/chat.css',
        [],
        ODINOKOV_AI_VERSION
    );
    wp_enqueue_script(
        'odinokov-ai-chat',
        ODINOKOV_AI_PLUGIN_URL . 'assets/js/chat.js',
        [],
        ODINOKOV_AI_VERSION,
        true
    );

    $chat_title   = get_option('odinokov_ai_chat_title', 'Задайте вопрос — отвечу по ГОСТ, СНиП и СП');
    $placeholder  = get_option('odinokov_ai_placeholder', 'Введите ваш вопрос...');
    $human_msg    = get_option('odinokov_ai_human_message', 'К сожалению, этот функционал пока дорабатывается. Планируем доделать в течение пары недель. Пока предлагаем Вам позвонить нам, написать на email или обратиться через другие каналы связи.');
    $suggestions  = get_option('odinokov_ai_suggestions', '');
    $suggestions_arr = [];
    if (!empty($suggestions)) {
        $suggestions_arr = array_values(array_filter(array_map('trim', explode("\n", $suggestions))));
    }
    if (empty($suggestions_arr)) {
        $suggestions_arr = [
            'Какие ГОСТы применяются к крепежу?',
            'Расчёт нагрузки на анкер по СП',
            'Подбор дюбеля для пустотелого кирпича',
            'Химический или механический анкер — что выбрать?',
        ];
    }

    wp_localize_script('odinokov-ai-chat', 'odinokovAi', [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('odinokov_ai_chat_nonce'),
        'captcha'      => odinokov_ai_generate_captcha(),
        'chatTitle'    => $chat_title,
        'placeholder'  => $placeholder,
        'humanMessage' => $human_msg,
        'suggestions'  => $suggestions_arr,
    ]);
}
add_action('wp_enqueue_scripts', 'odinokov_ai_enqueue_assets');

function odinokov_ai_custom_css() {
    $primary     = get_option('odinokov_ai_primary_color', '#e94560');
    $header_bg   = get_option('odinokov_ai_header_bg', '#1a1a2e');
    $user_bg     = get_option('odinokov_ai_user_bubble_bg', '#1a1a2e');
    $asst_bg     = get_option('odinokov_ai_assistant_bubble_bg', '#eef0f4');
    $trigger_clr = get_option('odinokov_ai_trigger_color', '#e94560');
    $trigger_size = (int) get_option('odinokov_ai_trigger_size', 60);
    $font        = get_option('odinokov_ai_font_family', 'inherit');
    $font_css    = ($font === 'inherit') ? 'inherit' : ($font === 'system' ? "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif" : $font);
    ?>
    <style id="odinokov-ai-dynamic">
        :root {
            --odinokov-primary: <?php echo esc_attr($primary); ?>;
            --odinokov-header-bg: <?php echo esc_attr($header_bg); ?>;
            --odinokov-user-bg: <?php echo esc_attr($user_bg); ?>;
            --odinokov-assistant-bg: <?php echo esc_attr($asst_bg); ?>;
            --odinokov-trigger: <?php echo esc_attr($trigger_clr); ?>;
            --odinokov-font: <?php echo esc_attr($font_css); ?>;
            --odinokov-trigger-size: <?php echo $trigger_size; ?>px;
        }
    </style>
    <?php
}
add_action('wp_head', 'odinokov_ai_custom_css');

function odinokov_ai_render_panel() {
    $icon_ai_id    = get_option('odinokov_ai_icon_ai', 0);
    $icon_human_id = get_option('odinokov_ai_icon_human', 0);
    $icon_trigger_id = get_option('odinokov_ai_icon_trigger', 0);
    $icon_ai_url   = $icon_ai_id ? wp_get_attachment_image_url($icon_ai_id, 'thumbnail') : '';
    $icon_human_url = $icon_human_id ? wp_get_attachment_image_url($icon_human_id, 'thumbnail') : '';
    $icon_trigger_url = $icon_trigger_id ? wp_get_attachment_image_url($icon_trigger_id, 'medium') : '';

    $panel_title   = get_option('odinokov_ai_panel_title', 'Ваш Персональный инженер проектировщик');
    $panel_badge   = get_option('odinokov_ai_panel_badge', 'Эксперт по крепежу');
    $speech_ai     = get_option('odinokov_ai_speech_ai', 'Привет! Я ИИ, я знаю всю техническую базу по материалам на сайте, а также нормативы и ГОСТы. Я знаю почти всё!');
    $speech_human  = get_option('odinokov_ai_speech_human', 'Привет! Я живой сотрудник компании. Я не так хорошо знаю всё, но могу ответить по коммерческим вопросам и наличию лучше ИИ.');
    $placeholder   = get_option('odinokov_ai_placeholder', 'Введите ваш вопрос...');

    $ai_icon_html = $icon_ai_url
        ? '<img src="' . esc_url($icon_ai_url) . '" width="22" height="22" style="border-radius:50%;object-fit:cover;" alt="ИИ" />'
        : '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4v1h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2V7a4 4 0 0 1 4-4z"/><circle cx="9" cy="13" r="1" fill="currentColor"/><circle cx="15" cy="13" r="1" fill="currentColor"/><path d="M9 17c.83 1.17 2.17 2 3.5 2s2.67-.83 3.5-2"/></svg>';

    $human_icon_html = $icon_human_url
        ? '<img src="' . esc_url($icon_human_url) . '" width="22" height="22" style="border-radius:50%;object-fit:cover;" alt="Оператор" />'
        : '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

    $trigger_icon_html = $icon_trigger_url
        ? '<img src="' . esc_url($icon_trigger_url) . '" class="odinokov-ai-trigger-icon" alt="Чат" />'
        : '<svg class="odinokov-ai-trigger-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    ?>
    <div class="odinokov-ai-overlay" id="odinokov-ai-overlay" aria-hidden="true"></div>

    <div class="odinokov-ai-trigger-group" id="odinokov-ai-trigger-group">
        <div class="odinokov-ai-sub-buttons" id="odinokov-ai-sub-buttons">
            <div class="odinokov-ai-sub-item">
                <div class="odinokov-ai-speech-bubble odinokov-ai-speech-bubble--ai">
                    <?php echo esc_html($speech_ai); ?>
                </div>
                <button
                    type="button"
                    class="odinokov-ai-sub-btn odinokov-ai-sub-btn--ai"
                    id="odinokov-ai-sub-ai"
                    aria-label="Чат с ИИ"
                    title="Чат с ИИ"
                >
                    <?php echo $ai_icon_html; ?>
                </button>
            </div>
            <div class="odinokov-ai-sub-item">
                <div class="odinokov-ai-speech-bubble odinokov-ai-speech-bubble--human">
                    <?php echo esc_html($speech_human); ?>
                </div>
                <button
                    type="button"
                    class="odinokov-ai-sub-btn odinokov-ai-sub-btn--human"
                    id="odinokov-ai-sub-human"
                    aria-label="Чат с оператором"
                    title="Чат с оператором"
                >
                    <?php echo $human_icon_html; ?>
                </button>
            </div>
        </div>

        <button
            type="button"
            class="odinokov-ai-trigger"
            id="odinokov-ai-trigger"
            aria-label="Открыть чат"
            title="Задать вопрос"
        >
            <?php echo $trigger_icon_html; ?>
            <svg class="odinokov-ai-trigger-close" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <div class="odinokov-ai-panel" id="odinokov-ai-panel" aria-hidden="true">
        <div class="odinokov-ai-panel-header">
            <span class="odinokov-ai-panel-title"><?php echo esc_html($panel_title); ?></span>
            <span class="odinokov-ai-panel-badge"><?php echo esc_html($panel_badge); ?></span>
        </div>
        <div class="odinokov-ai-tabs" id="odinokov-ai-tabs">
            <button type="button" class="odinokov-ai-tab odinokov-ai-tab--active" data-mode="ai">ИИ</button>
            <button type="button" class="odinokov-ai-tab" data-mode="human">Человек</button>
        </div>

        <div class="odinokov-ai-messages" id="odinokov-ai-messages">
            <div class="odinokov-ai-captcha" id="odinokov-ai-captcha">
                <p class="odinokov-ai-captcha-title">Подтвердите, что вы не робот</p>
                <p class="odinokov-ai-captcha-question" id="odinokov-ai-captcha-question"></p>
                <div class="odinokov-ai-captcha-row">
                    <input
                        type="number"
                        id="odinokov-ai-captcha-input"
                        placeholder="Ответ цифрой"
                        autocomplete="off"
                        inputmode="numeric"
                    />
                    <button type="button" id="odinokov-ai-captcha-btn">Проверить</button>
                </div>
                <p class="odinokov-ai-captcha-error" id="odinokov-ai-captcha-error"></p>
            </div>
            <div class="odinokov-ai-welcome" id="odinokov-ai-welcome" style="display:none">
                <p class="odinokov-ai-welcome-text" id="odinokov-ai-welcome-text"><?php echo esc_html($chat_title); ?></p>
                <div class="odinokov-ai-suggestions" id="odinokov-ai-suggestions"></div>
            </div>
        </div>

        <div class="odinokov-ai-input-area">
            <input
                type="text"
                id="odinokov-ai-input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                autocomplete="off"
            />
            <button type="button" id="odinokov-ai-send" aria-label="Отправить">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'odinokov_ai_render_panel');

function odinokov_ai_shortcode($atts) {
    return '<!-- Odinokov AI Chat: панель автоматически добавлена в футер -->';
}
add_shortcode('odinokov_chat', 'odinokov_ai_shortcode');
