<?php
/**
 * Zapier webhook notifier — fired once a challenge is completed and the form is submitted.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Zapier {

	/**
	 * Send the completion payload to the configured Zapier webhook.
	 *
	 * @param array $row Attempt row.
	 * @return void
	 */
	public static function notify( $row ) {
		$url = trim( (string) NAASE_Settings::get( 'zapier_webhook_url' ) );
		if ( '' === $url || ! $row ) {
			return;
		}

		$payload = array(
			'token'               => $row['token'],
			'first_name'          => $row['first_name'],
			'last_name'           => $row['last_name'],
			'email'               => $row['email'],
			'score'               => (int) $row['score'],
			'total'               => NAASE_QUESTIONS_PER_ATTEMPT,
			'tier'                => $row['tier'],
			'duration_seconds'    => (int) $row['duration_seconds'],
			'duration_text'       => NAASE_Scoring::format_duration_long( (int) $row['duration_seconds'] ),
			'answers'             => $row['answers_string'],
			'join_leaderboard'    => (bool) $row['join_leaderboard'],
			'membership_interest' => (bool) $row['membership_interest'],
			'linkedin'            => $row['linkedin'],
			'started_at'          => $row['started_at'],
			'finished_at'         => $row['finished_at'],
			'result_url'          => NAASE_Attempts::result_url( $row['token'] ),
			'badge_url'           => NAASE_Badge::url( NAASE_Scoring::tier_key( $row['tier'] ) ),
			'site'                => home_url( '/' ),
		);

		/**
		 * Filter the Zapier payload before it is sent.
		 *
		 * @param array $payload Payload.
		 * @param array $row     Attempt row.
		 */
		$payload = apply_filters( 'naase_zapier_payload', $payload, $row );

		wp_remote_post(
			$url,
			array(
				'timeout'   => 5,
				'blocking'  => false,
				'headers'   => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);
	}
}
