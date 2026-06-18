<?php
/**
 * Leaderboard query: best result per email, with the spec's sort order.
 *
 * Sort: score DESC, completion time ASC, newest finished date DESC (to the second).
 * Only completed attempts whose owner opted into "Join the Leaderboard" are included.
 * If one email has several qualifying attempts, only the best one is shown.
 *
 * Grouping is done in PHP so the ranking is identical across MySQL 5.7 / 8.0 / MariaDB
 * (no window-function dependency). The qualifying set is bounded (opted-in completions),
 * which keeps this comfortably cheap.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_Leaderboard {

	const CACHE_KEY = 'naase_leaderboard_ranked';

	/**
	 * Invalidate the cached ranking. Call whenever a leaderboard-affecting row changes
	 * (form submitted, admin edits/deletes an entry).
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Get a paginated leaderboard page.
	 *
	 * @param int $paged    1-based page.
	 * @param int $per_page Rows per page.
	 * @return array { items, total, pages, paged, per_page }
	 */
	public static function get_page( $paged = 1, $per_page = 20 ) {
		$paged    = max( 1, (int) $paged );
		$per_page = max( 1, (int) $per_page );

		$ranked = self::ranked_rows();
		$total  = count( $ranked );
		$pages  = (int) ceil( $total / $per_page );
		$offset = ( $paged - 1 ) * $per_page;
		$items  = array_slice( $ranked, $offset, $per_page );

		return array(
			'items'    => $items,
			'total'    => $total,
			'pages'    => $pages,
			'paged'    => $paged,
			'per_page' => $per_page,
		);
	}

	/**
	 * The full ranked list (best-per-email), each item annotated with its rank.
	 *
	 * @return array[]
	 */
	public static function ranked_rows() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = NAASE_DB::attempts();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			"SELECT id, token, first_name, last_name, email, score, tier, duration_seconds, finished_at
			 FROM {$table}
			 WHERE status = 'completed' AND join_leaderboard = 1",
			ARRAY_A
		);
		if ( ! $rows ) {
			return array();
		}

		// Keep the best row per (lower-cased) email. Rows without an email have no dedup
		// key, so each is kept on its own (e.g. manually added admin entries).
		$best       = array();
		$standalone = array();
		foreach ( $rows as $row ) {
			$key = strtolower( trim( (string) $row['email'] ) );
			if ( '' === $key ) {
				$standalone[] = $row;
				continue;
			}
			if ( ! isset( $best[ $key ] ) || self::compare( $row, $best[ $key ] ) < 0 ) {
				$best[ $key ] = $row;
			}
		}

		$ranked = array_merge( array_values( $best ), $standalone );
		usort( $ranked, array( __CLASS__, 'compare' ) );

		$rank = 0;
		foreach ( $ranked as &$row ) {
			$row['rank'] = ++$rank;
		}
		unset( $row );

		set_transient( self::CACHE_KEY, $ranked, HOUR_IN_SECONDS );
		return $ranked;
	}

	/**
	 * Comparator implementing the spec sort order. Returns <0 if $a ranks above $b.
	 *
	 * @param array $a Row.
	 * @param array $b Row.
	 * @return int
	 */
	public static function compare( $a, $b ) {
		// 1) score DESC
		$sa = (int) $a['score'];
		$sb = (int) $b['score'];
		if ( $sa !== $sb ) {
			return $sb - $sa;
		}
		// 2) completion time ASC
		$da = (int) $a['duration_seconds'];
		$db = (int) $b['duration_seconds'];
		if ( $da !== $db ) {
			return $da - $db;
		}
		// 3) newest finished date DESC
		$fa = strtotime( (string) $a['finished_at'] );
		$fb = strtotime( (string) $b['finished_at'] );
		if ( $fa !== $fb ) {
			return $fb - $fa;
		}
		return 0;
	}
}
