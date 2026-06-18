<?php
/**
 * Admin UI: settings, question bank (CRUD + import/export), leaderboard / responses.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'naase-challenge';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		// Form handlers (admin-post.php).
		add_action( 'admin_post_naase_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_naase_save_question', array( __CLASS__, 'handle_save_question' ) );
		add_action( 'admin_post_naase_delete_question', array( __CLASS__, 'handle_delete_question' ) );
		add_action( 'admin_post_naase_import_questions', array( __CLASS__, 'handle_import_questions' ) );
		add_action( 'admin_post_naase_export_questions', array( __CLASS__, 'handle_export_questions' ) );
		add_action( 'admin_post_naase_save_attempt', array( __CLASS__, 'handle_save_attempt' ) );
		add_action( 'admin_post_naase_delete_attempt', array( __CLASS__, 'handle_delete_attempt' ) );
	}

	/* ===================== Menu ===================== */

	public static function menu() {
		add_menu_page( 'NAASE Challenge', 'NAASE Challenge', self::CAP, self::SLUG, array( __CLASS__, 'page_settings' ), 'dashicons-awards', 30 );
		add_submenu_page( self::SLUG, 'Settings', 'Settings', self::CAP, self::SLUG, array( __CLASS__, 'page_settings' ) );
		add_submenu_page( self::SLUG, 'Questions', 'Questions', self::CAP, 'naase-questions', array( __CLASS__, 'page_questions' ) );
		add_submenu_page( self::SLUG, 'Leaderboard & Responses', 'Leaderboard', self::CAP, 'naase-responses', array( __CLASS__, 'page_responses' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'naase' ) ) {
			return;
		}
		wp_enqueue_style( 'naase-admin', NAASE_PLUGIN_URL . 'admin/css/admin.css', array(), NAASE_VERSION );
	}

	private static function nav( $current ) {
		$tabs = array(
			self::SLUG        => 'Settings',
			'naase-questions' => 'Questions',
			'naase-responses' => 'Leaderboard & Responses',
		);
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . $slug ) ),
				$slug === $current ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</h2>';
	}

	private static function notice() {
		if ( isset( $_GET['naase_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$map = array(
				'settings_saved'   => 'Settings saved.',
				'question_saved'   => 'Question saved.',
				'question_deleted' => 'Question deleted.',
				'imported'         => 'Questions imported.',
				'attempt_saved'    => 'Entry updated.',
				'attempt_deleted'  => 'Entry deleted.',
				'error'            => 'Something went wrong.',
			);
			$key = sanitize_key( wp_unslash( $_GET['naase_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $map[ $key ] ) ) {
				$class = 'error' === $key ? 'notice-error' : 'notice-success';
				$text  = $map[ $key ];
				if ( 'imported' === $key ) {
					$imported = isset( $_GET['imported'] ) ? (int) $_GET['imported'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
					$skipped  = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
					$text     = sprintf( 'Imported %d question(s).', $imported );
					if ( $skipped > 0 ) {
						$text .= sprintf( ' Skipped %d duplicate(s).', $skipped );
					}
				}
				printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $text ) );
			}
		}
	}

	private static function pagination( $paged, $pages ) {
		if ( $pages <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => (int) $paged,
				'total'   => (int) $pages,
			)
		);
		echo '</div></div>';
	}

	private static function redirect( $page, $msg, $extra = array() ) {
		$args = array_merge( array( 'page' => $page, 'naase_msg' => $msg ), $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ===================== Settings page ===================== */

	public static function page_settings() {
		$s = NAASE_Settings::all();
		?>
		<div class="wrap naase-admin">
			<h1>NAASE SE Basics Challenge</h1>
			<?php self::nav( self::SLUG ); ?>
			<?php self::notice(); ?>

			<p class="naase-shortcode-hint">
				Place the challenge with the shortcode <code>[naase_challenge]</code> and the standings with <code>[naase_leaderboard]</code>.
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="naase_save_settings">
				<?php wp_nonce_field( 'naase_save_settings' ); ?>

				<h2>Texts</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="challenge_title">Challenge title</label></th>
						<td><input class="regular-text" id="challenge_title" name="challenge_title" value="<?php echo esc_attr( $s['challenge_title'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="challenge_desc">Description (under the title)</label></th>
						<td><textarea class="large-text" rows="3" id="challenge_desc" name="challenge_desc"><?php echo esc_textarea( $s['challenge_desc'] ); ?></textarea></td>
					</tr>
					<tr>
						<th>Feature highlights (start screen)</th>
						<td>
							<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
								<input class="regular-text" style="margin-bottom:6px;" name="feature_<?php echo (int) $i; ?>" value="<?php echo esc_attr( $s[ 'feature_' . $i ] ); ?>" placeholder="Feature <?php echo (int) $i; ?>"><br>
							<?php endfor; ?>
						</td>
					</tr>
					<tr>
						<th><label for="timeout_title">Expired-session heading</label></th>
						<td><input class="regular-text" id="timeout_title" name="timeout_title" value="<?php echo esc_attr( $s['timeout_title'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="timeout_text">Expired-session text</label></th>
						<td>
							<textarea class="large-text" rows="3" id="timeout_text" name="timeout_text"><?php echo esc_textarea( $s['timeout_text'] ); ?></textarea>
							<p class="description">Shown when a returning visitor’s session has been inactive for over an hour. Use blank lines for paragraph breaks.</p>
						</td>
					</tr>
					<tr>
						<th><label for="post_completion">Post-completion text</label></th>
						<td><textarea class="large-text" rows="3" id="post_completion" name="post_completion"><?php echo esc_textarea( $s['post_completion'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="share_text">Share text</label></th>
						<td>
							<textarea class="large-text" rows="4" id="share_text" name="share_text"><?php echo esc_textarea( $s['share_text'] ); ?></textarea>
							<p class="description">Tokens: <code>{score}</code> <code>{total}</code> <code>{tier}</code> <code>{time}</code> <code>{percent}</code> <code>{time_percent}</code> <code>{ordinal}</code></p>
						</td>
					</tr>
					<tr>
						<th><label for="privacy_text">Privacy text</label></th>
						<td><textarea class="large-text" rows="2" id="privacy_text" name="privacy_text"><?php echo esc_textarea( $s['privacy_text'] ); ?></textarea></td>
					</tr>
				</table>

				<h2>Integrations</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="zapier_webhook_url">Zapier webhook URL</label></th>
						<td>
							<input class="regular-text" type="url" id="zapier_webhook_url" name="zapier_webhook_url" value="<?php echo esc_attr( $s['zapier_webhook_url'] ); ?>" placeholder="https://hooks.zapier.com/...">
							<p class="description">A POST with the completion payload is sent here when a participant submits the contact form.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save settings' ); ?>
			</form>
		</div>
		<?php
	}

	/* ===================== Questions page ===================== */

	public static function page_questions() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'edit' === $action || 'new' === $action ) {
			self::render_question_form();
			return;
		}

		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$list   = NAASE_Questions::admin_list(
			array(
				'paged'    => $paged,
				'search'   => $search,
				'per_page' => 20,
			)
		);
		$pages  = (int) ceil( $list['total'] / 20 );
		$active = NAASE_Questions::count_active();
		?>
		<div class="wrap naase-admin">
			<h1 class="wp-heading-inline">Question Bank</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=naase-questions&action=new' ) ); ?>" class="page-title-action">Add Question</a>
			<?php self::nav( 'naase-questions' ); ?>
			<?php self::notice(); ?>

			<p>Total active questions: <strong><?php echo (int) $active; ?></strong>
				<?php if ( $active < NAASE_QUESTIONS_PER_ATTEMPT ) : ?>
					<span style="color:#b32d2e;">— at least <?php echo (int) NAASE_QUESTIONS_PER_ATTEMPT; ?> are required to run the challenge.</span>
				<?php endif; ?>
			</p>

			<div class="naase-import-export">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:inline-block;margin-right:20px;">
					<input type="hidden" name="action" value="naase_import_questions">
					<?php wp_nonce_field( 'naase_import_questions' ); ?>
					<input type="file" name="naase_file" accept=".csv,.json" required>
					<?php submit_button( 'Import CSV / JSON', 'secondary', 'submit', false ); ?>
				</form>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=naase_export_questions&format=csv' ), 'naase_export_questions' ) ); ?>">Export CSV</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=naase_export_questions&format=json' ), 'naase_export_questions' ) ); ?>">Export JSON</a>
			</div>

			<form method="get" style="margin:12px 0;">
				<input type="hidden" name="page" value="naase-questions">
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search questions">
					<?php submit_button( 'Search', '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th>Question</th><th>Knowledge area</th><th>Correct</th><th>Difficulty</th><th>Actions</th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $list['items'] ) ) : ?>
					<tr><td colspan="5">No questions yet.</td></tr>
				<?php else : ?>
					<?php foreach ( $list['items'] as $q ) : ?>
						<tr>
							<td><strong><?php echo esc_html( wp_trim_words( $q['question_text'], 16 ) ); ?></strong></td>
							<td><?php echo esc_html( $q['knowledge_area'] ); ?></td>
							<td><?php echo esc_html( $q['correct_answer'] ); ?></td>
							<td><?php echo (int) $q['difficulty']; ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=naase-questions&action=edit&id=' . (int) $q['id'] ) ); ?>">Edit</a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=naase_delete_question&id=' . (int) $q['id'] ), 'naase_delete_question_' . (int) $q['id'] ) ); ?>" onclick="return confirm('Delete this question?');" style="color:#b32d2e;">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php self::pagination( $paged, $pages ); ?>
		</div>
		<?php
	}

	private static function render_question_form() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$q  = $id ? NAASE_Questions::get( $id ) : null;
		$v  = static function ( $key, $default = '' ) use ( $q ) {
			return $q && isset( $q[ $key ] ) ? $q[ $key ] : $default;
		};
		?>
		<div class="wrap naase-admin">
			<h1><?php echo $id ? 'Edit Question' : 'Add Question'; ?></h1>
			<?php self::nav( 'naase-questions' ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="naase_save_question">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'naase_save_question' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th><label for="question_text">Question text</label></th>
						<td><textarea class="large-text" rows="2" id="question_text" name="question_text" required><?php echo esc_textarea( $v( 'question_text' ) ); ?></textarea></td>
					</tr>
					<?php foreach ( array( 'a', 'b', 'c', 'd' ) as $letter ) : ?>
						<tr>
							<th><label>Answer <?php echo esc_html( strtoupper( $letter ) ); ?></label></th>
							<td><input class="large-text" name="answer_<?php echo esc_attr( $letter ); ?>" value="<?php echo esc_attr( $v( 'answer_' . $letter ) ); ?>" required></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th><label for="correct_answer">Correct answer</label></th>
						<td>
							<select id="correct_answer" name="correct_answer">
								<?php foreach ( array( 'A', 'B', 'C', 'D' ) as $L ) : ?>
									<option value="<?php echo esc_attr( $L ); ?>" <?php selected( $v( 'correct_answer', 'A' ), $L ); ?>><?php echo esc_html( $L ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="knowledge_area">Knowledge area</label></th>
						<td><input class="regular-text" id="knowledge_area" name="knowledge_area" value="<?php echo esc_attr( $v( 'knowledge_area' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="difficulty">Difficulty (1–10)</label></th>
						<td><input type="number" min="1" max="10" id="difficulty" name="difficulty" value="<?php echo esc_attr( $v( 'difficulty', 1 ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="public_note">Public note / helpful context</label></th>
						<td><textarea class="large-text" rows="2" id="public_note" name="public_note"><?php echo esc_textarea( $v( 'public_note' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="internal_note">Internal note</label></th>
						<td><textarea class="large-text" rows="2" id="internal_note" name="internal_note"><?php echo esc_textarea( $v( 'internal_note' ) ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( $id ? 'Update question' : 'Add question' ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=naase-questions' ) ); ?>">Cancel</a>
			</form>
		</div>
		<?php
	}

	/* ===================== Responses / leaderboard page ===================== */

	public static function page_responses() {
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $edit_id ) {
			self::render_attempt_form( $edit_id );
			return;
		}

		global $wpdb;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$per_page = 25;
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = NAASE_DB::attempts();
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$pages    = (int) ceil( $total / $per_page );
		?>
		<div class="wrap naase-admin">
			<h1>Leaderboard &amp; Responses</h1>
			<?php self::nav( 'naase-responses' ); ?>
			<?php self::notice(); ?>

			<p class="description">All attempts are listed for internal use (including partial / timed-out). Only <strong>completed</strong> attempts with “Join the Leaderboard” appear on the public leaderboard.</p>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th>ID</th><th>Status</th><th>Name</th><th>Email</th><th>Score</th><th>Tier</th><th>Time</th><th>Leaderboard</th><th>Answers</th><th>Date</th><th>Actions</th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="11">No attempts recorded yet.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo (int) $r['id']; ?></td>
							<td><span class="naase-status naase-status--<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
							<td><?php echo esc_html( trim( $r['first_name'] . ' ' . $r['last_name'] ) ); ?></td>
							<td><?php echo esc_html( $r['email'] ); ?></td>
							<td><?php echo null === $r['score'] ? '—' : (int) $r['score']; ?></td>
							<td><?php echo esc_html( $r['tier'] ); ?></td>
							<td><?php echo $r['duration_seconds'] ? esc_html( NAASE_Scoring::format_duration_long( (int) $r['duration_seconds'] ) ) : '—'; ?></td>
							<td><?php echo $r['join_leaderboard'] ? 'Yes' : 'No'; ?></td>
							<td style="max-width:160px;"><code style="font-size:11px;"><?php echo esc_html( $r['answers_string'] ); ?></code></td>
							<td><?php echo esc_html( $r['finished_at'] ? $r['finished_at'] : $r['started_at'] ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=naase-responses&edit=' . (int) $r['id'] ) ); ?>">Edit</a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=naase_delete_attempt&id=' . (int) $r['id'] ), 'naase_delete_attempt_' . (int) $r['id'] ) ); ?>" onclick="return confirm('Delete this entry?');" style="color:#b32d2e;">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php self::pagination( $paged, $pages ); ?>
		</div>
		<?php
	}

	private static function render_attempt_form( $id ) {
		$r = NAASE_Attempts::get_row_by_id( $id );
		if ( ! $r ) {
			echo '<div class="wrap"><p>Entry not found.</p></div>';
			return;
		}
		?>
		<div class="wrap naase-admin">
			<h1>Edit Entry #<?php echo (int) $r['id']; ?></h1>
			<?php self::nav( 'naase-responses' ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="naase_save_attempt">
				<input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
				<?php wp_nonce_field( 'naase_save_attempt' ); ?>

				<table class="form-table" role="presentation">
					<tr><th><label>First name</label></th><td><input class="regular-text" name="first_name" value="<?php echo esc_attr( $r['first_name'] ); ?>"></td></tr>
					<tr><th><label>Last name</label></th><td><input class="regular-text" name="last_name" value="<?php echo esc_attr( $r['last_name'] ); ?>"></td></tr>
					<tr><th><label>Email</label></th><td><input class="regular-text" type="email" name="email" value="<?php echo esc_attr( $r['email'] ); ?>"></td></tr>
					<tr><th><label>LinkedIn</label></th><td><input class="regular-text" name="linkedin" value="<?php echo esc_attr( $r['linkedin'] ); ?>"></td></tr>
					<tr><th><label>Score</label></th><td><input type="number" min="0" max="<?php echo (int) NAASE_QUESTIONS_PER_ATTEMPT; ?>" name="score" value="<?php echo esc_attr( $r['score'] ); ?>"></td></tr>
					<tr><th><label>Join leaderboard</label></th><td><label><input type="checkbox" name="join_leaderboard" value="1" <?php checked( (int) $r['join_leaderboard'], 1 ); ?>> Visible on public leaderboard</label></td></tr>
				</table>
				<p class="description">Tier is recalculated from the score on save.</p>

				<?php submit_button( 'Update entry' ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=naase-responses' ) ); ?>">Cancel</a>
			</form>
		</div>
		<?php
	}

	/* ===================== Handlers ===================== */

	private static function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( $nonce_action );
	}

	public static function handle_save_settings() {
		self::guard( 'naase_save_settings' );
		$in  = wp_unslash( $_POST );
		$out = array(
			'challenge_title'    => sanitize_text_field( $in['challenge_title'] ?? '' ),
			'challenge_desc'     => sanitize_textarea_field( $in['challenge_desc'] ?? '' ),
			'timeout_title'      => sanitize_text_field( $in['timeout_title'] ?? '' ),
			'timeout_text'       => sanitize_textarea_field( $in['timeout_text'] ?? '' ),
			'post_completion'    => sanitize_textarea_field( $in['post_completion'] ?? '' ),
			'share_text'         => sanitize_textarea_field( $in['share_text'] ?? '' ),
			'privacy_text'       => sanitize_textarea_field( $in['privacy_text'] ?? '' ),
			'zapier_webhook_url' => esc_url_raw( $in['zapier_webhook_url'] ?? '' ),
		);
		for ( $i = 1; $i <= 4; $i++ ) {
			$out[ 'feature_' . $i ] = sanitize_text_field( $in[ 'feature_' . $i ] ?? '' );
		}
		NAASE_Settings::update( $out );
		self::redirect( self::SLUG, 'settings_saved' );
	}

	public static function handle_save_question() {
		self::guard( 'naase_save_question' );
		$id     = (int) ( $_POST['id'] ?? 0 );
		$fields = NAASE_Questions::sanitize( wp_unslash( $_POST ) );
		if ( $id ) {
			NAASE_Questions::update( $id, $fields );
		} else {
			NAASE_Questions::insert( $fields );
		}
		self::redirect( 'naase-questions', 'question_saved' );
	}

	public static function handle_delete_question() {
		$id = (int) ( $_GET['id'] ?? 0 );
		self::guard( 'naase_delete_question_' . $id );
		NAASE_Questions::delete( $id );
		self::redirect( 'naase-questions', 'question_deleted' );
	}

	public static function handle_import_questions() {
		self::guard( 'naase_import_questions' );
		if ( empty( $_FILES['naase_file']['tmp_name'] ) ) {
			self::redirect( 'naase-questions', 'error' );
		}
		$tmp  = $_FILES['naase_file']['tmp_name']; // phpcs:ignore
		$name = sanitize_file_name( $_FILES['naase_file']['name'] ?? '' );
		$raw  = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$rows = array();

		if ( preg_match( '/\.json$/i', $name ) ) {
			$decoded = json_decode( $raw, true );
			$rows    = is_array( $decoded ) ? $decoded : array();
		} else {
			$rows = self::parse_csv( $raw );
		}

		$count   = 0;
		$skipped = 0;
		$seen    = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['question_text'] ) ) {
				continue;
			}
			$fields = NAASE_Questions::sanitize( $row );
			$key    = $fields['question_text'];
			// Skip blanks, duplicates within the file, and questions already in the bank.
			if ( '' === $key || isset( $seen[ $key ] ) || NAASE_Questions::exists_by_text( $key ) ) {
				$skipped++;
				continue;
			}
			$seen[ $key ] = true;
			NAASE_Questions::insert( $fields );
			$count++;
		}
		self::redirect( 'naase-questions', 'imported', array( 'imported' => $count, 'skipped' => $skipped ) );
	}

	private static function parse_csv( $raw ) {
		$rows  = array();
		$lines = preg_split( '/\r\n|\r|\n/', trim( (string) $raw ) );
		if ( empty( $lines ) ) {
			return $rows;
		}
		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( static function ( $h ) {
			return strtolower( trim( $h ) );
		}, $header );
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line );
			$row   = array();
			foreach ( $header as $i => $key ) {
				$row[ $key ] = isset( $cells[ $i ] ) ? $cells[ $i ] : '';
			}
			$rows[] = $row;
		}
		return $rows;
	}

	public static function handle_export_questions() {
		self::guard( 'naase_export_questions' );
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		$items  = NAASE_Questions::all();
		$cols   = array( 'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d', 'correct_answer', 'knowledge_area', 'difficulty', 'public_note', 'internal_note' );

		// Project every row onto the export columns once, then branch only on output format.
		$rows = array_map(
			static function ( $r ) use ( $cols ) {
				$o = array();
				foreach ( $cols as $c ) {
					$o[ $c ] = $r[ $c ] ?? '';
				}
				return $o;
			},
			$items
		);

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="naase-questions.json"' );
			echo wp_json_encode( $rows, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="naase-questions.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $cols );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_values( $row ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function handle_save_attempt() {
		self::guard( 'naase_save_attempt' );
		$id = (int) ( $_POST['id'] ?? 0 );
		$in = wp_unslash( $_POST );

		$score = isset( $in['score'] ) && '' !== $in['score'] ? max( 0, min( NAASE_QUESTIONS_PER_ATTEMPT, (int) $in['score'] ) ) : null;

		$data = array(
			'first_name'       => sanitize_text_field( $in['first_name'] ?? '' ),
			'last_name'        => sanitize_text_field( $in['last_name'] ?? '' ),
			'email'            => sanitize_email( $in['email'] ?? '' ),
			'linkedin'         => esc_url_raw( $in['linkedin'] ?? '' ),
			'join_leaderboard' => ! empty( $in['join_leaderboard'] ) ? 1 : 0,
			'updated_at'       => current_time( 'mysql' ),
		);
		if ( null !== $score ) {
			$data['score'] = $score;
			$data['tier']  = NAASE_Scoring::tier( $score );
		}

		global $wpdb;
		$wpdb->update( NAASE_DB::attempts(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB

		// The displayed name/score changed → drop the cached badge so it regenerates.
		$row = NAASE_Attempts::get_row_by_id( $id );
		if ( $row ) {
			NAASE_Badge::delete( $row['token'] );
		}
		NAASE_Leaderboard::flush();

		self::redirect( 'naase-responses', 'attempt_saved' );
	}

	public static function handle_delete_attempt() {
		$id = (int) ( $_GET['id'] ?? 0 );
		self::guard( 'naase_delete_attempt_' . $id );

		$row = NAASE_Attempts::get_row_by_id( $id );
		if ( $row ) {
			NAASE_Badge::delete( $row['token'] );
		}
		global $wpdb;
		$wpdb->delete( NAASE_DB::attempts(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		NAASE_Leaderboard::flush();
		self::redirect( 'naase-responses', 'attempt_deleted' );
	}
}
