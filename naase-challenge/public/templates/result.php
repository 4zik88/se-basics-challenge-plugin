<?php
/**
 * Final result / share screen (server-rendered at /naase-result/{token}/).
 *
 * @var array $args { summary }
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

$s          = $args['summary'];
$name       = trim( $s['first_name'] . ' ' . $s['last_name'] );
$name       = '' !== $name ? $name : __( 'there', 'naase-challenge' );
$badge_url  = NAASE_Badge::url( $s['tier_key'] );
$result_url = $s['result_url'];
$leaderboard = NAASE_Rewrites::leaderboard_url();

$share_caption = NAASE_OpenGraph::interpolate( NAASE_Settings::get( 'share_text' ), $s );

// Shared link points at the share endpoint: crawlers get the personalised badge + caption
// card, while humans who click are redirected to the challenge start (see NAASE_Rewrites).
$share_url = add_query_arg( 'share', '1', $result_url );

$linkedin = 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $share_url );
$facebook = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $share_url );
$twitter  = 'https://twitter.com/intent/tweet?text=' . rawurlencode( $share_caption ) . '&url=' . rawurlencode( $share_url );
?>
<div class="naase-app naase-result">
	<section class="naase-card naase-screen is-active">
		<div class="naase-screen-inner naase-text-center">

			<h1 class="naase-title-result naase-title--md">
				<?php
				/* translators: %s: participant name */
				printf( esc_html__( 'Thank you, %s! 🎉', 'naase-challenge' ), esc_html( $name ) );
				?>
			</h1>
			<p class="naase-lead-result">
				<?php
				printf(
					/* translators: %s: challenge name */
					esc_html__( 'Great job completing the %s.', 'naase-challenge' ),
					esc_html( NAASE_Settings::get( 'challenge_title' ) )
				);
				?><br><?php esc_html_e( 'Here are your results — share them with your network!', 'naase-challenge' ); ?>
			</p>

			<div class="naase-share">
				<div class="naase-share-head"><?php esc_html_e( 'Share your result', 'naase-challenge' ); ?></div>
				<div class="naase-share-body">
					<div class="naase-share-badge">
						<img src="<?php echo esc_url( $badge_url ); ?>" alt="<?php echo esc_attr( $s['tier'] ); ?>" loading="lazy" />
					</div>
					<div class="naase-share-text">
						<p><?php echo nl2br( esc_html( $share_caption ) ); ?></p>
						<a class="naase-share-link" href="<?php echo esc_url( $result_url ); ?>"><?php echo esc_html( $result_url ); ?></a>
					</div>
				</div>

				<div class="naase-share-actions">
					<div class="naase-share-social">
						<a class="naase-share-btn" href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener">
							<svg class="naase-share-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="24" height="24" rx="4" fill="#0077B5"/><path d="M8.92367 17.2407C8.92381 17.2939 8.91338 17.3467 8.89297 17.3959C8.87256 17.4451 8.84256 17.4899 8.80471 17.5276C8.76686 17.5653 8.72189 17.5952 8.67239 17.6156C8.62288 17.636 8.5698 17.6465 8.5162 17.6465H6.77979C6.7261 17.6466 6.6729 17.6362 6.62327 17.6159C6.57363 17.5955 6.52854 17.5657 6.49057 17.5279C6.4526 17.4902 6.42251 17.4454 6.40203 17.3961C6.38155 17.3468 6.37108 17.294 6.37123 17.2407V10.0101C6.37123 9.90249 6.41427 9.79927 6.49089 9.72316C6.56751 9.64706 6.67143 9.6043 6.77979 9.6043H8.5162C8.62437 9.60459 8.72801 9.64747 8.80439 9.72355C8.88077 9.79962 8.92367 9.90268 8.92367 10.0101V17.2407ZM7.64745 8.91921C7.32161 8.91921 7.0031 8.82324 6.73217 8.64344C6.46125 8.46363 6.2501 8.20806 6.12541 7.90906C6.00071 7.61005 5.96809 7.28103 6.03166 6.96361C6.09522 6.64619 6.25213 6.35461 6.48253 6.12577C6.71293 5.89692 7.00647 5.74107 7.32605 5.67793C7.64562 5.61479 7.97687 5.64719 8.2779 5.77105C8.57893 5.8949 8.83622 6.10464 9.01725 6.37373C9.19827 6.64283 9.29489 6.95921 9.29489 7.28285C9.29489 7.71684 9.12132 8.13305 8.81237 8.43993C8.50341 8.74681 8.08438 8.91921 7.64745 8.91921ZM18 17.269C18.0001 17.3181 17.9905 17.3666 17.9717 17.412C17.9529 17.4573 17.9252 17.4985 17.8903 17.5332C17.8554 17.5678 17.8139 17.5953 17.7683 17.614C17.7227 17.6327 17.6737 17.6423 17.6244 17.6421H15.7573C15.7079 17.6423 15.659 17.6327 15.6134 17.614C15.5677 17.5953 15.5263 17.5678 15.4914 17.5332C15.4564 17.4985 15.4288 17.4573 15.41 17.412C15.3911 17.3666 15.3815 17.3181 15.3817 17.269V13.8818C15.3817 13.3756 15.531 11.665 14.0494 11.665C12.9017 11.665 12.6678 12.8356 12.6216 13.3614V17.2734C12.6216 17.3714 12.5828 17.4655 12.5136 17.5353C12.4443 17.6051 12.3502 17.6451 12.2515 17.6465H10.4481C10.3988 17.6465 10.35 17.6368 10.3045 17.6181C10.259 17.5993 10.2177 17.5718 10.1829 17.5371C10.1481 17.5025 10.1205 17.4613 10.1018 17.4161C10.083 17.3708 10.0734 17.3223 10.0736 17.2734V9.97848C10.0734 9.92954 10.083 9.88105 10.1018 9.83579C10.1205 9.79053 10.1481 9.74939 10.1829 9.71474C10.2177 9.68008 10.259 9.65258 10.3045 9.63381C10.35 9.61505 10.3988 9.60539 10.4481 9.60539H12.2515C12.3511 9.60539 12.4467 9.6447 12.5171 9.71467C12.5876 9.78464 12.6271 9.87953 12.6271 9.97848V10.609C13.0533 9.97303 13.6848 9.4843 15.0324 9.4843C18.0176 9.4843 17.9978 12.253 17.9978 13.7738L18 17.269Z" fill="white"/></svg>
							<span><?php esc_html_e( 'LinkedIn', 'naase-challenge' ); ?></span>
						</a>
						<a class="naase-share-btn" href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener">
							<svg class="naase-share-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g clip-path="url(#nfb)"><path d="M24 12C24 5.37262 18.6274 0 12 0C5.37262 0 0 5.37262 0 12C0 17.9895 4.38825 22.954 10.125 23.8542V15.4688H7.07812V12H10.125V9.35625C10.125 6.34875 11.9166 4.6875 14.6576 4.6875C15.9705 4.6875 17.3438 4.92188 17.3438 4.92188V7.875H15.8306C14.3399 7.875 13.875 8.80003 13.875 9.74906V12H17.2031L16.6711 15.4688H13.875V23.8542C19.6117 22.954 24 17.9896 24 12Z" fill="#1877F2"/><path d="M16.6711 15.4688L17.2031 12H13.875V9.74906C13.875 8.79994 14.3399 7.875 15.8306 7.875H17.3438V4.92188C17.3438 4.92188 15.9705 4.6875 14.6575 4.6875C11.9166 4.6875 10.125 6.34875 10.125 9.35625V12H7.07812V15.4688H10.125V23.8542C10.7453 23.9514 11.3722 24.0001 12 24C12.6278 24.0001 13.2547 23.9514 13.875 23.8542V15.4688H16.6711Z" fill="white"/></g><defs><clipPath id="nfb"><rect width="24" height="24" fill="white"/></clipPath></defs></svg>
							<span><?php esc_html_e( 'Facebook', 'naase-challenge' ); ?></span>
						</a>
						<a class="naase-share-btn" href="<?php echo esc_url( $twitter ); ?>" target="_blank" rel="noopener">
							<svg class="naase-share-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g clip-path="url(#ntw)"><path d="M14.2532 10.1624L23.1663 0H21.0541L13.3148 8.82375L7.13337 0H0.00390625L9.35144 13.3433L0.00390625 24H2.11627L10.2892 14.6817L16.8173 24H23.9467L14.2526 10.1624H14.2532ZM11.36 13.4606L10.4129 12.132L2.8772 1.55953H6.12153L12.2029 10.0918L13.1501 11.4205L21.0552 22.5112H17.8108L11.36 13.4606Z" fill="black"/></g><defs><clipPath id="ntw"><rect width="24" height="24" fill="white"/></clipPath></defs></svg>
							<span><?php esc_html_e( 'Twitter', 'naase-challenge' ); ?></span>
						</a>
					</div>
					<a class="naase-share-btn naase-share-btn--download" href="<?php echo esc_url( add_query_arg( 'download', 'badge', $result_url ) ); ?>">
						<svg class="naase-share-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21 13.5V19.5C21 19.6989 20.921 19.8897 20.7803 20.0303C20.6397 20.171 20.4489 20.25 20.25 20.25H3.75C3.55109 20.25 3.36032 20.171 3.21967 20.0303C3.07902 19.8897 3 19.6989 3 19.5V13.5C3 13.3011 3.07902 13.1103 3.21967 12.9697C3.36032 12.829 3.55109 12.75 3.75 12.75C3.94891 12.75 4.13968 12.829 4.28033 12.9697C4.42098 13.1103 4.5 13.3011 4.5 13.5V18.75H19.5V13.5C19.5 13.3011 19.579 13.1103 19.7197 12.9697C19.8603 12.829 20.0511 12.75 20.25 12.75C20.4489 12.75 20.6397 12.829 20.7803 12.9697C20.921 13.1103 21 13.3011 21 13.5ZM11.4694 14.0306C11.539 14.1004 11.6217 14.1557 11.7128 14.1934C11.8038 14.2312 11.9014 14.2506 12 14.2506C12.0986 14.2506 12.1962 14.2312 12.2872 14.1934C12.3783 14.1557 12.461 14.1004 12.5306 14.0306L16.2806 10.2806C16.4214 10.1399 16.5004 9.94905 16.5004 9.75C16.5004 9.55095 16.4214 9.36011 16.2806 9.21937C16.1399 9.07864 15.949 8.99958 15.75 8.99958C15.551 8.99958 15.3601 9.07864 15.2194 9.21937L12.75 11.6897V3C12.75 2.80109 12.671 2.61032 12.5303 2.46967C12.3897 2.32902 12.1989 2.25 12 2.25C11.8011 2.25 11.6103 2.32902 11.4697 2.46967C11.329 2.61032 11.25 2.80109 11.25 3V11.6897L8.78063 9.21937C8.63989 9.07864 8.44902 8.99958 8.25 8.99958C8.05098 8.99958 7.86011 9.07864 7.71937 9.21937C7.57864 9.36011 7.49958 9.55098 7.49958 9.75C7.49958 9.94902 7.57864 10.1399 7.71937 10.2806L11.4694 14.0306Z" fill="#06509E"/></svg>
						<span><?php esc_html_e( 'Download badge', 'naase-challenge' ); ?></span>
					</a>
				</div>
			</div>

			<div class="naase-actions">
				<a class="naase-btn naase-btn--ghost" href="<?php echo esc_url( $leaderboard ); ?>">
					<svg class="naase-btn-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21.75 6H19.5V4.5C19.5 4.30109 19.421 4.11032 19.2803 3.96967C19.1397 3.82902 18.9489 3.75 18.75 3.75H5.25C5.05109 3.75 4.86032 3.82902 4.71967 3.96967C4.57902 4.11032 4.5 4.30109 4.5 4.5V6H2.25C1.85218 6 1.47064 6.15804 1.18934 6.43934C0.908035 6.72064 0.75 7.10218 0.75 7.5V9C0.75 9.99456 1.14509 10.9484 1.84835 11.6517C2.19657 11.9999 2.60997 12.2761 3.06494 12.4645C3.51991 12.653 4.00754 12.75 4.5 12.75H4.84219C5.28398 14.1501 6.12634 15.39 7.26516 16.3166C8.40398 17.2431 9.78933 17.8157 11.25 17.9634V20.25H9C8.80109 20.25 8.61032 20.329 8.46967 20.4697C8.32902 20.6103 8.25 20.8011 8.25 21C8.25 21.1989 8.32902 21.3897 8.46967 21.5303C8.61032 21.671 8.80109 21.75 9 21.75H15C15.1989 21.75 15.3897 21.671 15.5303 21.5303C15.671 21.3897 15.75 21.1989 15.75 21C15.75 20.8011 15.671 20.6103 15.5303 20.4697C15.3897 20.329 15.1989 20.25 15 20.25H12.75V17.9606C15.7444 17.6578 18.2288 15.5569 19.1325 12.75H19.5C20.4946 12.75 21.4484 12.3549 22.1516 11.6517C22.8549 10.9484 23.25 9.99456 23.25 9V7.5C23.25 7.10218 23.092 6.72064 22.8107 6.43934C22.5294 6.15804 22.1478 6 21.75 6ZM4.5 11.25C3.90326 11.25 3.33097 11.0129 2.90901 10.591C2.48705 10.169 2.25 9.59674 2.25 9V7.5H4.5V10.5C4.5 10.75 4.51219 11 4.53656 11.25H4.5ZM18 10.4156C18 13.7456 15.2812 16.4756 12 16.5C10.4087 16.5 8.88258 15.8679 7.75736 14.7426C6.63214 13.6174 6 12.0913 6 10.5V5.25H18V10.4156ZM21.75 9C21.75 9.59674 21.5129 10.169 21.091 10.591C20.669 11.0129 20.0967 11.25 19.5 11.25H19.4531C19.4839 10.9729 19.4995 10.6944 19.5 10.4156V7.5H21.75V9Z" fill="#06509E"/></svg>
					<?php esc_html_e( 'See the Leaderboard', 'naase-challenge' ); ?>
				</a>
			</div>
		</div>
	</section>
</div>
<?php
