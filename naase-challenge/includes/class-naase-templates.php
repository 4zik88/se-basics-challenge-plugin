<?php
/**
 * Tiny template loader for public-facing markup.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Templates {

	/**
	 * Render a template from public/templates/{name}.php.
	 *
	 * @param string $name Template name.
	 * @param array  $args Variables exposed to the template as $args.
	 */
	public static function render( $name, array $args = array() ) {
		$file = NAASE_PLUGIN_DIR . 'public/templates/' . sanitize_file_name( $name ) . '.php';
		if ( ! file_exists( $file ) ) {
			return;
		}
		include $file;
	}

	/**
	 * Capture a template's output as a string.
	 *
	 * @param string $name Template name.
	 * @param array  $args Variables.
	 * @return string
	 */
	public static function get( $name, array $args = array() ) {
		ob_start();
		self::render( $name, $args );
		return ob_get_clean();
	}
}
