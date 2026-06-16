<?php
/**
 * Invalid / missing result page.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="naase-app">
	<section class="naase-card naase-screen is-active">
		<div class="naase-screen-inner naase-text-center">
			<h1 class="naase-title naase-title--sm"><?php esc_html_e( 'Result not found', 'naase-challenge' ); ?></h1>
			<p class="naase-lead"><?php esc_html_e( 'This result link is invalid or has expired.', 'naase-challenge' ); ?></p>
			<div class="naase-actions">
				<a class="naase-btn naase-btn--primary" href="<?php echo esc_url( NAASE_Rewrites::challenge_url() ); ?>">
					<?php esc_html_e( 'Start the Challenge', 'naase-challenge' ); ?>
				</a>
				<a class="naase-btn naase-btn--ghost" href="<?php echo esc_url( NAASE_Rewrites::leaderboard_url() ); ?>">
					<?php esc_html_e( 'See the Leaderboard', 'naase-challenge' ); ?>
				</a>
			</div>
		</div>
	</section>
</div>
<?php
