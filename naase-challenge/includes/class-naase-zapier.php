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

		$questions = NAASE_Attempts::questions_breakdown( $row );

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
			// Full per-question breakdown — numbered 1..N as the participant answered,
			// with full question/answer/correct-answer texts (no bank ids). Use the array
			// for line-item mapping, or `answers_text` as a ready-made email block.
			'questions'           => $questions,
			'answers_text'        => self::answers_summary( $questions ),
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

	/**
	 * Render the per-question breakdown into a plain-text block that can be dropped
	 * straight into an email. Numbered 1..N (as the participant answered), full texts,
	 * no bank ids.
	 *
	 * @param array[] $questions Output of NAASE_Attempts::questions_breakdown().
	 * @return string
	 */
	private static function answers_summary( array $questions ) {
		$lines = array();
		foreach ( $questions as $q ) {
			$lines[] = sprintf( '%d. %s', $q['number'], wp_strip_all_tags( $q['question'] ) );
			$your    = '' !== $q['selected_answer'] ? wp_strip_all_tags( $q['selected_answer'] ) : '—';
			$lines[] = sprintf( '   Your answer: %s %s', $your, $q['is_correct'] ? '✓' : '✗' );
			if ( ! $q['is_correct'] ) {
				$lines[] = sprintf( '   Correct answer: %s', wp_strip_all_tags( $q['correct_answer'] ) );
			}
			$lines[] = '';
		}
		return rtrim( implode( "\n", $lines ) );
	}
}
