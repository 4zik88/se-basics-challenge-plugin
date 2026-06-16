<?php
/**
 * Database table names and schema.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

class NAASE_DB {

	/** Questions bank table (without prefix). */
	const QUESTIONS = 'naase_questions';

	/** Attempts / runs table (without prefix). */
	const ATTEMPTS = 'naase_attempts';

	/**
	 * Fully-qualified questions table name.
	 *
	 * @return string
	 */
	public static function questions() {
		global $wpdb;
		return $wpdb->prefix . self::QUESTIONS;
	}

	/**
	 * Fully-qualified attempts table name.
	 *
	 * @return string
	 */
	public static function attempts() {
		global $wpdb;
		return $wpdb->prefix . self::ATTEMPTS;
	}

	/**
	 * Create / upgrade the custom tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$questions       = self::questions();
		$attempts        = self::attempts();

		// Questions bank.
		dbDelta(
			"CREATE TABLE {$questions} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				question_text TEXT NOT NULL,
				answer_a TEXT NOT NULL,
				answer_b TEXT NOT NULL,
				answer_c TEXT NOT NULL,
				answer_d TEXT NOT NULL,
				correct_answer CHAR(1) NOT NULL DEFAULT 'A',
				knowledge_area VARCHAR(190) NOT NULL DEFAULT '',
				difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
				public_note TEXT NULL,
				internal_note TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY status (status),
				KEY knowledge_area (knowledge_area)
			) {$charset_collate};"
		);

		// Attempts / runs.
		dbDelta(
			"CREATE TABLE {$attempts} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				token CHAR(40) NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
				question_ids LONGTEXT NULL,
				answers LONGTEXT NULL,
				answers_string TEXT NULL,
				score TINYINT UNSIGNED NULL,
				tier VARCHAR(60) NULL,
				started_at DATETIME NULL,
				finished_at DATETIME NULL,
				duration_seconds INT UNSIGNED NULL,
				first_name VARCHAR(190) NOT NULL DEFAULT '',
				last_name VARCHAR(190) NOT NULL DEFAULT '',
				email VARCHAR(190) NOT NULL DEFAULT '',
				join_leaderboard TINYINT(1) NOT NULL DEFAULT 0,
				membership_interest TINYINT(1) NOT NULL DEFAULT 0,
				linkedin VARCHAR(255) NOT NULL DEFAULT '',
				form_submitted TINYINT(1) NOT NULL DEFAULT 0,
				ip VARCHAR(100) NOT NULL DEFAULT '',
				user_agent VARCHAR(255) NOT NULL DEFAULT '',
				created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY token (token),
				KEY status (status),
				KEY email (email),
				KEY leaderboard_sort (status, join_leaderboard, score, duration_seconds)
			) {$charset_collate};"
		);
	}

	/**
	 * Drop the custom tables. Used by uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::attempts() ); // phpcs:ignore
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::questions() ); // phpcs:ignore
	}
}
