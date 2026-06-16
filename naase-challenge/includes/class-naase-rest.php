<?php
/**
 * REST API for the challenge flow.
 *
 * Namespace: naase/v1
 *   POST /start         → begin an attempt, returns token + first question
 *   POST /resume        → revive a session by token, returns the current question OR a timeout/gone signal
 *   POST /answer        → record an answer, returns next question OR completion result
 *   POST /submit-form   → save contact form, fire Zapier, return result URL
 *   POST /abandon       → mark a partial attempt as abandoned (beacon on page leave)
 *
 * All routes are public but require a valid wp_rest nonce (sent as X-WP-Nonce or _wpnonce).
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_REST {

	const NS = 'naase/v1';

	/** Minimum seconds between completion and form submit (anti-bot). */
	const MIN_FORM_SECONDS = 2;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
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

	/* --------------------------------------------------------------------- */

	public static function start( $request ) {
		$result = NAASE_Attempts::start();
		return self::respond( $result );
	}

	public static function resume( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( '' === $token ) {
			return self::respond( array( 'status' => 'gone' ) );
		}
		return self::respond( NAASE_Attempts::resume( $token ) );
	}

	public static function answer( $request ) {
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$qid    = (int) $request->get_param( 'question_id' );
		$choice = sanitize_text_field( (string) $request->get_param( 'choice' ) );

		if ( '' === $token ) {
			return self::respond( new WP_Error( 'naase_no_token', __( 'Missing attempt token.', 'naase-challenge' ), array( 'status' => 400 ) ) );
		}
		return self::respond( NAASE_Attempts::answer( $token, $qid, $choice ) );
	}

	public static function submit_form( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( '' === $token ) {
			return self::respond( new WP_Error( 'naase_no_token', __( 'Missing attempt token.', 'naase-challenge' ), array( 'status' => 400 ) ) );
		}

		// --- Anti-spam: honeypot + timing ---------------------------------
		$honeypot = trim( (string) $request->get_param( 'company_website' ) ); // hidden field; bots fill it.
		if ( '' !== $honeypot ) {
			// Pretend success to not tip off bots, but record nothing extra.
			return self::respond( array( 'result_url' => NAASE_Attempts::result_url( $token ) ) );
		}

		$row = NAASE_Attempts::get_row( $token );
		if ( $row && ! empty( $row['finished_at'] ) ) {
			$elapsed = time() - strtotime( $row['finished_at'] . ' UTC' );
			if ( $elapsed < self::MIN_FORM_SECONDS ) {
				return self::respond( new WP_Error( 'naase_too_fast', __( 'Submission rejected. Please try again.', 'naase-challenge' ), array( 'status' => 429 ) ) );
			}
		}
		// ------------------------------------------------------------------

		$data = array(
			'first_name'          => (string) $request->get_param( 'first_name' ),
			'last_name'           => (string) $request->get_param( 'last_name' ),
			'email'               => (string) $request->get_param( 'email' ),
			'join_leaderboard'    => $request->get_param( 'join_leaderboard' ),
			'membership_interest' => $request->get_param( 'membership_interest' ),
			'linkedin'            => (string) $request->get_param( 'linkedin' ),
		);

		return self::respond( NAASE_Attempts::submit_form( $token, $data ) );
	}

	public static function abandon( $request ) {
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$reason = sanitize_text_field( (string) $request->get_param( 'reason' ) );
		if ( '' !== $token ) {
			if ( 'timeout' === $reason ) {
				NAASE_Attempts::mark_timeout( $token );
			} else {
				NAASE_Attempts::abandon( $token );
			}
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* --------------------------------------------------------------------- */

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
}
