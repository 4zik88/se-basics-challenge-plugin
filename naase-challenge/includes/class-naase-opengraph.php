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
	 * @param array $row Attempt row.
	 */
	public static function render( array $row ) {
		$summary = NAASE_Attempts::result_summary( $row );
		$name    = trim( $row['first_name'] . ' ' . $row['last_name'] );
		$name    = '' !== $name ? $name : 'A challenger';

		$share = NAASE_Settings::get( 'share_text' );
		$share = self::interpolate( $share, $summary );

		$title       = sprintf( '%s — %s', $summary['tier'], NAASE_Settings::get( 'challenge_title' ) );
		$description = $share;
		$image       = NAASE_Badge::url( $summary['tier_key'] );
		$url         = $summary['result_url'];

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
	 * Replace {score}, {total}, {tier}, {time}, {name} tokens in share text.
	 *
	 * @param string $text    Template.
	 * @param array  $summary Result summary.
	 * @return string
	 */
	public static function interpolate( $text, array $summary ) {
		return strtr(
			(string) $text,
			array(
				'{score}' => (string) $summary['score'],
				'{total}' => (string) $summary['total'],
				'{tier}'  => $summary['tier'],
				'{time}'  => $summary['duration_text'],
			)
		);
	}
}
