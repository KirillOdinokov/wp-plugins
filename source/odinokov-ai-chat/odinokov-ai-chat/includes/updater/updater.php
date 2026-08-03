<?php
if (!defined('ABSPATH')) {
    exit;
}

class Odinokov_AI_Updater {

    private $plugin_slug;
    private $plugin_file;
    private $update_url;
    private $current_version;

    public function __construct($plugin_file, $update_url) {
        $this->plugin_file     = $plugin_file;
        $this->update_url      = $update_url;
        $this->plugin_slug     = plugin_basename($plugin_file);
        $this->current_version = ODINOKOV_AI_VERSION;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_update'], 10, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $transient;
        }

        if (version_compare($this->current_version, $release['version'], '>=')) {
            return $transient;
        }

        if (empty($release['download_url'])) {
            return $transient;
        }

        $transient->response[$this->plugin_slug] = (object) [
            'slug'        => dirname($this->plugin_slug),
            'plugin'      => $this->plugin_slug,
            'new_version' => $release['version'],
            'url'         => $release['homepage'] ?? '',
            'package'     => $release['download_url'],
            'tested'      => $release['tested'] ?? get_bloginfo('version'),
        ];

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        return (object) [
            'name'           => 'Odinokov AI Chat',
            'slug'           => dirname($this->plugin_slug),
            'version'        => $release['version'],
            'author'         => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
            'homepage'       => $release['homepage'] ?? 'https://github.com/KirillOdinokov/wp-plugins',
            'requires'       => $release['requires'] ?? '5.0',
            'tested'         => $release['tested'] ?? get_bloginfo('version'),
            'last_updated'   => $release['last_updated'] ?? '',
            'download_link'  => $release['download_url'],
            'sections'       => [
                'description' => 'Чат-консультант с ИИ (DeepSeek). Отвечает по ГОСТ, СНиП, СП.',
                'changelog'   => $release['changelog'] ?? '',
            ],
        ];
    }

    public function after_update($response, $hook_extra, $result) {
        return $response;
    }

    private function get_latest_release() {
        $transient_key = 'odinokov_ai_release_' . md5($this->update_url);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($this->update_url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!$release || empty($release['version']) || empty($release['download_url'])) {
            return null;
        }

        set_transient($transient_key, $release, 6 * HOUR_IN_SECONDS);
        return $release;
    }
}
