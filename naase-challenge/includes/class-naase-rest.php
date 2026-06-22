<?php
/**
 * REST API for the challenge flow.
 *
 * Namespace: naase/v1
 *   GET  /ping          → transport probe (public, no nonce); lets the JS detect a
 *                          blocked REST API and fall back to admin-ajax
 *   POST /start         → begin an attempt, returns token + first question
 *   POST /resume        → revive a session by token, returns the current question OR a timeout/gone signal
 *   POST /answer        → record an answer, returns next question OR completion result
 *   POST /submit-form   → save contact form, fire Zapier, return result URL
 *   POST /abandon       → mark a partial attempt as abandoned (beacon on page leave)
 *
 * All POST routes are public but require a valid wp_rest nonce (sent as X-WP-Nonce or _wpnonce).
 *
 * Many hardened sites disable the REST API for logged-out visitors (security plugins,
 * "Disable REST API", etc.), which would break the quiz. So every operation is ALSO
 * exposed over admin-ajax.php — which those tools generally leave open — and the same
 * business logic is shared between both transports via the op_* methods. The JS picks a
 * transport once per page load using /ping, so non-idempotent calls never double-fire.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_REST {

	const NS = 'naase/v1';

	/** Minimum seconds between completion and form submit (anti-bot). */
	const MIN_FORM_SECONDS = 2;

	/** The five quiz operations, shared by the REST and admin-ajax transports. */
	const ACTIONS = array( 'start', 'resume', 'answer', 'submit_form', 'abandon' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );

		// admin-ajax fallback for sites where the REST API is blocked for guests.
		foreach ( self::ACTIONS as $action ) {
			add_action( 'wp_ajax_naase_' . $action, array( __CLASS__, 'ajax_' . $action ) );
			add_action( 'wp_ajax_nopriv_naase_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
	}

	public static function register() {
		$args = array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'check_nonce' ),
		);

		register_rest_route( self::NS, '/start', array( $args + array( 'callback' => array( __CLASS__, 'start' ) ) ) );
		register_rest_route( self::NS, '/resume', array( $args + array( 'callback' => array( __CLASS__, 'resume' ) ) ) );
		register_rest_route( self::NS, '/answer', array( $args + array( 'callback' => array( __CLASS__, 'answer' ) ) ) );
		register_rest_route( self::NS, '/submit-form', array( $args + array( 'callback' => array( __CLASS__, 'submit_form' ) ) ) );
		register_rest_route( self::NS, '/abandon', array( $args + array( 'callback' => array( __CLASS__, 'abandon' ) ) ) );

		// Public, side-effect-free probe so the front-end can detect REST availability.
		register_rest_route(
			self::NS,
			'/ping',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					return rest_ensure_response( array( 'ok' => true ) );
				},
			)
		);
	}

	/**
	 * Verify the wp_rest nonce for CSRF protection (works for logged-out visitors too).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function check_nonce( $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		return new WP_Error( 'naase_bad_nonce', __( 'Security check failed. Please reload the page.', 'naase-challenge' ), array( 'status' => 403 ) );
	}

	/* ===================== Shared business logic (op_*) ===================== */

	private static function op_start() {
		return NAASE_Attempts::start();
	}

	private static function op_resume( $token ) {
		if ( '' === $token ) {
			return array( 'status' => 'gone' );
		}
		return NAASE_Attempts::resume( $token );
	}

	private static function op_answer( $token, $qid, $choice ) {
		if ( '' === $token ) {
			return new WP_Error( 'naase_no_token', __( 'Missing attempt token.', 'naase-challenge' ), array( 'status' => 400 ) );
		}
		return NAASE_Attempts::answer( $token, $qid, $choice );
	}

	/**
	 * @param string $token  Attempt token.
	 * @param array  $params first_name, last_name, email, join_leaderboard,
	 *                       membership_interest, linkedin, company_website (honeypot).
	 * @return array|WP_Error
	 */
	private static function op_submit_form( $token, array $params ) {
		if ( '' === $token ) {
			return new WP_Error( 'naase_no_token', __( 'Missing attempt token.', 'naase-challenge' ), array( 'status' => 400 ) );
		}

		// --- Anti-spam: honeypot + timing ---------------------------------
		$honeypot = trim( (string) ( $params['company_website'] ?? '' ) ); // hidden field; bots fill it.
		if ( '' !== $honeypot ) {
			// Pretend success to not tip off bots, but record nothing extra.
			return array( 'result_url' => NAASE_Attempts::result_url( $token ) );
		}

		$row = NAASE_Attempts::get_row( $token );
		if ( $row && ! empty( $row['finished_at'] ) ) {
			$elapsed = time() - strtotime( $row['finished_at'] . ' UTC' );
			if ( $elapsed < self::MIN_FORM_SECONDS ) {
				return new WP_Error( 'naase_too_fast', __( 'Submission rejected. Please try again.', 'naase-challenge' ), array( 'status' => 429 ) );
			}
		}
		// ------------------------------------------------------------------

		$data = array(
			'first_name'          => (string) ( $params['first_name'] ?? '' ),
			'last_name'           => (string) ( $params['last_name'] ?? '' ),
			'email'               => (string) ( $params['email'] ?? '' ),
			'join_leaderboard'    => $params['join_leaderboard'] ?? null,
			'membership_interest' => $params['membership_interest'] ?? null,
			'linkedin'            => (string) ( $params['linkedin'] ?? '' ),
		);

		return NAASE_Attempts::submit_form( $token, $data );
	}

	private static function op_abandon( $token, $reason ) {
		if ( '' !== $token ) {
			if ( 'timeout' === $reason ) {
				NAASE_Attempts::mark_timeout( $token );
			} else {
				NAASE_Attempts::abandon( $token );
			}
		}
		return array( 'ok' => true );
	}

	/* ===================== REST adapters ===================== */

	public static function start( $request ) {
		return self::respond( self::op_start() );
	}

	public static function resume( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		return self::respond( self::op_resume( $token ) );
	}

	public static function answer( $request ) {
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$qid    = (int) $request->get_param( 'question_id' );
		$choice = sanitize_text_field( (string) $request->get_param( 'choice' ) );
		return self::respond( self::op_answer( $token, $qid, $choice ) );
	}

	public static function submit_form( $request ) {
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$params = array(
			'first_name'          => $request->get_param( 'first_name' ),
			'last_name'           => $request->get_param( 'last_name' ),
			'email'               => $request->get_param( 'email' ),
			'join_leaderboard'    => $request->get_param( 'join_leaderboard' ),
			'membership_interest' => $request->get_param( 'membership_interest' ),
			'linkedin'            => $request->get_param( 'linkedin' ),
			'company_website'     => $request->get_param( 'company_website' ),
		);
		return self::respond( self::op_submit_form( $token, $params ) );
	}

	public static function abandon( $request ) {
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$reason = sanitize_text_field( (string) $request->get_param( 'reason' ) );
		return self::respond( self::op_abandon( $token, $reason ) );
	}

	/**
	 * Convert a result/WP_Error into a REST response.
	 *
	 * @param mixed $result Result array or WP_Error.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/* ===================== admin-ajax adapters ===================== */

	public static function ajax_start() {
		self::ajax_guard();
		self::ajax_respond( self::op_start() );
	}

	public static function ajax_resume() {
		self::ajax_guard();
		$p = self::ajax_payload();
		self::ajax_respond( self::op_resume( sanitize_text_field( (string) ( $p['token'] ?? '' ) ) ) );
	}

	public static function ajax_answer() {
		self::ajax_guard();
		$p = self::ajax_payload();
		self::ajax_respond(
			self::op_answer(
				sanitize_text_field( (string) ( $p['token'] ?? '' ) ),
				(int) ( $p['question_id'] ?? 0 ),
				sanitize_text_field( (string) ( $p['choice'] ?? '' ) )
			)
		);
	}

	public static function ajax_submit_form() {
		self::ajax_guard();
		$p = self::ajax_payload();
		self::ajax_respond( self::op_submit_form( sanitize_text_field( (string) ( $p['token'] ?? '' ) ), $p ) );
	}

	public static function ajax_abandon() {
		self::ajax_guard();
		$p = self::ajax_payload();
		self::ajax_respond(
			self::op_abandon(
				sanitize_text_field( (string) ( $p['token'] ?? '' ) ),
				sanitize_text_field( (string) ( $p['reason'] ?? '' ) )
			)
		);
	}

	/**
	 * Verify the wp_rest nonce for an admin-ajax request; emits a 403 JSON error and
	 * exits when it fails.
	 */
	private static function ajax_guard() {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $nonce && isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			wp_send_json(
				array(
					'code'    => 'naase_bad_nonce',
					'message' => __( 'Security check failed. Please reload the page.', 'naase-challenge' ),
				),
				403
			);
		}
	}

	/**
	 * Decode the JSON payload posted by the admin-ajax transport.
	 *
	 * @return array
	 */
	private static function ajax_payload() {
		$raw  = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Emit an admin-ajax response that mirrors the REST shape: the result object on
	 * success, or { code, message } with the matching HTTP status on error.
	 *
	 * @param mixed $result Result array or WP_Error.
	 */
	private static function ajax_respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$err    = $result->get_error_data();
			$status = ( is_array( $err ) && isset( $err['status'] ) ) ? (int) $err['status'] : 400;
			wp_send_json(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				$status
			);
		}
		wp_send_json( $result );
	}
}
