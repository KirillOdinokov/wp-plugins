<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OBM_Plugin_Updater {

    private $plugin_slug;
    private $plugin_file;
    private $update_url;
    private $current_version;
    private $plugin_name;
    private $plugin_author;
    private $plugin_author_uri;
    private $plugin_description;

    public function __construct( $plugin_file, $update_url, $current_version, $args = array() ) {
        $this->plugin_file        = $plugin_file;
        $this->update_url         = $update_url;
        $this->plugin_slug        = plugin_basename( $plugin_file );
        $this->current_version    = $current_version;
        $this->plugin_name        = $args['name'] ?? 'Odinokov Breadcrumbs';
        $this->plugin_author      = $args['author'] ?? '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>';
        $this->plugin_author_uri  = $args['author_uri'] ?? 'https://github.com/KirillOdinokov/wp-plugins';
        $this->plugin_description = $args['description'] ?? '';

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_update' ), 10, 3 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;
        $release = $this->get_latest_release();
        if ( ! $release ) return $transient;
        if ( version_compare( $this->current_version, $release['version'], '>=' ) ) return $transient;
        if ( empty( $release['download_url'] ) ) return $transient;

        $transient->response[ $this->plugin_slug ] = (object) array(
            'slug' => dirname( $this->plugin_slug ), 'plugin' => $this->plugin_slug,
            'new_version' => $release['version'], 'url' => $release['homepage'] ?? '',
            'package' => $release['download_url'], 'tested' => $release['tested'] ?? get_bloginfo( 'version' ),
        );
        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) return $result;
        $release = $this->get_latest_release();
        if ( ! $release ) return $result;

        return (object) array(
            'name' => $this->plugin_name, 'slug' => dirname( $this->plugin_slug ),
            'version' => $release['version'], 'author' => $this->plugin_author,
            'homepage' => $release['homepage'] ?? $this->plugin_author_uri,
            'requires' => $release['requires'] ?? '5.8', 'tested' => $release['tested'] ?? get_bloginfo( 'version' ),
            'last_updated' => $release['last_updated'] ?? '', 'download_link' => $release['download_url'],
            'sections' => array( 'description' => $this->plugin_description, 'changelog' => $release['changelog'] ?? '' ),
        );
    }

    public function after_update( $response, $hook_extra, $result ) { return $response; }

    private function get_latest_release() {
        $key = 'obm_release_' . md5( $this->update_url );
        $cached = get_transient( $key );
        if ( false !== $cached ) return $cached;

        $resp = wp_remote_get( $this->update_url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) return null;

        $release = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! $release || empty( $release['version'] ) || empty( $release['download_url'] ) ) return null;

        set_transient( $key, $release, 6 * HOUR_IN_SECONDS );
        return $release;
    }
}
