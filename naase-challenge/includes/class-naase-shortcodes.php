<?php
/**
 * Shortcodes:
 *   [naase_challenge]   → the full challenge experience (start → questions → form → result)
 *   [naase_leaderboard] → the standings table
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Shortcodes {

	public static function init() {
		add_shortcode( 'naase_challenge', array( __CLASS__, 'challenge' ) );
		add_shortcode( 'naase_leaderboard', array( __CLASS__, 'leaderboard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) front-end assets; shortcodes enqueue on demand.
	 */
	public static function register_assets() {
		// Two separate handles on purpose: WP appends &ver via add_query_arg(), which
		// parses the query string and collapses duplicate `family=` keys — combining both
		// families in one URL would silently drop League Gothic. One family per request avoids that.
		wp_register_style( 'naase-fonts-title', 'https://fonts.googleapis.com/css2?family=League+Gothic&display=swap', array(), NAASE_VERSION );
		wp_register_style( 'naase-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap', array( 'naase-fonts-title' ), NAASE_VERSION );
		wp_register_style( 'naase-challenge', NAASE_PLUGIN_URL . 'public/css/challenge.css', array( 'naase-fonts' ), NAASE_VERSION );
		wp_register_script( 'naase-challenge', NAASE_PLUGIN_URL . 'public/js/challenge.js', array(), NAASE_VERSION, true );
	}

	/**
	 * Shared JS config for the challenge app.
	 *
	 * @return array
	 */
	private static function app_config() {
		$settings = NAASE_Settings::all();
		return array(
			'restUrl'        => esc_url_raw( rest_url( NAASE_REST::NS . '/' ) ),
			'ajaxUrl'        => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'total'          => NAASE_QUESTIONS_PER_ATTEMPT,
			'timeoutSeconds' => NAASE_TIMEOUT_SECONDS,
			'leaderboardUrl' => NAASE_Rewrites::leaderboard_url(),
			'challengeUrl'   => NAASE_Rewrites::challenge_url(),
			'settings'       => array(
				'title'          => $settings['challenge_title'],
				'postCompletion' => $settings['post_completion'],
				'shareText'      => $settings['share_text'],
				'privacyText'    => $settings['privacy_text'],
			),
		);
	}

	/**
	 * [naase_challenge]
	 *
	 * @return string
	 */
	public static function challenge() {
		wp_enqueue_style( 'naase-challenge' );
		wp_enqueue_script( 'naase-challenge' );

		$settings = NAASE_Settings::all();
		$enough   = NAASE_Questions::count_active() >= NAASE_QUESTIONS_PER_ATTEMPT;

		// Config is emitted inline within the shortcode output (JSON_HEX_TAG guards against
		// a "</script>" breakout). This is rendered before challenge.js runs in the footer.
		$config = '<script>window.NAASE_APP = ' . wp_json_encode( self::app_config(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';</script>';

		return $config . NAASE_Templates::get(
			'challenge-app',
			array(
				'settings'    => $settings,
				'features'    => NAASE_Settings::features(),
				'leaderboard' => NAASE_Rewrites::leaderboard_url(),
				'enough'      => $enough,
			)
		);
	}

	/**
	 * [naase_leaderboard]
	 *
	 * @return string
	 */
	public static function leaderboard() {
		wp_enqueue_style( 'naase-challenge' );

		$paged = isset( $_GET['lb_page'] ) ? max( 1, (int) $_GET['lb_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$data  = NAASE_Leaderboard::get_page( $paged, 10 );

		return NAASE_Templates::get(
			'leaderboard',
			array(
				'data'         => $data,
				'challengeUrl' => NAASE_Rewrites::challenge_url(),
			)
		);
	}
}
