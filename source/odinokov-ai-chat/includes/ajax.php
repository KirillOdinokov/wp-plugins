<?php
if (!defined('ABSPATH')) {
    exit;
}

function odinokov_ai_handle_verify_captcha() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    $answer = isset($_POST['answer']) ? trim(wp_unslash($_POST['answer'])) : '';
    $hash   = isset($_POST['hash']) ? sanitize_text_field(wp_unslash($_POST['hash'])) : '';

    if (empty($answer) || empty($hash)) {
        wp_send_json_error(['detail' => 'Неверный запрос'], 400);
    }

    if (!odinokov_ai_verify_captcha($answer, $hash)) {
        wp_send_json_error(['detail' => 'Неверный ответ, попробуйте ещё раз'], 400);
    }

    wp_send_json_success(['message' => 'OK']);
}
add_action('wp_ajax_odinokov_ai_verify_captcha', 'odinokov_ai_handle_verify_captcha');
add_action('wp_ajax_nopriv_odinokov_ai_verify_captcha', 'odinokov_ai_handle_verify_captcha');

function odinokov_ai_handle_chat() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';

    if (empty($message)) {
        wp_send_json_error(['detail' => 'Сообщение не может быть пустым'], 400);
    }

    $api_key = get_option('odinokov_ai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['detail' => 'API-ключ не настроен. Зайдите в Настройки → Odinokov AI Chat.'], 500);
    }

    $model       = get_option('odinokov_ai_model', 'deepseek-v4-pro');
    $temperature = (float) get_option('odinokov_ai_temperature', 0.3);
    $max_tokens  = (int) get_option('odinokov_ai_max_tokens', 2048);

    $default_prompt = odinokov_ai_default_system_prompt();
    $system_prompt  = get_option('odinokov_ai_system_prompt', '');
    if (empty(trim($system_prompt))) {
        $system_prompt = $default_prompt;
    }

    $default_areas  = odinokov_ai_default_knowledge_areas();
    $knowledge_areas = get_option('odinokov_ai_knowledge_areas', '');
    if (empty(trim($knowledge_areas))) {
        $knowledge_areas = $default_areas;
    }

    $full_prompt = $system_prompt . "\n\n" . $knowledge_areas;

    $body = [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $full_prompt],
            ['role' => 'user',   'content' => $message],
        ],
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
    ];

    $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['detail' => 'Ошибка соединения: ' . $response->get_error_message()], 502);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body_raw    = wp_remote_retrieve_body($response);
    $data        = json_decode($body_raw, true);

    if ($status_code !== 200 || !isset($data['choices'][0]['message']['content'])) {
        $error_msg = $data['error']['message'] ?? 'Неизвестная ошибка API';
        wp_send_json_error(['detail' => "DeepSeek API ({$status_code}): {$error_msg}"], 502);
    }

    $reply = $data['choices'][0]['message']['content'];

    odinokov_ai_log_conversation($message, $reply, $model);

    wp_send_json_success(['reply' => $reply]);
}
add_action('wp_ajax_odinokov_ai_chat', 'odinokov_ai_handle_chat');
add_action('wp_ajax_nopriv_odinokov_ai_chat', 'odinokov_ai_handle_chat');

function odinokov_ai_handle_send_transcript() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    $messages = isset($_POST['messages']) ? json_decode(wp_unslash($_POST['messages']), true) : [];

    if (empty($messages) || !is_array($messages)) {
        wp_send_json_success(['message' => 'Нет сообщений для отправки']);
    }

    $enabled = get_option('odinokov_ai_notify_enabled', true);
    if (!$enabled) {
        wp_send_json_success(['message' => 'Уведомления отключены']);
    }

    $to = get_option('odinokov_ai_notify_email', get_option('admin_email'));
    if (empty($to)) {
        wp_send_json_success(['message' => 'Email не настроен']);
    }

    $site_name = get_bloginfo('name');
    $subject   = "Диалог чата с ИИ " . wp_date('d.m.Y H:i');

    $body = "Транскрипт диалога с ИИ-консультантом ({$site_name})\n";
    $body .= "Дата: " . wp_date('d.m.Y H:i:s') . "\n";
    $body .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'неизвестно') . "\n";
    $body .= str_repeat('-', 50) . "\n\n";

    foreach ($messages as $msg) {
        $role  = ($msg['role'] === 'user') ? 'Посетитель' : 'ИИ';
        $body .= "{$role}:\n{$msg['text']}\n\n";
    }

    $body .= str_repeat('-', 50) . "\n";
    $body .= "Сообщение отправлено плагином Odinokov AI Chat\n";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    wp_mail($to, $subject, $body, $headers);

    wp_send_json_success(['message' => 'OK']);
}
add_action('wp_ajax_odinokov_ai_send_transcript', 'odinokov_ai_handle_send_transcript');
add_action('wp_ajax_nopriv_odinokov_ai_send_transcript', 'odinokov_ai_handle_send_transcript');

function odinokov_ai_handle_upload_kb() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    if (empty($_FILES['files'])) {
        wp_send_json_error(['detail' => 'Файлы не выбраны'], 400);
    }

    $allowed_mimes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => 'text/plain',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv'  => 'text/csv',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
    ];

    $kb_files = get_option('odinokov_ai_kb_files', []);

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $files = $_FILES['files'];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $file = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];

        $attachment_id = media_handle_sideload($file, 0);
        if (is_wp_error($attachment_id)) {
            continue;
        }

        $kb_files[] = [
            'attachment_id' => $attachment_id,
            'size'          => $files['size'][$i],
            'uploaded_at'   => wp_date('d.m.Y H:i'),
        ];
    }

    update_option('odinokov_ai_kb_files', $kb_files);
    wp_send_json_success(['message' => 'OK']);
}
add_action('wp_ajax_odinokov_ai_upload_kb', 'odinokov_ai_handle_upload_kb');

