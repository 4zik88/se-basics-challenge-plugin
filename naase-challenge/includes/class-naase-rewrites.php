<?php
/**
 * Public pretty URLs:
 *   /naase-result/{token}/   → per-attempt result page (with OG tags + badge)
 *   /naase-result/{token}/?download=badge → streams the badge PNG as a download
 *   /naase-leaderboard/      → leaderboard (fallback if no page hosts the shortcode)
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Rewrites {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * Register rewrite rules.
	 */
	public static function add_rules() {
		add_rewrite_rule( '^naase-result/([^/]+)/?$', 'index.php?naase_result=$matches[1]', 'top' );
		add_rewrite_rule( '^naase-leaderboard/?$', 'index.php?naase_leaderboard=1', 'top' );
	}

	/**
	 * Register query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'naase_result';
		$vars[] = 'naase_leaderboard';
		return $vars;
	}

	/**
	 * Render the virtual pages.
	 */
	public static function maybe_render() {
		$token = get_query_var( 'naase_result' );
		if ( $token ) {
			self::render_result( (string) $token );
			exit;
		}
		if ( get_query_var( 'naase_leaderboard' ) ) {
			self::render_leaderboard();
			exit;
		}
	}

	/**
	 * Render a single result page (theme-wrapped) or stream the badge download.
	 *
	 * @param string $token Token.
	 */
	private static function render_result( $token ) {
		$row = NAASE_Attempts::get_row( $token );
		$valid = $row && in_array( $row['status'], array( 'completed' ), true );

		// Badge download.
		if ( $valid && isset( $_GET['download'] ) && 'badge' === $_GET['download'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$path = NAASE_Badge::ensure( $row );
			if ( $path && file_exists( $path ) ) {
				$name = sanitize_file_name( 'naase-badge-' . NAASE_Scoring::tier_key( $row['tier'] ) . '.png' );
				header( 'Content-Type: image/png' );
				header( 'Content-Disposition: attachment; filename="' . $name . '"' );
				header( 'Content-Length: ' . filesize( $path ) );
				readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				exit;
			}
		}

		status_header( $valid ? 200 : 404 );

		if ( $valid ) {
			NAASE_Badge::ensure( $row );
			$title   = NAASE_Settings::get( 'challenge_title' );
			$content = NAASE_Templates::get( 'result', array( 'summary' => NAASE_Attempts::result_summary( $row ) ) );
			self::render_page( $title, $content, $row );
		} else {
			$content = NAASE_Templates::get( 'not-found', array() );
			self::render_page( __( 'Result not found', 'naase-challenge' ), $content );
		}
	}

	/**
	 * Render the leaderboard fallback page (standalone).
	 */
	private static function render_leaderboard() {
		self::render_page( __( 'Leaderboard', 'naase-challenge' ), do_shortcode( '[naase_leaderboard]' ) );
	}

	/**
	 * Output a self-contained HTML page for plugin rewrite endpoints.
	 *
	 * The site's active theme may be a block theme without header.php/footer.php, so we
	 * render our own minimal document and load the plugin's own styles directly. This keeps
	 * the result / leaderboard pages styled and theme-independent.
	 *
	 * @param string     $title   Page title.
	 * @param string     $content Body HTML.
	 * @param array|null $og_row  Attempt row for OpenGraph tags (result page only).
	 */
	private static function render_page( $title, $content, $og_row = null ) {
		$css         = NAASE_PLUGIN_URL . 'public/css/challenge.css?ver=' . NAASE_VERSION;
		$fonts_title = 'https://fonts.googleapis.com/css2?family=League+Gothic&display=swap';
		$fonts_body  = 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap';
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
		<?php
		if ( $og_row ) {
			NAASE_OpenGraph::render( $og_row );
		}
		?>
	<link rel="stylesheet" href="<?php echo esc_url( $fonts_title ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( $fonts_body ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
	<style>body{margin:0;background:#f4f8fc;font-family:"Roboto",-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;}</style>
</head>
<body class="naase-standalone-body">
		<?php
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- composed from escaped templates.
		?>
</body>
</html>
		<?php
	}

	/**
	 * URL of the leaderboard: prefer a page that hosts the shortcode, else the rewrite URL.
	 *
	 * @return string
	 */
	public static function leaderboard_url() {
		$page = self::find_page_with_shortcode( 'naase_leaderboard' );
		return $page ? get_permalink( $page ) : home_url( '/naase-leaderboard/' );
	}

	/**
	 * URL of the challenge: a page that hosts [naase_challenge], else home.
	 *
	 * @return string
	 */
	public static function challenge_url() {
		$page = self::find_page_with_shortcode( 'naase_challenge' );
		return $page ? get_permalink( $page ) : home_url( '/' );
	}

	/**
	 * Find the ID of a published page containing a given shortcode (cached).
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return int 0 if none.
	 */
	private static function find_page_with_shortcode( $shortcode ) {
		$cache_key = 'naase_page_' . $shortcode;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%[' . $wpdb->esc_like( $shortcode ) . '%'
			)
		);
		set_transient( $cache_key, $id, HOUR_IN_SECONDS );
		return $id;
	}
}
