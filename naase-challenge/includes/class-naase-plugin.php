<?php
/**
 * Main plugin loader — wires the pieces together.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Plugin {

	/** @var NAASE_Plugin|null */
	private static $instance = null;

	/**
	 * Boot the plugin once.
	 *
	 * @return NAASE_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'naase-challenge', false, dirname( NAASE_PLUGIN_BASENAME ) . '/languages' );

		NAASE_REST::init();
		NAASE_Rewrites::init();
		NAASE_Shortcodes::init();

		if ( is_admin() ) {
			NAASE_Admin::init();
		}

		// Cron: sweep stale in-progress attempts into timed_out.
		add_action( 'naase_sweep_stale', array( 'NAASE_Attempts', 'sweep_stale' ) );
	}
}
