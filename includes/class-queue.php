<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Queue {

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'aiap_queue_run', array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure' ) );
	}

	public static function schedules( $s ) {
		if ( ! isset( $s['weekly'] ) ) {
			$s['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => 'Once Weekly' );
		}
		return $s;
	}

	/**
	 * Settings ke hisaab se cron event schedule/unschedule karo.
	 */
	public static function ensure() {
		$s    = AIAP_Settings::all();
		$want = $s['queue_enabled'] ? $s['queue_interval'] : '';
		$cur  = get_option( 'aiap_queue_sched', '' );
		if ( $want === $cur && ( '' === $want || wp_next_scheduled( 'aiap_queue_run' ) ) ) {
			return;
		}
		wp_clear_scheduled_hook( 'aiap_queue_run' );
		if ( $want ) {
			wp_schedule_event( time() + 60, $want, 'aiap_queue_run' );
		}
		update_option( 'aiap_queue_sched', $want, false );
	}

	public static function items() {
		$q = get_option( 'aiap_queue', array() );
		return is_array( $q ) ? $q : array();
	}

	public static function add( $keywords, $args ) {
		$q = self::items();
		unset( $args['title'] );
		foreach ( $keywords as $kw ) {
			if ( count( $q ) >= 500 ) {
				break;
			}
			$q[] = array( 'kw' => $kw, 'args' => $args );
		}
		update_option( 'aiap_queue', $q, false );
		return count( $q );
	}

	public static function remove( $index ) {
		$q = self::items();
		if ( isset( $q[ $index ] ) ) {
			array_splice( $q, $index, 1 );
			update_option( 'aiap_queue', $q, false );
		}
	}

	public static function clear() {
		update_option( 'aiap_queue', array(), false );
	}

	/**
	 * Queue ke agle item(s) process karo. $force = manual "Run now" (sirf 1 item).
	 */
	public static function run( $force = false ) {
		if ( get_transient( 'aiap_qlock' ) ) {
			return 0;
		}
		set_transient( 'aiap_qlock', 1, 15 * MINUTE_IN_SECONDS );
		$q    = self::items();
		$n    = ( true === $force ) ? 1 : max( 1, (int) AIAP_Settings::get( 'queue_per_run' ) );
		$done = 0;
		while ( $n-- > 0 && $q ) {
			$item = array_shift( $q );
			update_option( 'aiap_queue', $q, false );
			AIAP_Generator::run( $item['kw'], isset( $item['args'] ) ? $item['args'] : array() );
			$done++;
		}
		delete_transient( 'aiap_qlock' );
		return $done;
	}
}
