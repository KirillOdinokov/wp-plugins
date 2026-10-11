<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OSOC_Plugin_Updater {

	private $slug;
	private $file;
	private $url;
	private $ver;
	private $name;
	private $author;
	private $author_uri;
	private $desc;

	public function __construct( $pf, $uu, $cv, $a = array() ) {
		$this->file       = $pf;
		$this->url        = $uu;
		$this->slug       = plugin_basename( $pf );
		$this->ver        = $cv;
		$this->name       = isset( $a['name'] ) ? $a['name'] : '';
		$this->author     = isset( $a['author'] ) ? $a['author'] : '';
		$this->author_uri = isset( $a['author_uri'] ) ? $a['author_uri'] : '';
		$this->desc       = isset( $a['description'] ) ? $a['description'] : '';

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
	}

	public function check( $t ) {
		if ( empty( $t->checked ) ) {
			return $t;
		}
		$r = $this->latest();
		if ( ! $r ) {
			return $t;
		}
		if ( version_compare( $this->ver, $r['version'], '>=' ) ) {
			return $t;
		}
		if ( empty( $r['download_url'] ) ) {
			return $t;
		}
		$t->response[ $this->slug ] = (object) array(
			'slug'        => dirname( $this->slug ),
			'plugin'      => $this->slug,
			'new_version' => $r['version'],
			'url'         => isset( $r['homepage'] ) ? $r['homepage'] : '',
			'package'     => $r['download_url'],
			'tested'      => isset( $r['tested'] ) ? $r['tested'] : get_bloginfo( 'version' ),
		);
		return $t;
	}

	public function info( $res, $act, $args ) {
		if ( 'plugin_information' !== $act || ! isset( $args->slug ) || $args->slug !== dirname( $this->slug ) ) {
			return $res;
		}
		$r = $this->latest();
		if ( ! $r ) {
			return $res;
		}
		return (object) array(
			'name'          => $this->name,
			'slug'          => dirname( $this->slug ),
			'version'       => $r['version'],
			'author'        => $this->author,
			'homepage'      => isset( $r['homepage'] ) ? $r['homepage'] : $this->author_uri,
			'requires'      => isset( $r['requires'] ) ? $r['requires'] : '5.8',
			'tested'        => isset( $r['tested'] ) ? $r['tested'] : get_bloginfo( 'version' ),
			'last_updated'  => isset( $r['last_updated'] ) ? $r['last_updated'] : '',
			'download_link' => $r['download_url'],
			'sections'      => array(
				'description' => $this->desc,
				'changelog'   => isset( $r['changelog'] ) ? $r['changelog'] : '',
			),
		);
	}

	private function latest() {
		$k = 'osoc_rel_' . md5( $this->url );
		$c = get_transient( $k );
		if ( false !== $c ) {
			return $c;
		}
		$resp = wp_remote_get( $this->url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return null;
		}
		$d = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! $d || empty( $d['version'] ) || empty( $d['download_url'] ) ) {
			return null;
		}
		set_transient( $k, $d, 6 * HOUR_IN_SECONDS );
		return $d;
	}
}
