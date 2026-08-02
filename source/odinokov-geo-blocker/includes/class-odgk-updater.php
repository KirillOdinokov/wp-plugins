<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ODGK_Plugin_Updater {
    private $slug, $file, $url, $ver, $name, $author, $author_uri, $desc;

    public function __construct( $pf, $uu, $cv, $a = [] ) {
        $this->file = $pf; $this->url = $uu; $this->slug = plugin_basename( $pf ); $this->ver = $cv;
        $this->name = $a['name'] ?? ''; $this->author = $a['author'] ?? ''; $this->author_uri = $a['author_uri'] ?? ''; $this->desc = $a['description'] ?? '';
        add_filter( 'pre_set_site_transient_update_plugins', [$this, 'check'] );
        add_filter( 'plugins_api', [$this, 'info'], 20, 3 );
        add_filter( 'upgrader_post_install', [$this, 'after'], 10, 3 );
    }

    public function check( $t ) {
        if ( empty( $t->checked ) ) return $t; $r = $this->latest(); if ( !$r ) return $t;
        if ( version_compare( $this->ver, $r['version'], '>=' ) ) return $t;
        if ( empty( $r['download_url'] ) ) return $t;
        $t->response[$this->slug] = (object)['slug'=>dirname($this->slug),'plugin'=>$this->slug,'new_version'=>$r['version'],'url'=>$r['homepage']??'','package'=>$r['download_url'],'tested'=>$r['tested']??get_bloginfo('version')];
        return $t;
    }

    public function info( $res, $act, $args ) {
        if ( 'plugin_information' !== $act || !isset( $args->slug ) || $args->slug !== dirname($this->slug) ) return $res;
        $r = $this->latest(); if ( !$r ) return $res;
        return (object)['name'=>$this->name,'slug'=>dirname($this->slug),'version'=>$r['version'],'author'=>$this->author,'homepage'=>$r['homepage']??$this->author_uri,'requires'=>$r['requires']??'5.8','tested'=>$r['tested']??get_bloginfo('version'),'last_updated'=>$r['last_updated']??'','download_link'=>$r['download_url'],'sections'=>['description'=>$this->desc,'changelog'=>$r['changelog']??'']];
    }

    public function after($r){return $r;}

    private function latest() {
        $k = 'odgk_rel_'.md5($this->url); $c = get_transient( $k ); if ( false !== $c ) return $c;
        $resp = wp_remote_get( $this->url, ['timeout'=>15] );
        if ( is_wp_error($resp) || 200 !== wp_remote_retrieve_response_code($resp) ) return null;
        $d = json_decode( wp_remote_retrieve_body($resp), true );
        if ( !$d || empty($d['version']) || empty($d['download_url']) ) return null;
        set_transient( $k, $d, 6 * HOUR_IN_SECONDS ); return $d;
    }
}
