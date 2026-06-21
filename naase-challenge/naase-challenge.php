<?php
/**
 * Plugin Name:       NAASE SE Basics Challenge
 * Description:        Sales Engineering Basics Challenge — a 12-question timed quiz with scoring, tiers, badges, social sharing, a leaderboard and Zapier notifications. Renders via the [naase_challenge] shortcode.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Viacheslav Tykhenkyi
 * Author URI:        https://freelancehunt.com/freelancer/Fozikk.html
 * Text Domain:       naase-challenge
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

define( 'NAASE_VERSION', '1.0.0' );
define( 'NAASE_PLUGIN_FILE', __FILE__ );
define( 'NAASE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAASE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NAASE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Fixed business rules (per spec — not editable in admin).
define( 'NAASE_QUESTIONS_PER_ATTEMPT', 12 );
define( 'NAASE_TIMEOUT_SECONDS', HOUR_IN_SECONDS ); // Session is no longer active after 1 hour.
define( 'NAASE_ALLOWED_SECONDS', 5 * MINUTE_IN_SECONDS ); // Target completion time used for the "% of allowed time" stat.

require_once NAASE_PLUGIN_DIR . 'includes/class-naase-db.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-templates.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-settings.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-scoring.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-questions.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-attempts.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-leaderboard.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-badge.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-zapier.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-rest.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-rewrites.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-opengraph.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-shortcodes.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-activator.php';
require_once NAASE_PLUGIN_DIR . 'includes/class-naase-plugin.php';

if ( is_admin() ) {
	require_once NAASE_PLUGIN_DIR . 'admin/class-naase-admin.php';
}

register_activation_hook( __FILE__, array( 'NAASE_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NAASE_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'NAASE_Plugin', 'instance' ) );
