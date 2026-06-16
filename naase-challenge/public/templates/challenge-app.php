<?php
/**
 * Challenge app shell. The start + timeout screens are server-rendered; the question
 * and contact-form screens are built by challenge.js. After the form is submitted the
 * browser is redirected to the canonical result URL.
 *
 * @var array $args { settings, features, leaderboard, enough }
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

$settings    = $args['settings'];
$features    = $args['features'];
$leaderboard = $args['leaderboard'];
$enough      = ! empty( $args['enough'] );

$feature_icons = array( 'help', 'tiers', 'badge', 'star' );
?>
<div class="naase-app" id="naase-app">

	<!-- ================= START ================= -->
	<section class="naase-card naase-screen is-active" data-screen="start">
		<div class="naase-screen-inner naase-text-center">
			<h1 class="naase-title"><?php echo esc_html( $settings['challenge_title'] ); ?></h1>
			<p class="naase-lead"><?php echo nl2br( esc_html( $settings['challenge_desc'] ) ); ?></p>

			<?php if ( ! empty( $features ) ) : ?>
				<div class="naase-features">
					<?php foreach ( $features as $i => $feature ) : ?>
						<div class="naase-feature">
							<span class="naase-feature-ico naase-ico-<?php echo esc_attr( $feature_icons[ $i % count( $feature_icons ) ] ); ?>" aria-hidden="true"></span>
							<span class="naase-feature-label"><?php echo esc_html( $feature ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $enough ) : ?>
				<div class="naase-actions">
					<button type="button" class="naase-btn naase-btn--primary" data-action="start">
						<?php esc_html_e( 'Start the Challenge', 'naase-challenge' ); ?>
					</button>
					<a class="naase-btn naase-btn--ghost" href="<?php echo esc_url( $leaderboard ); ?>">
						<?php esc_html_e( 'See the Leaderboard', 'naase-challenge' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="naase-notice">
					<?php esc_html_e( 'This challenge is being prepared. Please check back soon.', 'naase-challenge' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- ============== QUESTION (built by JS) ============== -->
	<section class="naase-card question naase-screen" data-screen="question" hidden></section>

	<!-- ============== CONTACT FORM (built by JS) ============== -->
	<section class="naase-card contact naase-screen" data-screen="form" hidden></section>

	<!-- ================= TIMEOUT ================= -->
	<section class="naase-card naase-screen" data-screen="timeout" hidden>
		<div class="naase-screen-inner naase-text-center">
			<h1 class="naase-title"><?php echo esc_html( $settings['challenge_title'] ); ?></h1>
			<p class="naase-lead">
				<strong><?php echo esc_html( $settings['timeout_title'] ); ?></strong><br>
				<strong><?php echo nl2br( esc_html( $settings['timeout_text'] ) ); ?></strong>
			</p>
			<div class="naase-actions">
				<button type="button" class="naase-btn naase-btn--primary" data-action="start">
					<?php esc_html_e( 'Start the Challenge', 'naase-challenge' ); ?>
				</button>
				<a class="naase-btn naase-btn--ghost" href="<?php echo esc_url( $leaderboard ); ?>">
					<?php esc_html_e( 'See the Leaderboard', 'naase-challenge' ); ?>
				</a>
			</div>
		</div>
	</section>

	<!-- ================= ERROR ================= -->
	<section class="naase-card naase-screen" data-screen="error" hidden>
		<div class="naase-screen-inner naase-text-center">
			<p class="naase-lead naase-error-text"></p>
			<div class="naase-actions">
				<button type="button" class="naase-btn naase-btn--primary" data-action="start">
					<?php esc_html_e( 'Start the Challenge', 'naase-challenge' ); ?>
				</button>
			</div>
		</div>
	</section>

</div>
<?php
