<?php
if (!defined('ABSPATH')) {
    exit;
}

function odinokov_ai_admin_menu() {
    global $menu;
    $exists = false;
    if (is_array($menu)) {
        foreach ($menu as $item) {
            if (isset($item[2]) && 'odinokov-plugins' === $item[2]) {
                $exists = true;
                break;
            }
        }
    }
    if (!$exists) {
        add_menu_page(
            'Одиноков',
            'Одиноков',
            'manage_options',
            'odinokov-plugins',
            'odinokov_ai_dashboard',
            'dashicons-admin-settings',
            30
        );
    }

    add_submenu_page(
        'odinokov-plugins',
        'Odinokov AI Chat — Настройки',
        'AI Chat',
        'manage_options',
        'odinokov-ai-chat',
        'odinokov_ai_settings_html'
    );
}
add_action('admin_menu', 'odinokov_ai_admin_menu');

function odinokov_ai_dashboard() {
    ?>
    <div class="wrap">
        <h1>Плагины Одиноков</h1>
        <p>Список установленных плагинов от Одиноков для управления.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov AI Chat</h3>
                <p>Чат-консультант с ИИ (DeepSeek). Отвечает по ГОСТ, СНиП, СП.</p>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=odinokov-ai-chat')); ?>" class="button">Настроить</a></p>
            </div>
        </div>
    </div>
    <?php
}

