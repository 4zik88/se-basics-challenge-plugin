<?php
/**
 * Score and tier rules (fixed per spec).
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Scoring {

	/**
	 * The single source of truth for tiers, in ascending order. Each entry:
	 *   key    — short slug (badge filename / CSS / ordinal lookups)
	 *   label  — display label
	 *   min    — minimum score (out of 12) to reach this tier
	 *   accent — RGB accent colour for the badge
	 *
	 * tier(), tier_key(), tier_index(), tier_accent() all derive from this list so a tier
	 * change is made in one place.
	 *
	 * @return array[]
	 */
	public static function tiers() {
		return array(
			array( 'key' => 'explorer', 'label' => 'SE Basics Explorer', 'min' => 0, 'accent' => array( 56, 189, 248 ) ),
			array( 'key' => 'builder', 'label' => 'SE Basics Builder', 'min' => 6, 'accent' => array( 96, 165, 250 ) ),
			array( 'key' => 'ready', 'label' => 'SE Basics Ready', 'min' => 9, 'accent' => array( 52, 211, 153 ) ),
			array( 'key' => 'ace', 'label' => 'SE Basics Ace', 'min' => 11, 'accent' => array( 250, 204, 21 ) ),
		);
	}

	/**
	 * The tier entry for a given score (highest tier whose min is met).
	 *
	 * @param int $score Number of correct answers.
	 * @return array Tier entry.
	 */
	private static function tier_for_score( $score ) {
		$score = (int) $score;
		$match = self::tiers()[0];
		foreach ( self::tiers() as $tier ) {
			if ( $score >= $tier['min'] ) {
				$match = $tier;
			}
		}
		return $match;
	}

	/**
	 * The tier entry for a given key (falls back to the lowest tier).
	 *
	 * @param string $key Tier key.
	 * @return array Tier entry.
	 */
	private static function tier_by_key( $key ) {
		foreach ( self::tiers() as $tier ) {
			if ( $tier['key'] === $key ) {
				return $tier;
			}
		}
		return self::tiers()[0];
	}

	/**
	 * Map a score (0-12) to its tier label.
	 *
	 * @param int $score Number of correct answers.
	 * @return string Tier label.
	 */
	public static function tier( $score ) {
		return self::tier_for_score( $score )['label'];
	}

	/**
	 * Short tier key (used for badge image filename / styling).
	 *
	 * @param string $tier Tier label.
	 * @return string
	 */
	public static function tier_key( $tier ) {
		foreach ( self::tiers() as $entry ) {
			if ( $entry['label'] === $tier ) {
				return $entry['key'];
			}
		}
		return self::tiers()[0]['key'];
	}

	/**
	 * 1-based position of a tier in the ordered list (e.g. "Third tier out of four").
	 *
	 * @param string $key Tier key.
	 * @return int
	 */
	public static function tier_index( $key ) {
		foreach ( self::tiers() as $i => $entry ) {
			if ( $entry['key'] === $key ) {
				return $i + 1;
			}
		}
		return 1;
	}

	/**
	 * Total number of tiers.
	 *
	 * @return int
	 */
	public static function tier_count() {
		return count( self::tiers() );
	}

	/**
	 * RGB accent colour for a tier key.
	 *
	 * @param string $key Tier key.
	 * @return int[] [r, g, b]
	 */
	public static function tier_accent( $key ) {
		return self::tier_by_key( $key )['accent'];
	}

	/**
	 * Long, human duration like "4 min 18 sec" (used on result + leaderboard screens).
	 * The short ticking M:SS timer lives only client-side (challenge.js).
	 *
	 * @param int|null $seconds Duration.
	 * @return string
	 */
	public static function format_duration_long( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$h       = (int) floor( $seconds / 3600 );
		$m       = (int) floor( ( $seconds % 3600 ) / 60 );
		$s       = $seconds % 60;

		$parts = array();
		if ( $h > 0 ) {
			/* translators: %d: hours */
			$parts[] = sprintf( _n( '%d hr', '%d hr', $h, 'naase-challenge' ), $h );
		}
		if ( $m > 0 || $h > 0 ) {
			/* translators: %d: minutes */
			$parts[] = sprintf( __( '%d min', 'naase-challenge' ), $m );
		}
		/* translators: %d: seconds */
		$parts[] = sprintf( __( '%d sec', 'naase-challenge' ), $s );

		return implode( ' ', $parts );
	}
}
