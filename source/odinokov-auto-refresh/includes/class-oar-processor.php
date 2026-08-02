<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAR_Processor {

    const TRANSIENT_LOCK = 'oar_run_lock';

    public static function get_settings() {
        $defaults = array(
            'interval_days'    => 14,
            'batch_size'       => 30,
            'post_types'       => array( 'post', 'page', 'product' ),
            'taxonomies'       => array( 'category', 'post_tag', 'product_cat', 'product_tag' ),
            'skip_recent_days' => 0,
            'enabled'          => 1,
            'jitter_minutes'   => 0,
            'min_gap_hours'    => 12,
        );
        $opt = get_option( OAR_OPTION_KEY, array() );
        if ( ! is_array( $opt ) ) {
            $opt = array();
        }
        return wp_parse_args( $opt, $defaults );
    }

    public static function run() {
        $settings = self::get_settings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        if ( get_transient( self::TRANSIENT_LOCK ) ) {
            return;
        }
        set_transient( self::TRANSIENT_LOCK, 1, 5 * MINUTE_IN_SECONDS );

        $state = self::get_state();
        $now   = time();

        if ( ! empty( $state['last_finish'] ) ) {
            $min_gap = max( 0, (int) $settings['min_gap_hours'] ) * HOUR_IN_SECONDS;
            if ( $min_gap > 0 && ( $now - (int) $state['last_finish'] ) < $min_gap ) {
                delete_transient( self::TRANSIENT_LOCK );
                return;
            }
        }

        $state['last_run'] = $now;
        $batch = max( 1, (int) $settings['batch_size'] );

        $touched  = 0;
        $touched += self::process_posts( $settings, $state, $batch );
        $touched += self::process_terms( $settings, $state, $batch );

        if ( self::is_finished( $state ) ) {
            $state['last_finish'] = $now;
            $state['offset']      = 0;
            $state['cursor']      = array();
        }

        self::save_state( $state );
        delete_transient( self::TRANSIENT_LOCK );
    }

    private static function process_posts( $settings, &$state, $batch ) {
        global $wpdb;
        $post_types = array_values( array_filter( (array) $settings['post_types'] ) );
        if ( empty( $post_types ) ) {
            return 0;
        }

        $interval_days = max( 1, (int) $settings['interval_days'] );
        $skip_days     = max( 0, (int) $settings['skip_recent_days'] );

        $last_run   = ! empty( $state['last_finish'] ) ? (int) $state['last_finish'] : time();
        $cursor_key = 'post_offset';
        $offset     = isset( $state['cursor'][ $cursor_key ] ) ? (int) $state['cursor'][ $cursor_key ] : 0;

        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $params       = $post_types;

        $sql = "SELECT ID FROM {$wpdb->posts}
                WHERE post_type IN ($placeholders)
                  AND post_status IN ('publish','future','draft','pending','private')
                ORDER BY post_modified_gmt ASC
                LIMIT %d OFFSET %d";

        $params[] = $batch;
        $params[] = $offset;
        $prepared = $wpdb->prepare( $sql, $params );
        $ids      = $wpdb->get_col( $prepared );

        if ( empty( $ids ) ) {
            $state['cursor'][ $cursor_key ] = 0;
            return 0;
        }

        $count  = 0;
        $jitter = (int) $settings['jitter_minutes'];

        $placeholders_in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql_dates = $wpdb->prepare(
            "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE ID IN ($placeholders_in)",
            $ids
        );
        $rows = $wpdb->get_results( $sql_dates );
        $modified_map = array();
        foreach ( (array) $rows as $r ) {
            $modified_map[ (int) $r->ID ] = $r->post_modified_gmt;
        }

        $threshold    = $last_run - ( $interval_days * DAY_IN_SECONDS );
        $recent_floor = ( $skip_days > 0 ) ? ( time() - $skip_days * DAY_IN_SECONDS ) : 0;

        foreach ( $ids as $post_id ) {
            $post_id = (int) $post_id;
            $current_modified = isset( $modified_map[ $post_id ] ) ? $modified_map[ $post_id ] : '';
            if ( ! $current_modified ) {
                continue;
            }

            $current_ts = strtotime( $current_modified . ' UTC' );
            if ( ! $current_ts ) {
                continue;
            }

            if ( $current_ts > $threshold ) {
                continue;
            }

            if ( $skip_days > 0 && $current_ts > $recent_floor ) {
                continue;
            }

            $new_ts    = self::make_jittered_time( $jitter );
            $new_gmt   = gmdate( 'Y-m-d H:i:s', $new_ts );
            $new_local = get_date_from_gmt( $new_gmt );

            $result = $wpdb->update(
                $wpdb->posts,
                array(
                    'post_modified'     => $new_local,
                    'post_modified_gmt' => $new_gmt,
                ),
                array( 'ID' => $post_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( false !== $result ) {
                wp_cache_delete( $post_id, 'posts' );
                $count++;
            }
        }

        $state['cursor'][ $cursor_key ] = $offset + count( $ids );
        return $count;
    }

    private static function process_terms( $settings, &$state, $batch ) {
        global $wpdb;
        $taxonomies = array_values( array_filter( (array) $settings['taxonomies'] ) );
        if ( empty( $taxonomies ) ) {
            return 0;
        }

        $interval_days = max( 1, (int) $settings['interval_days'] );
        $skip_days     = max( 0, (int) $settings['skip_recent_days'] );
        $last_run      = ! empty( $state['last_finish'] ) ? (int) $state['last_finish'] : time();
        $jitter        = (int) $settings['jitter_minutes'];
        $threshold     = $last_run - ( $interval_days * DAY_IN_SECONDS );
        $recent_floor  = ( $skip_days > 0 ) ? ( time() - $skip_days * DAY_IN_SECONDS ) : 0;

        $count                 = 0;
        $taxonomy_placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
        $last_id_state_key     = 'term_last_id';

        $cursor_id     = isset( $state['cursor'][ $last_id_state_key ] ) ? (int) $state['cursor'][ $last_id_state_key ] : 0;
        $cursor_offset = isset( $state['cursor']['term_offset'] ) ? (int) $state['cursor']['term_offset'] : 0;

        $sql = $wpdb->prepare(
            "SELECT t.term_id, t.taxonomy
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy IN ($taxonomy_placeholders)
               AND t.term_id > %d
             ORDER BY t.term_id ASC
             LIMIT %d",
            array_merge( $taxonomies, array( $cursor_id, $batch ) )
        );
        $rows = $wpdb->get_results( $sql );

        if ( empty( $rows ) ) {
            $state['cursor']['term_offset']          = 0;
            $state['cursor'][ $last_id_state_key ]   = 0;
            return 0;
        }

        $term_ids = array();
        $tax_map  = array();
        foreach ( (array) $rows as $r ) {
            $tid = (int) $r->term_id;
            $term_ids[] = $tid;
            $tax_map[ $tid ] = $r->taxonomy;
        }

        $existing_meta = array();
        if ( ! empty( $term_ids ) ) {
            $placeholders_in = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
            $meta_sql = $wpdb->prepare(
                "SELECT term_id, meta_value FROM {$wpdb->termmeta}
                 WHERE meta_key = 'oar_last_touched' AND term_id IN ($placeholders_in)",
                $term_ids
            );
            foreach ( (array) $wpdb->get_results( $meta_sql ) as $mr ) {
                $existing_meta[ (int) $mr->term_id ] = (int) $mr->meta_value;
            }
        }

        $max_id_seen = $cursor_id;
        foreach ( $term_ids as $term_id ) {
            $current_ts = isset( $existing_meta[ $term_id ] ) ? (int) $existing_meta[ $term_id ] : 0;
            if ( ! $current_ts ) {
                $current_ts = time();
            }

            if ( $current_ts > $threshold ) {
                if ( $term_id > $max_id_seen ) {
                    $max_id_seen = $term_id;
                }
                continue;
            }
            if ( $skip_days > 0 && $current_ts > $recent_floor ) {
                if ( $term_id > $max_id_seen ) {
                    $max_id_seen = $term_id;
                }
                continue;
            }

            $new_ts = self::make_jittered_time( $jitter );
            update_term_meta( $term_id, 'oar_last_touched', $new_ts );
            $count++;
            if ( $term_id > $max_id_seen ) {
                $max_id_seen = $term_id;
            }
        }

        $state['cursor'][ $last_id_state_key ] = $max_id_seen;
        $state['cursor']['term_offset']         = $cursor_offset + 1;
        if ( count( $rows ) < $batch ) {
            $state['cursor']['term_offset']        = 0;
            $state['cursor'][ $last_id_state_key ] = 0;
        }

        return $count;
    }

    private static function is_finished( $state ) {
        if ( empty( $state['cursor'] ) ) {
            return true;
        }
        foreach ( $state['cursor'] as $v ) {
            if ( (int) $v > 0 ) {
                return false;
            }
        }
        return true;
    }

    private static function make_jittered_time( $jitter_minutes ) {
        $jitter = max( 0, (int) $jitter_minutes );
        if ( $jitter <= 0 ) {
            return time();
        }
        $offset = wp_rand( 0, $jitter * MINUTE_IN_SECONDS * 2 ) - ( $jitter * MINUTE_IN_SECONDS );
        return time() + $offset;
    }

    public static function get_state() {
        $state = get_option( OAR_STATE_KEY, array() );
        if ( ! is_array( $state ) ) {
            $state = array();
        }
        $state = wp_parse_args( $state, array(
            'offset'      => 0,
            'last_run'    => 0,
            'last_finish' => 0,
            'cursor'      => array(),
        ) );

        $migrated = false;
        if ( ! empty( $state['cursor'] ) && is_array( $state['cursor'] ) ) {
            $new_cursor = array();
            foreach ( $state['cursor'] as $k => $v ) {
                if ( strpos( $k, 'term_offset_' ) === 0 ) {
                    $migrated = true;
                    continue;
                }
                $new_cursor[ $k ] = $v;
            }
            if ( $migrated ) {
                $state['cursor'] = $new_cursor;
                update_option( OAR_STATE_KEY, $state, 'no' );
            }
        }
        return $state;
    }

    public static function save_state( $state ) {
        update_option( OAR_STATE_KEY, $state, false );
    }

    public static function reset_state() {
        update_option( OAR_STATE_KEY, array(
            'offset'      => 0,
            'last_run'    => 0,
            'last_finish' => 0,
            'cursor'      => array(),
        ), false );
    }
}