function odinokov_ai_register_settings() {
    register_setting('odinokov_ai_options', 'odinokov_ai_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    register_setting('odinokov_ai_options', 'odinokov_ai_model', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'deepseek-v4-pro']);
    register_setting('odinokov_ai_options', 'odinokov_ai_temperature', ['type' => 'float', 'sanitize_callback' => function($v) { return max(0, min(2, (float)$v)); }, 'default' => 0.3]);
    register_setting('odinokov_ai_options', 'odinokov_ai_max_tokens', ['type' => 'int', 'sanitize_callback' => 'absint', 'default' => 2048]);
    register_setting('odinokov_ai_options', 'odinokov_ai_system_prompt', ['type' => 'string', 'sanitize_callback' => 'wp_kses_post']);
    register_setting('odinokov_ai_options', 'odinokov_ai_knowledge_areas', ['type' => 'string', 'sanitize_callback' => 'wp_kses_post']);
    register_setting('odinokov_ai_options', 'odinokov_ai_categories', ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field']);
    register_setting('odinokov_ai_options', 'odinokov_ai_notify_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option('admin_email')]);
    register_setting('odinokov_ai_options', 'odinokov_ai_notify_enabled', ['type' => 'bool', 'sanitize_callback' => function($v) { return (bool) $v; }, 'default' => true]);

    register_setting('odinokov_ai_options', 'odinokov_ai_primary_color', ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#e94560']);
    register_setting('odinokov_ai_options', 'odinokov_ai_header_bg', ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#1a1a2e']);
    register_setting('odinokov_ai_options', 'odinokov_ai_user_bubble_bg', ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#1a1a2e']);
    register_setting('odinokov_ai_options', 'odinokov_ai_assistant_bubble_bg', ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#eef0f4']);
    register_setting('odinokov_ai_options', 'odinokov_ai_trigger_color', ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#e94560']);
    register_setting('odinokov_ai_options', 'odinokov_ai_font_family', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'inherit']);

    register_setting('odinokov_ai_options', 'odinokov_ai_icon_ai', ['type' => 'int', 'sanitize_callback' => 'absint', 'default' => 0]);
    register_setting('odinokov_ai_options', 'odinokov_ai_icon_human', ['type' => 'int', 'sanitize_callback' => 'absint', 'default' => 0]);
    register_setting('odinokov_ai_options', 'odinokov_ai_icon_trigger', ['type' => 'int', 'sanitize_callback' => 'absint', 'default' => 0]);
    register_setting('odinokov_ai_options', 'odinokov_ai_trigger_size', ['type' => 'int', 'sanitize_callback' => function($v) { return max(40, min(120, (int)$v)); }, 'default' => 60]);

    register_setting('odinokov_ai_options', 'odinokov_ai_panel_title', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Ваш Персональный инженер проектировщик']);
    register_setting('odinokov_ai_options', 'odinokov_ai_panel_badge', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Эксперт по крепежу']);
    register_setting('odinokov_ai_options', 'odinokov_ai_speech_ai', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Привет! Я ИИ, я знаю всю техническую базу по материалам на сайте, а также нормативы и ГОСТы. Я знаю почти всё!']);
    register_setting('odinokov_ai_options', 'odinokov_ai_speech_human', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Привет! Я живой сотрудник компании. Я не так хорошо знаю всё, но могу ответить по коммерческим вопросам и наличию лучше ИИ.']);
    register_setting('odinokov_ai_options', 'odinokov_ai_chat_title', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Задайте вопрос — отвечу по ГОСТ, СНиП и СП']);
    register_setting('odinokov_ai_options', 'odinokov_ai_placeholder', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Введите ваш вопрос...']);
    register_setting('odinokov_ai_options', 'odinokov_ai_human_message', ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => 'К сожалению, этот функционал пока дорабатывается. Планируем доделать в течение пары недель. Пока предлагаем Вам позвонить нам, написать на email или обратиться через другие каналы связи.']);
    register_setting('odinokov_ai_options', 'odinokov_ai_suggestions', ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field']);
    register_setting('odinokov_ai_options', 'odinokov_ai_log_retention_days', ['type' => 'int', 'sanitize_callback' => function($v) { return max(1, min(365, (int)$v)); }, 'default' => 30]);
}
add_action('admin_init', 'odinokov_ai_register_settings');

function odinokov_ai_default_system_prompt() {
    return <<<EOT
Ты — эксперт-консультант по крепежу и метизам. Твоя задача — помогать пользователям с вопросами о крепежных изделиях, их применении, подборе и нормативных требованиях. Если спросят - кто ты, отвечай - Я ИИ консультант, в меня загружены все действующие ГОСТы, СНиПы, СП и каталоги производителей крепежа, на основе которых я отвечаю. Если я не смогу ответить или не знаю точно, так и скажу. Ты отвечаешь от имени торговой компании ФАСТЕН ТРЕЙД, на сайте fasten-trade.ru.

ПРАВИЛА ОТВЕТОВ:
1. Отвечай строго на русском языке.
2. При ответе опирайся на действующие стандарты: ГОСТ, СНиП, СП, ISO, DIN.
3. Всегда указывай конкретные номера пунктов стандартов, на которые ссылаешься.
4. Если точного стандарта не знаешь — честно скажи об этом и предложи, где искать.
5. Отвечай по делу, без воды. Структурируй ответ: сначала краткий вывод, затем детали.
6. Если вопрос касается безопасности или несущих конструкций — обязательно добавь предупреждение о необходимости проверки расчётов специалистом.
7. Уточняй у пользователя детали, если вопрос слишком общий (нагрузки, материал основания, условия эксплуатации).
8. На вопросы по наличию или стоимости (товара или доставки) отвечай - Я не располагаю данными о складских остатках, так как я ИИ-консультант по крепежу и метизам. Моя задача — помогать с подбором крепежа, расчётами, ссылками на ГОСТ, СНиП, СП и каталоги производителей с технической точки зрения. Если вы уточните, какой именно крепёж вас интересует (тип, размер, стандарт) и для каких целей, я помогу правильно его выбрать, после чего Вы можете сформировать заявку и отправить на электронную почту info@fasten-trade.ru или скажите мне, я отправлю заявку сам.

ФОРМАТ ОТВЕТА (сами заголовки **Краткий ответ** и **Нормативная база** не выводи):
**Краткий ответ:** [1-2 предложения]
**Нормативная база:** [перечень ГОСТ/СНиП/СП с пунктами]
**Подробности:** [развёрнутое пояснение]
**Рекомендация:** [что делать пользователю]
EOT;
}

function odinokov_ai_default_knowledge_areas() {
    return <<<EOT
ОБЛАСТИ ЗНАНИЙ:
- Анкерный крепёж (ГОСТ Р 56731, ГОСТ 24379.0, СП 70.13330)
- Болтовые соединения (ГОСТ 1759.0, ГОСТ 7798, ГОСТ 7805, СП 16.13330)
- Саморезы и шурупы (ГОСТ 1145, ГОСТ 11652)
- Дюбели (ГОСТ 26998, ГОСТ 27320)
- Заклёпки (ГОСТ 10299, ГОСТ 10300)
- Сварные соединения (СП 70.13330, ГОСТ 5264)
- Кровельный крепёж (СП 17.13330)
- Фасадный крепёж (СП 70.13330, ГОСТ Р 56731)
- Нагрузки и расчёты (СП 20.13330, СП 16.13330)
- Коррозионная стойкость (СП 28.13330)
EOT;
}

function odinokov_ai_settings_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    $default_prompt  = odinokov_ai_default_system_prompt();
    $default_areas   = odinokov_ai_default_knowledge_areas();
    $icon_ai_id      = get_option('odinokov_ai_icon_ai', 0);
    $icon_human_id   = get_option('odinokov_ai_icon_human', 0);
    $icon_trigger_id = get_option('odinokov_ai_icon_trigger', 0);
    $icon_ai_url     = $icon_ai_id ? wp_get_attachment_image_url($icon_ai_id, 'thumbnail') : '';
    $icon_human_url  = $icon_human_id ? wp_get_attachment_image_url($icon_human_id, 'thumbnail') : '';
    $icon_trigger_url = $icon_trigger_id ? wp_get_attachment_image_url($icon_trigger_id, 'thumbnail') : '';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post" enctype="multipart/form-data">
            <?php settings_fields('odinokov_ai_options'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="odinokov_ai_api_key">DeepSeek API Key</label></th>
                    <td>
                        <input type="password" id="odinokov_ai_api_key" name="odinokov_ai_api_key" value="<?php echo esc_attr(get_option('odinokov_ai_api_key', '')); ?>" class="regular-text" autocomplete="off" />
                        <p class="description">Ключ API DeepSeek. Получить: <a href="https://platform.deepseek.com/api_keys" target="_blank">platform.deepseek.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_model">Модель</label></th>
                    <td>
                        <select id="odinokov_ai_model" name="odinokov_ai_model">
                            <option value="deepseek-v4-pro" <?php selected(get_option('odinokov_ai_model', 'deepseek-v4-pro'), 'deepseek-v4-pro'); ?>>DeepSeek V4 Pro</option>
                            <option value="deepseek-v4-flash" <?php selected(get_option('odinokov_ai_model', 'deepseek-v4-pro'), 'deepseek-v4-flash'); ?>>DeepSeek V4 Flash</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_temperature">Temperature</label></th>
                    <td><input type="number" id="odinokov_ai_temperature" name="odinokov_ai_temperature" value="<?php echo esc_attr(get_option('odinokov_ai_temperature', '0.3')); ?>" step="0.1" min="0" max="2" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_max_tokens">Max Tokens</label></th>
                    <td><input type="number" id="odinokov_ai_max_tokens" name="odinokov_ai_max_tokens" value="<?php echo esc_attr(get_option('odinokov_ai_max_tokens', '2048')); ?>" step="100" min="100" max="8192" class="small-text" /></td>
                </tr>
            </table>

            <h2 class="title">Тексты и заголовки</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="odinokov_ai_panel_title">Заголовок панели чата</label></th>
                    <td><input type="text" id="odinokov_ai_panel_title" name="odinokov_ai_panel_title" value="<?php echo esc_attr(get_option('odinokov_ai_panel_title', 'Ваш Персональный инженер проектировщик')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_panel_badge">Бейдж панели</label></th>
                    <td><input type="text" id="odinokov_ai_panel_badge" name="odinokov_ai_panel_badge" value="<?php echo esc_attr(get_option('odinokov_ai_panel_badge', 'Эксперт по крепежу')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_chat_title">Заголовок приветствия</label></th>
                    <td><input type="text" id="odinokov_ai_chat_title" name="odinokov_ai_chat_title" value="<?php echo esc_attr(get_option('odinokov_ai_chat_title', 'Задайте вопрос — отвечу по ГОСТ, СНиП и СП')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_placeholder">Placeholder поля ввода</label></th>
                    <td><input type="text" id="odinokov_ai_placeholder" name="odinokov_ai_placeholder" value="<?php echo esc_attr(get_option('odinokov_ai_placeholder', 'Введите ваш вопрос...')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_speech_ai">Текст бабла ИИ</label></th>
                    <td><input type="text" id="odinokov_ai_speech_ai" name="odinokov_ai_speech_ai" value="<?php echo esc_attr(get_option('odinokov_ai_speech_ai', 'Привет! Я ИИ, я знаю всю техническую базу по материалам на сайте, а также нормативы и ГОСТы. Я знаю почти всё!')); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_speech_human">Текст бабла Оператор</label></th>
                    <td><input type="text" id="odinokov_ai_speech_human" name="odinokov_ai_speech_human" value="<?php echo esc_attr(get_option('odinokov_ai_speech_human', 'Привет! Я живой сотрудник компании. Я не так хорошо знаю всё, но могу ответить по коммерческим вопросам и наличию лучше ИИ.')); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_human_message">Сообщение при выборе оператора</label></th>
                    <td>
                        <textarea id="odinokov_ai_human_message" name="odinokov_ai_human_message" rows="4" class="large-text"><?php echo esc_textarea(get_option('odinokov_ai_human_message', 'К сожалению, этот функционал пока дорабатывается. Планируем доделать в течение пары недель. Пока предлагаем Вам позвонить нам, написать на email или обратиться через другие каналы связи.')); ?></textarea>
                        <p class="description">Показывается, когда пользователь выбирает чат с живым оператором.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Системный промпт</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="odinokov_ai_system_prompt">Основная инструкция</label></th>
                    <td>
                        <textarea id="odinokov_ai_system_prompt" name="odinokov_ai_system_prompt" rows="18" class="large-text code"><?php echo esc_textarea(get_option('odinokov_ai_system_prompt', $default_prompt)); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_categories">Категории сайта</label></th>
                    <td>
                        <textarea id="odinokov_ai_categories" name="odinokov_ai_categories" rows="5" class="large-text" placeholder="Введите категории товаров/материалов через запятую или с новой строки."><?php echo esc_textarea(get_option('odinokov_ai_categories', '')); ?></textarea>
                        <p class="description">На основе категорий DeepSeek сгенерирует области знаний и популярные вопросы.</p>
                        <button type="button" class="button" id="odinokov-ai-generate-areas">Сгенерировать области знаний</button>
                        <button type="button" class="button" id="odinokov-ai-generate-suggestions" style="margin-left:6px;">Сгенерировать популярные вопросы</button>
                        <span id="odinokov-ai-generate-spinner" class="spinner" style="float:none;margin-top:0;"></span>
                        <p id="odinokov-ai-generate-msg" style="color:#2271b1;margin-top:6px;"></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_knowledge_areas">Области знаний</label></th>
                    <td>
                        <textarea id="odinokov_ai_knowledge_areas" name="odinokov_ai_knowledge_areas" rows="12" class="large-text code"><?php echo esc_textarea(get_option('odinokov_ai_knowledge_areas', $default_areas)); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_suggestions">Популярные вопросы</label></th>
                    <td>
                        <textarea id="odinokov_ai_suggestions" name="odinokov_ai_suggestions" rows="6" class="large-text" placeholder="По одному вопросу на строку. Отображаются в виджете чата как подсказки."><?php echo esc_textarea(get_option('odinokov_ai_suggestions', '')); ?></textarea>
                        <p class="description">По одному вопросу на строку. Если не заполнено — используются вопросы по умолчанию.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Иконки чата</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Иконка кнопки чата</label></th>
                    <td>
                        <div class="odinokov-ai-icon-upload">
                            <div class="odinokov-ai-icon-preview" id="odinokov-ai-icon-trigger-preview">
                                <?php if ($icon_trigger_url): ?>
                                    <img src="<?php echo esc_url($icon_trigger_url); ?>" style="max-width:80px;max-height:80px;border-radius:50%;" />
                                <?php else: ?>
                                    <div class="odinokov-ai-icon-placeholder" style="width:80px;height:80px;border-radius:50%;background:var(--odinokov-primary, #e94560);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;">&#128172;</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="odinokov_ai_icon_trigger" name="odinokov_ai_icon_trigger" value="<?php echo esc_attr($icon_trigger_id); ?>" />
                            <button type="button" class="button odinokov-ai-upload-btn" data-target="odinokov_ai_icon_trigger" data-preview="odinokov-ai-icon-trigger-preview">Загрузить иконку</button>
                            <?php if ($icon_trigger_id): ?>
                                <button type="button" class="button odinokov-ai-remove-btn" data-target="odinokov_ai_icon_trigger" data-preview="odinokov-ai-icon-trigger-preview">Удалить</button>
                            <?php endif; ?>
                        </div>
                        <p class="description">Основная иконка плавающей кнопки чата. Рекомендуемый размер: 200×200 px.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Иконка ИИ</label></th>
                    <td>
                        <div class="odinokov-ai-icon-upload">
                            <div class="odinokov-ai-icon-preview" id="odinokov-ai-icon-ai-preview">
                                <?php if ($icon_ai_url): ?>
                                    <img src="<?php echo esc_url($icon_ai_url); ?>" style="max-width:80px;max-height:80px;border-radius:50%;" />
                                <?php else: ?>
                                    <div class="odinokov-ai-icon-placeholder" style="width:80px;height:80px;border-radius:50%;background:var(--odinokov-primary, #e94560);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;">&#9881;</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="odinokov_ai_icon_ai" name="odinokov_ai_icon_ai" value="<?php echo esc_attr($icon_ai_id); ?>" />
                            <button type="button" class="button odinokov-ai-upload-btn" data-target="odinokov_ai_icon_ai" data-preview="odinokov-ai-icon-ai-preview">Загрузить иконку</button>
                            <?php if ($icon_ai_id): ?>
                                <button type="button" class="button odinokov-ai-remove-btn" data-target="odinokov_ai_icon_ai" data-preview="odinokov-ai-icon-ai-preview">Удалить</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Иконка оператора</label></th>
                    <td>
                        <div class="odinokov-ai-icon-upload">
                            <div class="odinokov-ai-icon-preview" id="odinokov-ai-icon-human-preview">
                                <?php if ($icon_human_url): ?>
                                    <img src="<?php echo esc_url($icon_human_url); ?>" style="max-width:80px;max-height:80px;border-radius:50%;" />
                                <?php else: ?>
                                    <div class="odinokov-ai-icon-placeholder" style="width:80px;height:80px;border-radius:50%;background:#4a90d9;color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;">&#128100;</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="odinokov_ai_icon_human" name="odinokov_ai_icon_human" value="<?php echo esc_attr($icon_human_id); ?>" />
                            <button type="button" class="button odinokov-ai-upload-btn" data-target="odinokov_ai_icon_human" data-preview="odinokov-ai-icon-human-preview">Загрузить иконку</button>
                            <?php if ($icon_human_id): ?>
                                <button type="button" class="button odinokov-ai-remove-btn" data-target="odinokov_ai_icon_human" data-preview="odinokov-ai-icon-human-preview">Удалить</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>

            <h2 class="title">Кастомизация внешнего вида</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="odinokov_ai_trigger_size">Размер кнопки чата (px)</label></th>
                    <td><input type="number" id="odinokov_ai_trigger_size" name="odinokov_ai_trigger_size" value="<?php echo esc_attr(get_option('odinokov_ai_trigger_size', 60)); ?>" min="40" max="120" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_trigger_color">Цвет кнопки чата</label></th>
                    <td><input type="text" id="odinokov_ai_trigger_color" name="odinokov_ai_trigger_color" value="<?php echo esc_attr(get_option('odinokov_ai_trigger_color', '#e94560')); ?>" class="odinokov-ai-color-picker" data-default-color="#e94560" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_primary_color">Акцентный цвет</label></th>
                    <td><input type="text" id="odinokov_ai_primary_color" name="odinokov_ai_primary_color" value="<?php echo esc_attr(get_option('odinokov_ai_primary_color', '#e94560')); ?>" class="odinokov-ai-color-picker" data-default-color="#e94560" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_header_bg">Фон шапки панели</label></th>
                    <td><input type="text" id="odinokov_ai_header_bg" name="odinokov_ai_header_bg" value="<?php echo esc_attr(get_option('odinokov_ai_header_bg', '#1a1a2e')); ?>" class="odinokov-ai-color-picker" data-default-color="#1a1a2e" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_user_bubble_bg">Фон сообщений пользователя</label></th>
                    <td><input type="text" id="odinokov_ai_user_bubble_bg" name="odinokov_ai_user_bubble_bg" value="<?php echo esc_attr(get_option('odinokov_ai_user_bubble_bg', '#1a1a2e')); ?>" class="odinokov-ai-color-picker" data-default-color="#1a1a2e" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_assistant_bubble_bg">Фон сообщений ИИ</label></th>
                    <td><input type="text" id="odinokov_ai_assistant_bubble_bg" name="odinokov_ai_assistant_bubble_bg" value="<?php echo esc_attr(get_option('odinokov_ai_assistant_bubble_bg', '#eef0f4')); ?>" class="odinokov-ai-color-picker" data-default-color="#eef0f4" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_font_family">Шрифт</label></th>
                    <td>
                        <select id="odinokov_ai_font_family" name="odinokov_ai_font_family">
                            <option value="inherit" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), 'inherit'); ?>>Наследовать от темы</option>
                            <option value="system" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), 'system'); ?>>Системный</option>
                            <option value="Arial, sans-serif" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), 'Arial, sans-serif'); ?>>Arial</option>
                            <option value="'Helvetica Neue', Helvetica, sans-serif" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), "'Helvetica Neue', Helvetica, sans-serif"); ?>>Helvetica Neue</option>
                            <option value="'Open Sans', sans-serif" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), "'Open Sans', sans-serif"); ?>>Open Sans</option>
                            <option value="'Roboto', sans-serif" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), "'Roboto', sans-serif"); ?>>Roboto</option>
                            <option value="'Montserrat', sans-serif" <?php selected(get_option('odinokov_ai_font_family', 'inherit'), "'Montserrat', sans-serif"); ?>>Montserrat</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2 class="title">База знаний</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Загрузить документы</label></th>
                    <td>
                        <input type="file" id="odinokov-ai-kb-upload" multiple accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.csv,.jpeg,.jpg,.png" style="display:none;" />
                        <button type="button" class="button button-primary" id="odinokov-ai-kb-upload-btn">Выбрать файлы</button>
                        <span id="odinokov-ai-kb-spinner" class="spinner" style="float:none;margin-top:0;"></span>
                        <p class="description">Максимальный размер файла — <?php echo esc_html(size_format(wp_max_upload_size())); ?>.</p>
                    </td>
                </tr>
            </table>

            <h3>Загруженные документы</h3>
            <table class="wp-list-table widefat fixed striped" id="odinokov-ai-kb-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th><th>Название</th><th>Тип</th><th>Размер</th><th>Дата</th><th style="width:80px;">Действия</th>
                    </tr>
                </thead>
                <tbody id="odinokov-ai-kb-list">
                    <?php
                    $kb_files = get_option('odinokov_ai_kb_files', []);
                    if (empty($kb_files)):
                    ?>
                        <tr id="odinokov-ai-kb-empty"><td colspan="6" style="text-align:center;padding:20px;color:#888;">Нет загруженных документов</td></tr>
                    <?php else:
                        foreach ($kb_files as $file):
                            $attach = get_post($file['attachment_id']);
                            if (!$attach) continue;
                            $icon = wp_mime_type_icon($file['attachment_id']);
                    ?>
                        <tr data-file-id="<?php echo esc_attr($file['attachment_id']); ?>">
                            <td><img src="<?php echo esc_url($icon); ?>" width="24" height="24" /></td>
                            <td><a href="<?php echo esc_url(wp_get_attachment_url($file['attachment_id'])); ?>" target="_blank"><?php echo esc_html($attach->post_title); ?></a></td>
                            <td><?php echo esc_html($attach->post_mime_type); ?></td>
                            <td><?php echo esc_html(size_format($file['size'] ?? 0)); ?></td>
                            <td><?php echo esc_html($file['uploaded_at'] ?? ''); ?></td>
                            <td><button type="button" class="button button-small odinokov-ai-kb-delete" data-file-id="<?php echo esc_attr($file['attachment_id']); ?>">Удалить</button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 class="title">Уведомления и логи</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="odinokov_ai_notify_enabled">Отправка транскрипта</label></th>
                    <td><label><input type="checkbox" id="odinokov_ai_notify_enabled" name="odinokov_ai_notify_enabled" value="1" <?php checked(get_option('odinokov_ai_notify_enabled', true)); ?> /> Отправлять транскрипт диалога на почту при завершении чата</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_notify_email">Email для уведомлений</label></th>
                    <td><input type="email" id="odinokov_ai_notify_email" name="odinokov_ai_notify_email" value="<?php echo esc_attr(get_option('odinokov_ai_notify_email', get_option('admin_email'))); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="odinokov_ai_log_retention_days">Хранить логи (дней)</label></th>
                    <td>
                        <input type="number" id="odinokov_ai_log_retention_days" name="odinokov_ai_log_retention_days" value="<?php echo esc_attr(get_option('odinokov_ai_log_retention_days', 30)); ?>" min="1" max="365" class="small-text" />
                        <p class="description">Логи старше указанного срока автоматически удаляются раз в сутки.</p>
                        <button type="button" class="button" id="odinokov-ai-clear-logs" style="margin-top:6px;">Очистить все логи сейчас</button>
                        <span id="odinokov-ai-clear-spinner" class="spinner" style="float:none;margin-top:0;"></span>
                        <p id="odinokov-ai-clear-msg" style="margin-top:4px;"></p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Сохранить настройки'); ?>
        </form>

        <script>
        jQuery(document).ready(function($) {
            $('.odinokov-ai-color-picker').wpColorPicker();

            var mediaFrame;
            $('.odinokov-ai-upload-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
                if (mediaFrame) mediaFrame.open();
                else {
                    mediaFrame = wp.media({ title: 'Выберите изображение', button: { text: 'Использовать' }, library: { type: 'image' }, multiple: false });
                    mediaFrame.on('select', function() {
                        var a = mediaFrame.state().get('selection').first().toJSON();
                        $('#' + target).val(a.id);
                        $('#' + preview).html('<img src="' + (a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url) + '" style="max-width:80px;max-height:80px;border-radius:50%;" />');
                        if (!btn.siblings('.odinokov-ai-remove-btn').length) btn.after(' <button type="button" class="button odinokov-ai-remove-btn" data-target="' + target + '" data-preview="' + preview + '">Удалить</button>');
                    });
                    mediaFrame.open();
                }
            });

            $(document).on('click', '.odinokov-ai-remove-btn', function(e) {
                e.preventDefault();
                var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
                $('#' + target).val('0');
                $('#' + preview).html('<div class="odinokov-ai-icon-placeholder" style="width:80px;height:80px;border-radius:50%;background:#ccc;color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;">?</div>');
                btn.remove();
            });

            $('#odinokov-ai-kb-upload-btn').on('click', function() { $('#odinokov-ai-kb-upload').click(); });

            $('#odinokov-ai-kb-upload').on('change', function() {
                var files = this.files; if (!files.length) return;
                var spinner = $('#odinokov-ai-kb-spinner'); spinner.addClass('is-active');
                var fd = new FormData();
                for (var i = 0; i < files.length; i++) fd.append('files[]', files[i]);
                fd.append('action', 'odinokov_ai_upload_kb');
                fd.append('nonce', '<?php echo wp_create_nonce("odinokov_ai_chat_nonce"); ?>');
                $.ajax({ url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false,
                    success: function(r) { spinner.removeClass('is-active'); if (r.success) location.reload(); else alert('Ошибка: ' + (r.data.detail || '')); },
                    error: function() { spinner.removeClass('is-active'); alert('Ошибка соединения'); }
                });
            });

            $(document).on('click', '.odinokov-ai-kb-delete', function() {
                if (!confirm('Удалить документ?')) return;
                var btn = $(this), row = btn.closest('tr');
                $.post(ajaxurl, { action: 'odinokov_ai_delete_kb', nonce: '<?php echo wp_create_nonce("odinokov_ai_chat_nonce"); ?>', file_id: btn.data('file-id') },
                    function(r) {
                        if (r.success) { row.fadeOut(300, function() { row.remove(); if (!$('#odinokov-ai-kb-list tr:visible').length) $('#odinokov-ai-kb-list').html('<tr id="odinokov-ai-kb-empty"><td colspan="6" style="text-align:center;padding:20px;color:#888;">Нет загруженных документов</td></tr>'); }); }
                        else alert('Ошибка: ' + (r.data.detail || ''));
                    }
                );
            });

            function generateAjax(action, targetId) {
                var btns    = $('#odinokov-ai-generate-areas, #odinokov-ai-generate-suggestions');
                var spinner = $('#odinokov-ai-generate-spinner');
                var msg     = $('#odinokov-ai-generate-msg');
                btns.prop('disabled', true); spinner.addClass('is-active'); msg.text('Генерируем...').css('color', '#2271b1');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: { action: action, nonce: '<?php echo wp_create_nonce("odinokov_ai_chat_nonce"); ?>' },
                    success: function(r) {
                        btns.prop('disabled', false); spinner.removeClass('is-active');
                        console.log('AI Chat response:', r);
                        if (r && r.success && r.data) {
                            var val = r.data.areas || r.data.suggestions || '';
                            console.log('Setting target ' + targetId + ' to:', val);
                            $('#' + targetId).val(val).trigger('change');
                            if (val) {
                                msg.text('Готово. Нажмите «Сохранить настройки».').css('color', '#2271b1');
                            } else {
                                msg.css('color', '#d63638').text('Получен пустой ответ от API. Проверьте категории и API-ключ.');
                            }
                        } else {
                            msg.css('color', '#d63638').text('Ошибка: ' + ((r && r.data && r.data.detail) || 'Неизвестная ошибка'));
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        btns.prop('disabled', false); spinner.removeClass('is-active');
                        var errMsg = textStatus;
                        try {
                            var r = JSON.parse(jqXHR.responseText);
                            if (r && r.data && r.data.detail) errMsg = r.data.detail;
                        } catch(e) {}
                        msg.css('color', '#d63638').text('Ошибка: ' + errMsg);
                    }
                });
            }

            $('#odinokov-ai-generate-areas').on('click', function() { generateAjax('odinokov_ai_generate_areas', 'odinokov_ai_knowledge_areas'); });
            $('#odinokov-ai-generate-suggestions').on('click', function() { generateAjax('odinokov_ai_generate_suggestions', 'odinokov_ai_suggestions'); });

            $('#odinokov-ai-clear-logs').on('click', function() {
                if (!confirm('Удалить все логи диалогов?')) return;
                var btn = $(this), spinner = $('#odinokov-ai-clear-spinner'), msg = $('#odinokov-ai-clear-msg');
                btn.prop('disabled', true); spinner.addClass('is-active'); msg.text('');
                $.post(ajaxurl, { action: 'odinokov_ai_clear_logs', nonce: '<?php echo wp_create_nonce("odinokov_ai_chat_nonce"); ?>' }, function(r) {
                    btn.prop('disabled', false); spinner.removeClass('is-active');
                    if (r.success) msg.css('color', '#2271b1').text('Удалено записей: ' + r.data.deleted);
                    else msg.css('color', '#d63638').text('Ошибка: ' + (r.data.detail || ''));
                }).fail(function() { btn.prop('disabled', false); spinner.removeClass('is-active'); msg.css('color', '#d63638').text('Ошибка соединения'); });
            });
        });
        </script>
        <style>
            .odinokov-ai-tooltip{display:inline-block;width:18px;height:18px;line-height:18px;text-align:center;background:#c3c4c7;color:#fff;border-radius:50%;font-size:12px;font-weight:700;cursor:help;vertical-align:middle;margin-left:2px;}
            .odinokov-ai-icon-upload{display:flex;align-items:center;gap:12px;}
            .odinokov-ai-icon-preview{flex-shrink:0;}
        </style>
    </div>
    <?php
}
