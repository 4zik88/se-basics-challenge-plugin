<?php
/**
 * Leaderboard table + pagination.
 *
 * @var array $args { data, challengeUrl }
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

$data         = $args['data'];
$challenge    = $args['challengeUrl'];
$items        = $data['items'];
$paged        = (int) $data['paged'];
$pages        = (int) $data['pages'];

$page_link = static function ( $p ) {
	return esc_url( add_query_arg( 'lb_page', max( 1, (int) $p ) ) );
};
?>
<div class="naase-app naase-leaderboard">
	<section class="naase-card naase-screen is-active">
		<div class="naase-lb-head">
			<div>
				<h1 class="naase-title-leaderboard naase-title--sm"><?php esc_html_e( 'SE Basics Challenge Leaderboard', 'naase-challenge' ); ?></h1>
				<p class="naase-lb-sub"><?php esc_html_e( 'See how participants rank by score and completion time in the NAASE Sales Engineering Basics Challenge.', 'naase-challenge' ); ?></p>
			</div>
			<a class="naase-btn leaderboard naase-btn--primary naase-btn--sm" href="<?php echo esc_url( $challenge ); ?>">
				<?php esc_html_e( 'Challenge Yourself', 'naase-challenge' ); ?>
				<svg class="naase-btn-ico naase-btn-ico--after" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4.16602 9.99935H15.8327M9.99935 15.8327L15.8327 9.99935L9.99935 4.16602" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</a>
		</div>

		<?php if ( empty( $items ) ) : ?>
			<p class="naase-lb-empty"><?php esc_html_e( 'No results yet — be the first on the board!', 'naase-challenge' ); ?></p>
		<?php else : ?>
			<div class="naase-lb-table-wrap">
				<table class="naase-lb-table">
					<thead>
						<tr>
							<th class="naase-col-rank"><?php esc_html_e( 'Rank', 'naase-challenge' ); ?></th>
							<th><?php esc_html_e( 'Name', 'naase-challenge' ); ?></th>
							<th><?php esc_html_e( 'Score', 'naase-challenge' ); ?></th>
							<th><?php esc_html_e( 'Tier', 'naase-challenge' ); ?></th>
							<th><?php esc_html_e( 'Completion time', 'naase-challenge' ); ?></th>
							<th><?php esc_html_e( 'Date', 'naase-challenge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $row ) : ?>
							<tr>
								<td class="naase-col-rank value"><?php echo (int) $row['rank']; ?></td>
								<td class="naase-col-name"><?php echo esc_html( trim( $row['first_name'] . ' ' . $row['last_name'] ) ); ?></td>
								<td class="naase-col-score"><?php echo (int) $row['score']; ?>/<?php echo (int) NAASE_QUESTIONS_PER_ATTEMPT; ?></td>
								<td class="naase-col-tier"><?php echo esc_html( $row['tier'] ); ?></td>
								<td class="naase-col-duration"><?php echo esc_html( NAASE_Scoring::format_duration_long( (int) $row['duration_seconds'] ) ); ?></td>
								<td class="naase-col-date"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $row['finished_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $pages > 1 ) : ?>
				<nav class="naase-pagination">
					<?php if ( $paged > 1 ) : ?>
						<a class="naase-page naase-page--step" href="<?php echo $page_link( $paged - 1 ); // phpcs:ignore ?>"><span class="naase-page-arrow" aria-hidden="true"><svg class="naase-page-ico" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span> <?php esc_html_e( 'Previous', 'naase-challenge' ); ?></a>
						<?php else : ?>
							<span class="naase-page naase-page--step is-disabled"><span class="naase-page-arrow" aria-hidden="true"><svg class="naase-page-ico" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span> <?php esc_html_e( 'Previous', 'naase-challenge' ); ?></span>
					<?php endif; ?>
					<?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
						<a class="naase-page <?php echo $p === $paged ? 'is-current' : ''; ?>" href="<?php echo $page_link( $p ); // phpcs:ignore ?>"><?php echo (int) $p; ?></a>
					<?php endfor; ?>
					<?php if ( $paged < $pages ) : ?>
						<a class="naase-page naase-page--step" href="<?php echo $page_link( $paged + 1 ); // phpcs:ignore ?>"><?php esc_html_e( 'Next', 'naase-challenge' ); ?> <span class="naase-page-arrow" aria-hidden="true"><svg class="naase-page-ico" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
						<?php else : ?>
							<span class="naase-page naase-page--step is-disabled"><?php esc_html_e( 'Next', 'naase-challenge' ); ?> <span class="naase-page-arrow" aria-hidden="true"><svg class="naase-page-ico" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></span>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</div>
<?php
