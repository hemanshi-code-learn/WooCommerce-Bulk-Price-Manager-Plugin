<?php
/**
 * Summary DTO
 *
 * Carries post-job statistics returned to the front-end
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\DTO;

class SummaryDTO {

	public function __construct(
		public readonly string $jobId,
		public readonly int    $totalProcessed,
		public readonly float  $averageAdjustment,
		public readonly string $startedAt,
	) {}

	/**
	 * Serialise to an array suitable for a REST response
	 */
	public function toArray(): array {
		return [
			'job_id'             => $this->jobId,
			'total_processed'    => $this->totalProcessed,
			'average_adjustment' => round( $this->averageAdjustment, 2 ),
			'started_at'         => $this->startedAt,
		];
	}
}
