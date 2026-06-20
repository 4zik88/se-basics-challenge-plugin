<?php
/**
 * Question bank repository.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Questions {

	/** Valid answer letters. */
	const LETTERS = array( 'A', 'B', 'C', 'D' );

	/** HTML tags allowed in question/answer/context text (inline emphasis only). */
	const ALLOWED_HTML = array(
		'em' => array(),
		'i'  => array(),
	);

	/**
	 * Sanitise a text field that may contain inline <em>/<i> emphasis.
	 *
	 * Keeps only the inline-emphasis whitelist (no attributes) and strips every
	 * other tag; wp_kses also neutralises dangerous markup.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_rich( $value ) {
		return trim( wp_kses( (string) $value, self::ALLOWED_HTML ) );
	}

	/**
	 * Count active questions in the bank.
	 *
	 * @return int
	 */
	public static function count_active() {
		global $wpdb;
		$table = NAASE_DB::questions();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Get a single question row by id.
	 *
	 * @param int $id Question id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = NAASE_DB::questions();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Fetch many questions by ids, preserving the given order.
	 *
	 * @param int[] $ids Question ids in desired order.
	 * @return array[] Rows keyed by their position in $ids order.
	 */
	public static function get_many_ordered( array $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$table        = NAASE_DB::questions();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB

		$by_id = array();
		foreach ( $rows as $row ) {
			$by_id[ (int) $row['id'] ] = $row;
		}
		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$ordered[] = $by_id[ $id ];
			}
		}
		return $ordered;
	}

	/**
	 * Pick N random active question ids for a new attempt.
	 *
	 * @param int $limit How many questions.
	 * @return int[] Ordered (already randomised) question ids.
	 */
	public static function pick_random_ids( $limit ) {
		global $wpdb;
		$table = NAASE_DB::questions();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = 'active' ORDER BY RAND() LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB
		return array_map( 'intval', $ids );
	}

	/**
	 * Public-facing payload for a question (NO correct answer leaked).
	 *
	 * @param array $row     Question row.
	 * @param int   $index   Zero-based position within the attempt.
	 * @param int   $total   Total questions in the attempt.
	 * @return array
	 */
	public static function public_payload( array $row, $index, $total ) {
		return array(
			'id'             => (int) $row['id'],
			'number'         => $index + 1,
			'total'          => $total,
			'question'       => $row['question_text'],
			'answers'        => array(
				'A' => $row['answer_a'],
				'B' => $row['answer_b'],
				'C' => $row['answer_c'],
				'D' => $row['answer_d'],
			),
			'knowledge_area' => $row['knowledge_area'],
			'helpful_context' => isset( $row['public_note'] ) ? (string) $row['public_note'] : '',
		);
	}

	/**
	 * List questions for the admin table.
	 *
	 * @param array $args { search, paged, per_page }.
	 * @return array { items, total }
	 */
	public static function admin_list( $args = array() ) {
		global $wpdb;
		$table    = NAASE_DB::questions();
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$where  = 'WHERE 1=1';
		$params = array();
		if ( '' !== $search ) {
			// Let "Q7", "q7" or "7" jump straight to bank question #7 by id;
			// otherwise fall back to a text search over question / knowledge area.
			$id_match = ltrim( $search, 'Qq' );
			if ( ctype_digit( $id_match ) ) {
				$where   .= ' AND id = %d';
				$params[] = (int) $id_match;
			} else {
				$where   .= ' AND (question_text LIKE %s OR knowledge_area LIKE %s)';
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$params[] = $like;
				$params[] = $like;
			}
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;
		$items         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Sanitise raw question input into a storable row.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitised fields.
	 */
	public static function sanitize( array $input ) {
		$letter = strtoupper( trim( (string) ( $input['correct_answer'] ?? 'A' ) ) );
		if ( ! in_array( $letter, self::LETTERS, true ) ) {
			$letter = 'A';
		}
		$difficulty = (int) ( $input['difficulty'] ?? 1 );
		$difficulty = max( 1, min( 10, $difficulty ) );

		return array(
			'question_text'  => self::sanitize_rich( $input['question_text'] ?? '' ),
			'answer_a'       => self::sanitize_rich( $input['answer_a'] ?? '' ),
			'answer_b'       => self::sanitize_rich( $input['answer_b'] ?? '' ),
			'answer_c'       => self::sanitize_rich( $input['answer_c'] ?? '' ),
			'answer_d'       => self::sanitize_rich( $input['answer_d'] ?? '' ),
			'correct_answer' => $letter,
			'knowledge_area' => sanitize_text_field( $input['knowledge_area'] ?? '' ),
			'difficulty'     => $difficulty,
			'public_note'    => self::sanitize_rich( $input['public_note'] ?? '' ),
			'internal_note'  => sanitize_textarea_field( $input['internal_note'] ?? '' ),
			'status'         => 'active',
		);
	}

	/**
	 * Delete every question and reset the id counter, so the next inserts start at 1.
	 * Used by "replace bank" import to give clean Q1, Q2, … numbering.
	 *
	 * @return void
	 */
	public static function delete_all_and_reset() {
		global $wpdb;
		$table = NAASE_DB::questions();
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$table} AUTO_INCREMENT = 1" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Whether a question with the given (already sanitised) text already exists.
	 * Used to skip duplicates on import.
	 *
	 * @param string $question_text Sanitised question text.
	 * @return bool
	 */
	public static function exists_by_text( $question_text ) {
		global $wpdb;
		$table = NAASE_DB::questions();
		$id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE question_text = %s LIMIT 1", $question_text ) ); // phpcs:ignore WordPress.DB
		return null !== $id;
	}

	/**
	 * Insert a new question. Returns the new id or 0 on failure.
	 *
	 * @param array $fields Sanitised fields.
	 * @return int
	 */
	public static function insert( array $fields ) {
		global $wpdb;
		$now            = current_time( 'mysql' );
		$fields['created_at'] = $now;
		$fields['updated_at'] = $now;
		$ok = $wpdb->insert( NAASE_DB::questions(), $fields ); // phpcs:ignore WordPress.DB
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing question.
	 *
	 * @param int   $id     Question id.
	 * @param array $fields Sanitised fields.
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( NAASE_DB::questions(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete a question.
	 *
	 * @param int $id Question id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( NAASE_DB::questions(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * All questions (for export).
	 *
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;
		$table = NAASE_DB::questions();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB
		return $rows ? $rows : array();
	}
}
