<?php
/**
 * Editable settings (Options API).
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Settings {

	const OPTION = 'naase_settings';

	/** @var array|null Per-request memoised merged settings. */
	private static $cache = null;

	/**
	 * Default editable settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'challenge_title'    => 'NAASE Sales Engineering Basics Challenge',
			'challenge_desc'     => 'Test your knowledge of the fundamentals every great Sales Engineer should know. 12 questions, one hour, one badge to earn.',
			'feature_1'          => '12 questions drawn at random',
			'feature_2'          => 'Covers the core SE knowledge areas',
			'feature_3'          => 'Earn a shareable badge',
			'feature_4'          => 'Climb the public leaderboard',
			'timeout_title'      => 'Still there?',
			'timeout_text'       => "This session has been open for over an hour, so it’s no longer active.\n\nYou can start again when you’re ready or view the Leaderboard.",
			'post_completion'    => 'Enter your information to receive your detailed results, a personalized breakdown, and your digital badge:',
			'share_text'         => 'I just scored {score}/12 ({tier}) on the NAASE SE Basics Challenge!',
			'privacy_text'       => 'We respect your privacy. Your information is used only to deliver your results and, if you opt in, to display you on the leaderboard.',
			'zapier_webhook_url' => '',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved        = get_option( self::OPTION, array() );
			self::$cache  = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if not set.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist the full settings array (already sanitised).
	 *
	 * @param array $values Settings.
	 */
	public static function update( array $values ) {
		update_option( self::OPTION, $values );
		self::$cache = null;
	}

	/**
	 * The four landing-page features as a clean list.
	 *
	 * @return string[]
	 */
	public static function features() {
		$all = self::all();
		return array_values(
			array_filter(
				array(
					$all['feature_1'],
					$all['feature_2'],
					$all['feature_3'],
					$all['feature_4'],
				),
				static function ( $f ) {
					return '' !== trim( (string) $f );
				}
			)
		);
	}
}
