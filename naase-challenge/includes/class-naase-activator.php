<?php
/**
 * Activation / deactivation.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Activator {

	/**
	 * On activation: create tables, seed default settings, register rewrites and flush.
	 */
	public static function activate() {
		NAASE_DB::create_tables();

		if ( false === get_option( NAASE_Settings::OPTION, false ) ) {
			add_option( NAASE_Settings::OPTION, NAASE_Settings::defaults() );
		}

		// Make the result/leaderboard pretty URLs resolve immediately.
		NAASE_Rewrites::add_rules();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'naase_sweep_stale' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'naase_sweep_stale' );
		}
	}

	/**
	 * On deactivation: clear scheduled events and flush rewrites.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'naase_sweep_stale' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'naase_sweep_stale' );
		}
		flush_rewrite_rules();
	}
}
