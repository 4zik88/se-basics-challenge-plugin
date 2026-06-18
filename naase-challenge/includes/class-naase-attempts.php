<?php
/**
 * Attempt lifecycle: start, answer, finish, timeout, form submission.
 *
 * Design notes (per spec):
 * - A brand-new attempt that records ZERO answers must leave NOTHING in the DB.
 *   So we keep the pre-first-answer state (selected question ids + start time) in a
 *   transient, and only INSERT the DB row when the first answer arrives.
 * - Scoring and the authoritative timer are server-side; the correct answers are never
 *   sent to the browser.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Attempts {

	const TRANSIENT_PREFIX = 'naase_pending_';

	/* --------------------------------------------------------------------- */
	/* Start                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Start a new attempt.
	 *
	 * @return array|WP_Error { token, question } or error.
	 */
	public static function start() {
		if ( NAASE_Questions::count_active() < NAASE_QUESTIONS_PER_ATTEMPT ) {
			return new WP_Error(
				'naase_not_enough_questions',
				sprintf(
					/* translators: %d: required number of questions */
					__( 'The challenge is not available yet — at least %d questions are required.', 'naase-challenge' ),
					NAASE_QUESTIONS_PER_ATTEMPT
				),
				array( 'status' => 409 )
			);
		}

		$ids = NAASE_Questions::pick_random_ids( NAASE_QUESTIONS_PER_ATTEMPT );
		if ( count( $ids ) < NAASE_QUESTIONS_PER_ATTEMPT ) {
			return new WP_Error( 'naase_not_enough_questions', __( 'Not enough questions available.', 'naase-challenge' ), array( 'status' => 409 ) );
		}

		$token = self::generate_token();
		$now   = time();

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'question_ids' => $ids,
				'started_ts'   => $now,
				'started_at'   => self::mysql_from_ts( $now ),
			),
			NAASE_TIMEOUT_SECONDS + ( 10 * MINUTE_IN_SECONDS )
		);

		$rows  = NAASE_Questions::get_many_ordered( $ids );
		$first = NAASE_Questions::public_payload( $rows[0], 0, NAASE_QUESTIONS_PER_ATTEMPT );

		return array(
			'token'      => $token,
			'started_at' => $now,
			'timeout_in' => NAASE_TIMEOUT_SECONDS,
			'question'   => $first,
		);
	}

	/* --------------------------------------------------------------------- */
	/* Answer                                                                 */
	/* --------------------------------------------------------------------- */

	/**
	 * Record an answer and advance.
	 *
	 * @param string $token       Attempt token.
	 * @param int    $question_id Question being answered.
	 * @param string $choice      A|B|C|D.
	 * @return array|WP_Error
	 */
	public static function answer( $token, $question_id, $choice ) {
		$ctx = self::load_context( $token );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		$choice = strtoupper( trim( (string) $choice ) );
		if ( ! in_array( $choice, NAASE_Questions::LETTERS, true ) ) {
			return new WP_Error( 'naase_bad_choice', __( 'Invalid answer.', 'naase-challenge' ), array( 'status' => 400 ) );
		}

		$question_id = (int) $question_id;
		if ( ! in_array( $question_id, $ctx['question_ids'], true ) ) {
			return new WP_Error( 'naase_bad_question', __( 'That question is not part of this attempt.', 'naase-challenge' ), array( 'status' => 400 ) );
		}

		// Timeout check (authoritative, server-side).
		if ( ( time() - $ctx['started_ts'] ) > NAASE_TIMEOUT_SECONDS ) {
			self::mark_timeout( $token );
			return array( 'status' => 'timeout' );
		}

		$answers                 = $ctx['answers'];
		$answers[ $question_id ] = $choice;

		// Persist (create row on first answer, else update).
		self::persist_answers( $token, $ctx, $answers );

		// Find the next unanswered question, in attempt order.
		$next_index = null;
		foreach ( $ctx['question_ids'] as $i => $qid ) {
			if ( ! isset( $answers[ $qid ] ) ) {
				$next_index = $i;
				break;
			}
		}

		if ( null === $next_index ) {
			// All answered → finalise (uses the in-memory context, no extra read).
			return array(
				'status' => 'completed',
				'result' => self::finalize( $token, 'completed', $ctx['question_ids'], $answers, $ctx['started_at'] ),
			);
		}

		$rows = NAASE_Questions::get_many_ordered( $ctx['question_ids'] );
		$next = NAASE_Questions::public_payload( $rows[ $next_index ], $next_index, NAASE_QUESTIONS_PER_ATTEMPT );

		return array(
			'status'   => 'continue',
			'answered' => count( $answers ),
			'question' => $next,
		);
	}

	/* --------------------------------------------------------------------- */
	/* Resume (Session revive)                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Resume a session by token so a returning visitor can continue mid-challenge.
	 *
	 * @param string $token Token.
	 * @return array {
	 *     status: in_progress → { question, answered, elapsed } (continue the quiz)
	 *             timeout      → session ran past the 1-hour limit (show "Still there?")
	 *             completed    → { result_url } (already finished)
	 *             gone         → nothing to resume (start fresh)
	 * }
	 */
	public static function resume( $token ) {
		$row = self::get_row( $token );

		if ( $row ) {
			if ( 'completed' === $row['status'] ) {
				return array( 'status' => 'completed', 'result_url' => self::result_url( $token ) );
			}
			if ( 'in_progress' !== $row['status'] ) {
				// Already abandoned / timed out → treat as an expired session.
				return array( 'status' => 'timeout' );
			}
			$started_ts = (int) strtotime( $row['started_at'] . ' UTC' );
			if ( ( time() - $started_ts ) > NAASE_TIMEOUT_SECONDS ) {
				self::mark_timeout( $token );
				return array( 'status' => 'timeout' );
			}
			return self::resume_payload( self::decode_ids( $row['question_ids'] ), self::decode_answers( $row['answers'] ), $started_ts );
		}

		// No DB row yet → maybe a started-but-unanswered (pending) session in a transient.
		$pending = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( $pending && ! empty( $pending['question_ids'] ) ) {
			$started_ts = (int) $pending['started_ts'];
			if ( ( time() - $started_ts ) > NAASE_TIMEOUT_SECONDS ) {
				delete_transient( self::TRANSIENT_PREFIX . $token );
				return array( 'status' => 'timeout' );
			}
			return self::resume_payload( array_map( 'intval', $pending['question_ids'] ), array(), $started_ts );
		}

		return array( 'status' => 'gone' );
	}

	/**
	 * Build the resume payload: the next unanswered question + elapsed seconds.
	 *
	 * @param int[]  $question_ids Ordered attempt question ids.
	 * @param array  $answers      qid => letter (already answered).
	 * @param int    $started_ts   Unix start timestamp.
	 * @return array
	 */
	private static function resume_payload( array $question_ids, array $answers, $started_ts ) {
		$next_index = null;
		foreach ( $question_ids as $i => $qid ) {
			if ( ! isset( $answers[ $qid ] ) ) {
				$next_index = $i;
				break;
			}
		}
		if ( null === $next_index ) {
			// All answered but not finalised — nothing to resume to.
			return array( 'status' => 'gone' );
		}

		$rows     = NAASE_Questions::get_many_ordered( $question_ids );
		$question = NAASE_Questions::public_payload( $rows[ $next_index ], $next_index, NAASE_QUESTIONS_PER_ATTEMPT );

		return array(
			'status'   => 'in_progress',
			'question' => $question,
			'answered' => count( $answers ),
			'elapsed'  => max( 0, time() - $started_ts ),
		);
	}

	/* --------------------------------------------------------------------- */
	/* Finish / timeout / abandon                                             */
	/* --------------------------------------------------------------------- */

	/**
	 * Finalise an attempt: compute score/tier/duration, freeze answers_string, set status.
	 * Single source of truth for both completion and partial close.
	 *
	 * @param string $token        Token.
	 * @param string $status       completed|timed_out|abandoned.
	 * @param int[]  $question_ids Attempt question ids (ordered).
	 * @param array  $answers      qid => letter.
	 * @param string $started_at   MySQL start datetime (UTC).
	 * @return array Result summary.
	 */
	private static function finalize( $token, $status, array $question_ids, array $answers, $started_at ) {
		$score    = self::compute_score( $question_ids, $answers );
		$tier     = NAASE_Scoring::tier( $score );
		$finished = time();
		$duration = max( 0, $finished - (int) strtotime( $started_at . ' UTC' ) );

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			NAASE_DB::attempts(),
			array(
				'status'           => $status,
				'score'            => $score,
				'tier'             => $tier,
				'finished_at'      => self::mysql_from_ts( $finished ),
				'duration_seconds' => $duration,
				'answers_string'   => self::build_answers_string( $question_ids, $answers ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'token' => $token )
		);

		delete_transient( self::TRANSIENT_PREFIX . $token );

		return array(
			'token'         => $token,
			'score'         => $score,
			'total'         => NAASE_QUESTIONS_PER_ATTEMPT,
			'tier'          => $tier,
			'tier_key'      => NAASE_Scoring::tier_key( $tier ),
			'duration'      => $duration,
			'duration_text' => NAASE_Scoring::format_duration_long( $duration ),
			'result_url'    => self::result_url( $token ),
		);
	}

	/**
	 * Mark an attempt as timed out (treated like an early exit — partial, internal).
	 *
	 * @param string $token Token.
	 */
	public static function mark_timeout( $token ) {
		self::close_partial( $token, 'timed_out' );
	}

	/**
	 * Mark an attempt as abandoned (user left). Partial, internal only.
	 *
	 * @param string $token Token.
	 */
	public static function abandon( $token ) {
		self::close_partial( $token, 'abandoned' );
	}

	/**
	 * Shared partial-close logic. No-op if no DB row exists (i.e. zero answers).
	 *
	 * @param string $token  Token.
	 * @param string $status timed_out|abandoned.
	 */
	private static function close_partial( $token, $status ) {
		$row = self::get_row( $token );
		delete_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! $row || 'in_progress' !== $row['status'] ) {
			return; // Zero answers → nothing recorded, or already finalised.
		}
		self::finalize( $token, $status, self::decode_ids( $row['question_ids'] ), self::decode_answers( $row['answers'] ), $row['started_at'] );
	}

	/**
	 * Sweep stale in-progress attempts (older than the timeout) into timed_out.
	 * Scheduled via cron; also safe to call ad hoc.
	 */
	public static function sweep_stale() {
		global $wpdb;
		$table  = NAASE_DB::attempts();
		$cutoff = self::mysql_from_ts( time() - NAASE_TIMEOUT_SECONDS );
		$tokens = $wpdb->get_col( $wpdb->prepare( "SELECT token FROM {$table} WHERE status = 'in_progress' AND started_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB
		foreach ( (array) $tokens as $token ) {
			self::mark_timeout( $token );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Contact form                                                           */
	/* --------------------------------------------------------------------- */

	/**
	 * Save the contact form data onto a completed attempt.
	 *
	 * @param string $token Token.
	 * @param array  $data  { first_name, last_name, email, join_leaderboard, membership_interest, linkedin }.
	 * @return array|WP_Error { result_url } on success.
	 */
	public static function submit_form( $token, array $data ) {
		$row = self::get_row( $token );
		if ( ! $row ) {
			return new WP_Error( 'naase_no_attempt', __( 'Attempt not found.', 'naase-challenge' ), array( 'status' => 404 ) );
		}
		if ( 'completed' !== $row['status'] ) {
			return new WP_Error( 'naase_not_completed', __( 'This challenge has not been completed.', 'naase-challenge' ), array( 'status' => 409 ) );
		}

		$first      = sanitize_text_field( $data['first_name'] ?? '' );
		$last       = sanitize_text_field( $data['last_name'] ?? '' );
		$email      = sanitize_email( $data['email'] ?? '' );
		$join       = ! empty( $data['join_leaderboard'] ) ? 1 : 0;
		$membership = ! empty( $data['membership_interest'] ) ? 1 : 0;
		$linkedin   = esc_url_raw( $data['linkedin'] ?? '' );

		if ( '' === $first || '' === $last ) {
			return new WP_Error( 'naase_missing_name', __( 'Please enter your first and last name.', 'naase-challenge' ), array( 'status' => 400 ) );
		}
		if ( preg_match( '/[0-9]/', $first . $last ) ) {
			return new WP_Error( 'naase_bad_name', __( 'First and last name can contain letters only.', 'naase-challenge' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'naase_bad_email', __( 'Please enter a valid email address.', 'naase-challenge' ), array( 'status' => 400 ) );
		}
		if ( $membership && '' === $linkedin ) {
			return new WP_Error( 'naase_missing_linkedin', __( 'A LinkedIn URL is required for NAASE associate membership interest.', 'naase-challenge' ), array( 'status' => 400 ) );
		}
		if ( '' !== $linkedin && ! preg_match( '#^https?://([\w-]+\.)?linkedin\.com/#i', $linkedin ) ) {
			return new WP_Error( 'naase_bad_linkedin', __( 'Please enter a valid LinkedIn URL.', 'naase-challenge' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			NAASE_DB::attempts(),
			array(
				'first_name'          => $first,
				'last_name'           => $last,
				'email'               => $email,
				'join_leaderboard'    => $join,
				'membership_interest' => $membership,
				'linkedin'            => $linkedin,
				'form_submitted'      => 1,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'token' => $token )
		);

		$fresh = self::get_row( $token );

		// The opt-in / name may have changed who appears on the board.
		NAASE_Leaderboard::flush();

		// Notify Zapier (non-blocking).
		NAASE_Zapier::notify( $fresh );

		// Pre-generate the badge so the result page + OG image are ready instantly.
		NAASE_Badge::ensure( $fresh );

		return array(
			'result_url' => self::result_url( $token ),
		);
	}

	/* --------------------------------------------------------------------- */
	/* Reads                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Get a DB attempt row by token.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function get_row( $token ) {
		return self::fetch_one( 'token', '%s', $token );
	}

	/**
	 * Get a DB attempt row by primary id (admin).
	 *
	 * @param int $id Row id.
	 * @return array|null
	 */
	public static function get_row_by_id( $id ) {
		return self::fetch_one( 'id', '%d', (int) $id );
	}

	/**
	 * Fetch a single attempt row by an indexed column.
	 *
	 * @param string $column      Column name (trusted, internal).
	 * @param string $placeholder %s or %d.
	 * @param mixed  $value       Value to match.
	 * @return array|null
	 */
	private static function fetch_one( $column, $placeholder, $value ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NAASE_DB::attempts() . " WHERE {$column} = {$placeholder}", $value ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Build the public result summary for a completed attempt (used by result page).
	 *
	 * @param array $row Attempt row.
	 * @return array
	 */
	public static function result_summary( array $row ) {
		$tier = $row['tier'] ? $row['tier'] : NAASE_Scoring::tier( (int) $row['score'] );
		return array(
			'token'         => $row['token'],
			'first_name'    => $row['first_name'],
			'last_name'     => $row['last_name'],
			'score'         => (int) $row['score'],
			'total'         => NAASE_QUESTIONS_PER_ATTEMPT,
			'tier'          => $tier,
			'tier_key'      => NAASE_Scoring::tier_key( $tier ),
			'duration'      => (int) $row['duration_seconds'],
			'duration_text' => NAASE_Scoring::format_duration_long( (int) $row['duration_seconds'] ),
			'result_url'    => self::result_url( $row['token'] ),
		);
	}

	/* --------------------------------------------------------------------- */
	/* Internal helpers                                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Load the working context for an attempt (transient first, then DB row).
	 *
	 * @param string $token Token.
	 * @return array|WP_Error { question_ids, started_ts, started_at, answers, has_row }.
	 */
	private static function load_context( $token ) {
		$row = self::get_row( $token );
		if ( $row && 'in_progress' === $row['status'] ) {
			return array(
				'question_ids' => self::decode_ids( $row['question_ids'] ),
				'started_ts'   => strtotime( $row['started_at'] . ' UTC' ),
				'started_at'   => $row['started_at'],
				'answers'      => self::decode_answers( $row['answers'] ),
				'has_row'      => true,
			);
		}
		if ( $row ) {
			// Already completed / timed out / abandoned.
			return new WP_Error( 'naase_attempt_closed', __( 'This attempt is no longer active.', 'naase-challenge' ), array( 'status' => 409 ) );
		}

		$pending = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! $pending || empty( $pending['question_ids'] ) ) {
			return new WP_Error( 'naase_expired', __( 'Your session has expired. Please start again.', 'naase-challenge' ), array( 'status' => 410 ) );
		}
		return array(
			'question_ids' => array_map( 'intval', $pending['question_ids'] ),
			'started_ts'   => (int) $pending['started_ts'],
			'started_at'   => $pending['started_at'],
			'answers'      => array(),
			'has_row'      => false,
		);
	}

	/**
	 * Persist answers, creating the DB row on the first answer.
	 *
	 * @param string $token   Token.
	 * @param array  $ctx     Context from load_context().
	 * @param array  $answers qid => letter.
	 */
	private static function persist_answers( $token, array $ctx, array $answers ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		if ( $ctx['has_row'] ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				NAASE_DB::attempts(),
				array(
					'answers'        => wp_json_encode( $answers ),
					'answers_string' => self::build_answers_string( $ctx['question_ids'], $answers ),
					'updated_at'     => $now,
				),
				array( 'token' => $token )
			);
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			NAASE_DB::attempts(),
			array(
				'token'          => $token,
				'status'         => 'in_progress',
				'question_ids'   => wp_json_encode( $ctx['question_ids'] ),
				'answers'        => wp_json_encode( $answers ),
				'answers_string' => self::build_answers_string( $ctx['question_ids'], $answers ),
				'started_at'     => $ctx['started_at'],
				'ip'             => self::client_ip(),
				'user_agent'     => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
	}

	/**
	 * Compute score: count of correct answers.
	 *
	 * @param int[] $question_ids Attempt question ids.
	 * @param array $answers      qid => letter.
	 * @return int
	 */
	private static function compute_score( array $question_ids, array $answers ) {
		if ( empty( $answers ) ) {
			return 0;
		}
		$rows  = NAASE_Questions::get_many_ordered( $question_ids );
		$score = 0;
		foreach ( $rows as $row ) {
			$qid = (int) $row['id'];
			if ( isset( $answers[ $qid ] ) && strtoupper( $answers[ $qid ] ) === strtoupper( $row['correct_answer'] ) ) {
				$score++;
			}
		}
		return $score;
	}

	/**
	 * Build an answer string in the order the questions were presented, each labelled by
	 * its question-bank id, like "Q42-B, Q12-C, Q57-A". This records what came up, in what
	 * order, and how the person answered — and the bank ids keep it comparable across the
	 * randomised attempts for per-question statistics.
	 *
	 * @param int[] $question_ids Attempt question ids, in presentation order.
	 * @param array $answers      qid => letter.
	 * @return string
	 */
	private static function build_answers_string( array $question_ids, array $answers ) {
		$parts = array();
		foreach ( $question_ids as $qid ) {
			$qid = (int) $qid;
			if ( isset( $answers[ $qid ] ) ) {
				$parts[] = 'Q' . $qid . '-' . strtoupper( $answers[ $qid ] );
			}
		}
		return implode( ', ', $parts );
	}

	private static function decode_ids( $json ) {
		$ids = json_decode( (string) $json, true );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	private static function decode_answers( $json ) {
		$a = json_decode( (string) $json, true );
		if ( ! is_array( $a ) ) {
			return array();
		}
		$out = array();
		foreach ( $a as $qid => $letter ) {
			$out[ (int) $qid ] = strtoupper( (string) $letter );
		}
		return $out;
	}

	private static function generate_token() {
		return wp_generate_password( 32, false, false );
	}

	private static function mysql_from_ts( $ts ) {
		return gmdate( 'Y-m-d H:i:s', (int) $ts );
	}

	/**
	 * Public result URL for a token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function result_url( $token ) {
		return home_url( '/naase-result/' . rawurlencode( $token ) . '/' );
	}

	private static function client_ip() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return sanitize_text_field( substr( (string) $ip, 0, 100 ) );
	}
}
