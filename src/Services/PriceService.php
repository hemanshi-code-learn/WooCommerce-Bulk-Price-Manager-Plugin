<?php
/**
 * Price Service.
 *
 * Contains all business logic for:
 *  - Fetching the product list (with exclusions)
 *  - Computing the new price for a single product
 *  - Processing a single batch and persisting audit logs
 *
 * @package WCBulkPriceManager\Services
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Services;

use WCBulkPriceManager\DTO\SettingsDTO;
use WCBulkPriceManager\DTO\SummaryDTO;
use WCBulkPriceManager\Enums\AmountType;
use WCBulkPriceManager\Enums\Operation;
use WCBulkPriceManager\Interfaces\LogRepositoryInterface;

final class PriceService {

	public const BATCH_SIZE = 20;

	public function __construct(
		private readonly LogRepositoryInterface $logRepository
	) {}

	// -------------------------------------------------------------------------
	// Product list helpers
	// -------------------------------------------------------------------------

	/**
	 * Total number of products that will be processed given current settings.
	 *
	 * Counts only simple products + variable product parents.
	 *
	 * @param int[] $excludedIds
	 */
	public function getTotalProductCount( array $excludedIds = [] ): int {
		return count( $this->getProductIds( $excludedIds ) );
	}

	/**
	 * Return a page of product IDs (0-indexed page, 1 = first batch).
	 *
	 * @param int[] $excludedIds
	 * @return int[]
	 */
	public function getProductIdBatch( int $page, array $excludedIds = [] ): array {
		$all    = $this->getProductIds( $excludedIds );
		$offset = ( $page - 1 ) * self::BATCH_SIZE;

		return array_slice( $all, $offset, self::BATCH_SIZE );
	}

	// -------------------------------------------------------------------------
	// Core batch processor
	// -------------------------------------------------------------------------

	/**
	 * Process one batch of products.
	 *
	 * Updates regular_price / sale_price for simple products, and the
	 * regular_price for every variation child when the parent is variable.
	 *
	 * @param int[]      $productIds IDs returned by getProductIdBatch().
	 * @param SettingsDTO $settings
	 * @param string      $jobId     Unique identifier for this run (UUID v4).
	 * @param int         $userId    WordPress user initiating the job.
	 *
	 * @return int Number of products / variations actually updated.
	 */
	public function processBatch(
		array       $productIds,
		SettingsDTO $settings,
		string      $jobId,
		int         $userId
	): int {
		$updated = 0;

		foreach ( $productIds as $productId ) {
			$product = wc_get_product( $productId );

			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				/** @var \WC_Product_Variable $product */
				foreach ( $product->get_children() as $variationId ) {
					$variation = wc_get_product( $variationId );
					if ( ! $variation ) {
						continue;
					}
					if ( $this->applyPrice( $variation, $settings, $jobId, $userId ) ) {
						$updated++;
					}
				}
			} else {
				if ( $this->applyPrice( $product, $settings, $jobId, $userId ) ) {
					$updated++;
				}
			}
		}

		return $updated;
	}

	// -------------------------------------------------------------------------
	// Summary
	// -------------------------------------------------------------------------

	/**
	 * Build a SummaryDTO from the persisted log for a completed job.
	 */
	public function buildSummary( string $jobId ): SummaryDTO {
		$metrics = $this->logRepository->getJobMetrics( $jobId );

		return new SummaryDTO(
			jobId:             $jobId,
			totalProcessed:    (int)    ( $metrics['total_processed'] ?? 0 ),
			averageAdjustment: (float)  ( $metrics['avg_adjustment']  ?? 0 ),
			startedAt:         (string) ( $metrics['started_at']      ?? '' ),
		);
	}

	// -------------------------------------------------------------------------
	// Rollback
	// -------------------------------------------------------------------------

	/**
	 * Rollback a single batch of log entries to their original prices.
	 */
	public function rollbackBatch( string $jobId, int $page ): int {
		$logs    = $this->logRepository->getLogsByJob( $jobId, $page, self::BATCH_SIZE );
		$updated = 0;

		foreach ( $logs as $row ) {
			$product = wc_get_product( (int) $row['product_id'] );
			if ( ! $product ) {
				continue;
			}

			$product->set_regular_price( (string) $row['old_price'] );

			// Clear sale price only when restoring to old state.
			if ( (float) $product->get_sale_price() !== (float) $product->get_regular_price() ) {
				$product->set_sale_price( $product->get_sale_price() );
			}

			$product->save();
			wc_delete_product_transients( $product->get_id() );
			$updated++;
		}

		return $updated;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Apply the price delta to a single product/variation and log the change.
	 */
	private function applyPrice(
		\WC_Product $product,
		SettingsDTO $settings,
		string      $jobId,
		int         $userId
	): bool {
		$oldPrice = (float) $product->get_regular_price();

		// Skip products with no price set.
		if ( $oldPrice <= 0 ) {
			return false;
		}

		$delta    = $this->calculateDelta( $oldPrice, $settings );
		$newPrice = $this->applyDelta( $oldPrice, $delta, $settings->operation );

		// Prevent negative prices.
		$newPrice = max( 0.0, $newPrice );

		// No change — skip silently.
		if ( abs( $newPrice - $oldPrice ) < 0.0001 ) {
			return false;
		}

		$product->set_regular_price( wc_format_decimal( $newPrice ) );

		// Also shift the sale price if one is active.
		$salePrice = (float) $product->get_sale_price();
		if ( $salePrice > 0 ) {
			$newSale = max( 0.0, $this->applyDelta( $salePrice, $delta, $settings->operation ) );
			$product->set_sale_price( wc_format_decimal( $newSale ) );
		}

		$product->save();
		wc_delete_product_transients( $product->get_id() );

		$this->logRepository->saveLog( $jobId, $product->get_id(), $oldPrice, $newPrice, $userId );

		return true;
	}

	/**
	 * Calculate the absolute price delta for flat/percentage modes.
	 */
	private function calculateDelta( float $oldPrice, SettingsDTO $settings ): float {
		return match ( $settings->amountType ) {
			AmountType::FLAT       => $settings->amount,
			AmountType::PERCENTAGE => round( $oldPrice * ( $settings->amount / 100 ), 4 ),
		};
	}

	/**
	 * Apply the delta with the correct direction.
	 */
	private function applyDelta( float $price, float $delta, Operation $operation ): float {
		return match ( $operation ) {
			Operation::INCREASE => $price + $delta,
			Operation::DECREASE => $price - $delta,
		};
	}

	/**
	 * Return all product IDs (simple + variable parents) excluding the given IDs
	 */
	private function getProductIds( array $excludedIds ): array {
		$args = [
			'status'       => 'publish',
			'type'         => [ 'simple', 'variable' ],
			'limit'        => -1,
			'return'       => 'ids',
			'orderby'      => 'ID',
			'order'        => 'ASC',
		];

		if ( ! empty( $excludedIds ) ) {
			$args['exclude'] = $excludedIds;
		}

		return wc_get_products( $args );
	}
}
