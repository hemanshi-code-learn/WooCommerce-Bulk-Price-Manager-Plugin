<?php
/**
 * Log Repository
 *
 * All raw DB access for the wc_bulk_price_manager table lives here
 * Controllers and services only talk to this repository, never to $wpdb directly
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Repositories;

use WCBulkPriceManager\Interfaces\LogRepositoryInterface;

final class LogRepository implements LogRepositoryInterface {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wc_bulk_price_manager';
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Persist a single price mutation record
	 */
	public function saveLog(
		string $jobId,
		int    $productId,
		float  $oldPrice,
		float  $newPrice,
		int    $userId
	): bool {
		global $wpdb;

		return $wpdb->insert(
			$this->table,
			[
				'job_id'     => $jobId,
				'product_id' => $productId,
				'old_price'  => $oldPrice,
				'new_price'  => $newPrice,
				'user_id'    => $userId,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%f', '%f', '%d', '%s' ]
		) !== false;
	}

	/**
	 * Remove all log entries for a given job (used after rollback)
	 */
	public function deleteLogsForJob( string $jobId ): void {
		global $wpdb;

		$wpdb->delete( $this->table, [ 'job_id' => $jobId ], [ '%s' ] );
	}

	// -------------------------------------------------------------------------
	// Read operations
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a paginated batch of log entries for rollback
	 *
	 * @return array<int, array{product_id: string, old_price: string}>
	 */
	public function getLogsByJob( string $jobId, int $page, int $batchSize ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $batchSize;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, old_price
				 FROM {$this->table}
				 WHERE job_id = %s
				 ORDER BY id ASC
				 LIMIT %d OFFSET %d",
				$jobId,
				$batchSize,
				$offset
			),
			ARRAY_A
		) ?? [];
	}

	/**
	 * Total number of log rows for a specific job
	 */
	public function getTotalLogCountForJob( string $jobId ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table} WHERE job_id = %s",
				$jobId
			)
		);
	}

	/**
	 * Aggregate metrics for the post-job summary
	 */
	public function getJobMetrics( string $jobId ): array {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(id)                           AS total_processed,
					AVG( ABS( new_price - old_price ) ) AS avg_adjustment,
					MIN( created_at )                   AS started_at
				 FROM {$this->table}
				 WHERE job_id = %s",
				$jobId
			),
			ARRAY_A
		) ?? [];
	}

	/**
	 * Return a list of distinct job IDs ordered by most recent
	 */
	public function getRecentJobIds( int $limit = 10 ): array {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT job_id
				 FROM {$this->table}
				 ORDER BY MIN(created_at) DESC
				 LIMIT %d",
				$limit
			)
		) ?? [];
	}
}