function odinokov_ai_handle_delete_kb() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    $file_id = isset($_POST['file_id']) ? absint($_POST['file_id']) : 0;
    if (!$file_id) {
        wp_send_json_error(['detail' => 'Файл не указан'], 400);
    }

    $kb_files = get_option('odinokov_ai_kb_files', []);
    $kb_files = array_filter($kb_files, function($f) use ($file_id) {
        return $f['attachment_id'] !== $file_id;
    });
    $kb_files = array_values($kb_files);

    update_option('odinokov_ai_kb_files', $kb_files);
    wp_send_json_success(['message' => 'OK']);
}
add_action('wp_ajax_odinokov_ai_delete_kb', 'odinokov_ai_handle_delete_kb');

function odinokov_ai_handle_generate_areas() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    $api_key = get_option('odinokov_ai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['detail' => 'API-ключ не настроен'], 500);
    }

    $categories = get_option('odinokov_ai_categories', '');
    if (empty(trim($categories))) {
        wp_send_json_error(['detail' => 'Сначала заполните поле «Категории сайта»'], 400);
    }

    $system_msg = "Ты — эксперт по строительным стандартам. Твоя задача — для ЛЮБЫХ категорий товаров или материалов предложить релевантные ГОСТ, СНиП, СП, ISO, DIN. Даже если точного стандарта нет, предложи наиболее близкие по смыслу. НИКОГДА не возвращай пустой ответ — всегда пиши хотя бы общие нормативы для этой области. Отвечай ТОЛЬКО списком в формате ОБЛАСТИ ЗНАНИЙ:";

    $gen_prompt = "Для каждой из перечисленных категорий подбери релевантные действующие ГОСТ, СНиП, СП (РФ), ISO, DIN. "
        . "Если для категории нет точного стандарта — укажи общие нормативы, применимые к этой области. "
        . "ВАЖНО: не возвращай пустой ответ. Если не уверен — напиши хотя бы общие стандарты. "
        . "Формат ответа (строго):\n\n"
        . "ОБЛАСТИ ЗНАНИЙ:\n"
        . "- Название категории (ГОСТ XXXX-YYYY, СП XX.XXXX)\n\n"
        . "Категории:\n" . $categories;

    $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'model'       => get_option('odinokov_ai_model', 'deepseek-v4-pro'),
            'messages'    => [
                ['role' => 'system', 'content' => $system_msg],
                ['role' => 'user', 'content' => $gen_prompt],
            ],
            'temperature' => 0.3,
            'max_tokens'  => 1024,
        ]),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['detail' => 'Ошибка соединения: ' . $response->get_error_message()], 502);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body_raw    = wp_remote_retrieve_body($response);
    $data        = json_decode($body_raw, true);

    if ($status_code !== 200 || !isset($data['choices'][0]['message']['content'])) {
        $error_msg = $data['error']['message'] ?? 'Неизвестная ошибка API';
        wp_send_json_error(['detail' => "DeepSeek API ({$status_code}): {$error_msg}"], 502);
    }

    $areas = trim($data['choices'][0]['message']['content']);
    wp_send_json_success(['areas' => $areas]);
}
add_action('wp_ajax_odinokov_ai_generate_areas', 'odinokov_ai_handle_generate_areas');

function odinokov_ai_handle_generate_suggestions() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');

    $api_key = get_option('odinokov_ai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['detail' => 'API-ключ не настроен'], 500);
    }

    $categories = get_option('odinokov_ai_categories', '');
    if (empty(trim($categories))) {
        wp_send_json_error(['detail' => 'Сначала заполните поле «Категории сайта»'], 400);
    }

    $gen_prompt = "На основе перечисленных ниже категорий товаров/услуг сайта придумай 5-8 популярных вопросов, "
        . "которые посетители могут задать консультанту. Вопросы должны быть на русском языке, "
        . "конкретными и полезными. Каждый вопрос на отдельной строке. "
        . "Не нумеруй строки, не добавляй лишнего текста, только вопросы.\n\n"
        . "Категории:\n" . $categories;

    $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'model'       => get_option('odinokov_ai_model', 'deepseek-v4-pro'),
            'messages'    => [
                ['role' => 'user', 'content' => $gen_prompt],
            ],
            'temperature' => 0.5,
            'max_tokens'  => 512,
        ]),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['detail' => 'Ошибка соединения: ' . $response->get_error_message()], 502);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body_raw    = wp_remote_retrieve_body($response);
    $data        = json_decode($body_raw, true);

    if ($status_code !== 200 || !isset($data['choices'][0]['message']['content'])) {
        $error_msg = $data['error']['message'] ?? 'Неизвестная ошибка API';
        wp_send_json_error(['detail' => "DeepSeek API ({$status_code}): {$error_msg}"], 502);
    }

    $suggestions = trim($data['choices'][0]['message']['content']);
    wp_send_json_success(['suggestions' => $suggestions]);
}
add_action('wp_ajax_odinokov_ai_generate_suggestions', 'odinokov_ai_handle_generate_suggestions');

function odinokov_ai_handle_clear_logs() {
    check_ajax_referer('odinokov_ai_chat_nonce', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'odinokov_ai_logs';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $wpdb->query("TRUNCATE TABLE {$table}");
    wp_send_json_success(['deleted' => (int) $count]);
}
add_action('wp_ajax_odinokov_ai_clear_logs', 'odinokov_ai_handle_clear_logs');
