<?php
/**
 * Log Repository Interface
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Interfaces;

interface LogRepositoryInterface {

	public function saveLog( string $jobId, int $productId, float $oldPrice, float $newPrice, int $userId ): bool;

	public function getLogsByJob( string $jobId, int $page, int $batchSize ): array;

	public function getTotalLogCountForJob( string $jobId ): int;

	public function getJobMetrics( string $jobId ): array;

	public function deleteLogsForJob( string $jobId ): void;
}
