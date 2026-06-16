<?php
/**
 * Tier badge images. Each tier has one fixed badge artwork bundled with the plugin
 * (assets/badges/{tier_key}.png), used both as the share/OG image and the download.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Badge {

	const WIDTH  = 1080;
	const HEIGHT = 1080;

	/**
	 * Absolute file path for a tier's badge artwork.
	 *
	 * @param string $tier_key Tier key (explorer|builder|ready|ace).
	 * @return string
	 */
	public static function path( $tier_key ) {
		return NAASE_PLUGIN_DIR . 'assets/badges/' . self::sanitize_key( $tier_key ) . '.png';
	}

	/**
	 * Public URL for a tier's badge artwork.
	 *
	 * @param string $tier_key Tier key (explorer|builder|ready|ace).
	 * @return string
	 */
	public static function url( $tier_key ) {
		return NAASE_PLUGIN_URL . 'assets/badges/' . self::sanitize_key( $tier_key ) . '.png';
	}

	/**
	 * The badge file path for an attempt row's tier (artwork is bundled, never generated).
	 *
	 * @param array $row Attempt row.
	 * @return string|false File path or false.
	 */
	public static function ensure( $row ) {
		if ( ! $row || empty( $row['tier'] ) && ! isset( $row['score'] ) ) {
			return false;
		}
		$tier = ! empty( $row['tier'] ) ? $row['tier'] : NAASE_Scoring::tier( (int) $row['score'] );
		$path = self::path( NAASE_Scoring::tier_key( $tier ) );
		return file_exists( $path ) ? $path : false;
	}

	/**
	 * Validate a tier key against the known tiers, falling back to the lowest.
	 *
	 * @param string $tier_key Candidate key.
	 * @return string
	 */
	private static function sanitize_key( $tier_key ) {
		foreach ( NAASE_Scoring::tiers() as $tier ) {
			if ( $tier['key'] === $tier_key ) {
				return $tier_key;
			}
		}
		return NAASE_Scoring::tiers()[0]['key'];
	}

	/**
	 * No-op kept for backwards compatibility — tier artwork is shared and bundled,
	 * so individual attempts never own a deletable badge file.
	 *
	 * @param string $token Token (unused).
	 */
	public static function delete( $token ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}
