<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IFO_Plugin_Updater {
    private $plugin_slug, $plugin_file, $update_url, $current_version, $plugin_name, $plugin_author, $plugin_author_uri, $plugin_description;

    public function __construct( $pf, $uu, $cv, $args = [] ) {
        $this->plugin_file = $pf; $this->update_url = $uu; $this->plugin_slug = plugin_basename( $pf ); $this->current_version = $cv;
        $this->plugin_name = $args['name'] ?? ''; $this->plugin_author = $args['author'] ?? ''; $this->plugin_author_uri = $args['author_uri'] ?? ''; $this->plugin_description = $args['description'] ?? '';
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check' ] );
        add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
        add_filter( 'upgrader_post_install', [ $this, 'after' ], 10, 3 );
    }

    public function check( $t ) {
        if ( empty( $t->checked ) ) return $t;
        $r = $this->latest(); if ( ! $r ) return $t;
        if ( version_compare( $this->current_version, $r['version'], '>=' ) ) return $t;
        if ( empty( $r['download_url'] ) ) return $t;
        $t->response[ $this->plugin_slug ] = (object) [ 'slug' => dirname( $this->plugin_slug ), 'plugin' => $this->plugin_slug, 'new_version' => $r['version'], 'url' => $r['homepage'] ?? '', 'package' => $r['download_url'], 'tested' => $r['tested'] ?? get_bloginfo('version') ];
        return $t;
    }

    public function info( $res, $act, $args ) {
        if ( 'plugin_information' !== $act ) return $res;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) return $res;
        $r = $this->latest(); if ( ! $r ) return $res;
        return (object) [ 'name' => $this->plugin_name, 'slug' => dirname( $this->plugin_slug ), 'version' => $r['version'], 'author' => $this->plugin_author, 'homepage' => $r['homepage'] ?? $this->plugin_author_uri, 'requires' => $r['requires'] ?? '5.8', 'tested' => $r['tested'] ?? get_bloginfo('version'), 'last_updated' => $r['last_updated'] ?? '', 'download_link' => $r['download_url'], 'sections' => [ 'description' => $this->plugin_description, 'changelog' => $r['changelog'] ?? '' ] ];
    }

    public function after( $res ) { return $res; }

    private function latest() {
        $k = 'ifo_rel_' . md5( $this->update_url );
        $c = get_transient( $k ); if ( false !== $c ) return $c;
        $resp = wp_remote_get( $this->update_url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) return null;
        $d = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! $d || empty( $d['version'] ) || empty( $d['download_url'] ) ) return null;
        set_transient( $k, $d, 6 * HOUR_IN_SECONDS ); return $d;
    }
}
