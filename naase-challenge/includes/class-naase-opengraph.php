<?php
/**
 * OpenGraph / Twitter card meta for result pages, so shared links show the badge.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_OpenGraph {

	/**
	 * Print meta tags for a given attempt result row. Called from the result template head.
	 *
	 * @param array       $row           Attempt row.
	 * @param string|null $canonical_url Override for og:url (e.g. the share endpoint). Defaults to the result URL.
	 */
	public static function render( array $row, $canonical_url = null ) {
		$summary = NAASE_Attempts::result_summary( $row );
		$name    = trim( $row['first_name'] . ' ' . $row['last_name'] );
		$name    = '' !== $name ? $name : 'A challenger';

		$share = NAASE_Settings::get( 'share_text' );
		$share = self::interpolate( $share, $summary );

		$title       = sprintf( '%s — %s', $summary['tier'], NAASE_Settings::get( 'challenge_title' ) );
		$description = $share;
		$image       = NAASE_Badge::url( $summary['tier_key'] );
		$url         = $canonical_url ? $canonical_url : $summary['result_url'];

		$tags = array(
			array( 'property' => 'og:type', 'content' => 'website' ),
			array( 'property' => 'og:title', 'content' => $title ),
			array( 'property' => 'og:description', 'content' => $description ),
			array( 'property' => 'og:url', 'content' => $url ),
			array( 'property' => 'og:image', 'content' => $image ),
			array( 'property' => 'og:image:width', 'content' => (string) NAASE_Badge::WIDTH ),
			array( 'property' => 'og:image:height', 'content' => (string) NAASE_Badge::HEIGHT ),
			array( 'name' => 'twitter:card', 'content' => 'summary_large_image' ),
			array( 'name' => 'twitter:title', 'content' => $title ),
			array( 'name' => 'twitter:description', 'content' => $description ),
			array( 'name' => 'twitter:image', 'content' => $image ),
		);

		foreach ( $tags as $tag ) {
			$attr = isset( $tag['property'] ) ? 'property' : 'name';
			$key  = isset( $tag['property'] ) ? $tag['property'] : $tag['name'];
			printf(
				'<meta %1$s="%2$s" content="%3$s" />' . "\n",
				esc_attr( $attr ),
				esc_attr( $key ),
				esc_attr( $tag['content'] )
			);
		}
	}

	/**
	 * Replace {score}, {total}, {tier}, {time}, {percent}, {time_percent}, {ordinal}
	 * tokens in share text.
	 *
	 * @param string $text    Template.
	 * @param array  $summary Result summary.
	 * @return string
	 */
	public static function interpolate( $text, array $summary ) {
		$percent      = (int) round( $summary['score'] / max( 1, $summary['total'] ) * 100 );
		$time_percent = (int) round( (int) $summary['duration'] / max( 1, NAASE_ALLOWED_SECONDS ) * 100 );
		$ordinals     = array(
			1 => __( 'First', 'naase-challenge' ),
			2 => __( 'Second', 'naase-challenge' ),
			3 => __( 'Third', 'naase-challenge' ),
			4 => __( 'Fourth', 'naase-challenge' ),
		);
		$index   = NAASE_Scoring::tier_index( $summary['tier_key'] );
		$ordinal = isset( $ordinals[ $index ] ) ? $ordinals[ $index ] : '';

		return strtr(
			(string) $text,
			array(
				'{score}'        => (string) $summary['score'],
				'{total}'        => (string) $summary['total'],
				'{tier}'         => $summary['tier'],
				'{time}'         => $summary['duration_text'],
				'{percent}'      => $percent . '%',
				'{time_percent}' => $time_percent . '%',
				'{ordinal}'      => $ordinal,
			)
		);
	}
}
