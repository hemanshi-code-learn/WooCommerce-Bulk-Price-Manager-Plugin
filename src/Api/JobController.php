<?php
/**
 * Job REST Controller.
 *
 * POST /wc-bpm/v1/job/start           — initialise a job, return job_id + total
 * POST /wc-bpm/v1/job/batch           — process one batch, return progress
 * GET  /wc-bpm/v1/job/{job_id}/summary — job summary after completion
 * POST /wc-bpm/v1/job/{job_id}/rollback/batch — rollback one batch
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Api;

use WCBulkPriceManager\Interfaces\LogRepositoryInterface;
use WCBulkPriceManager\Services\PriceService;
use WCBulkPriceManager\Services\SettingsService;
use WP_REST_Request;
use WP_REST_Response;

class JobController extends AbstractController {

	public function __construct(
		private readonly PriceService           $priceService,
		private readonly SettingsService        $settingsService,
		private readonly LogRepositoryInterface $logRepository,
	) {}

	public function registerRoutes(): void {
		// Start a new job.
		register_rest_route( self::NAMESPACE, '/job/start', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'startJob' ],
			'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
		] );

		// Process one batch.
		register_rest_route( self::NAMESPACE, '/job/batch', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'processBatch' ],
			'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
			'args'                => [
				'job_id' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'page'   => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
			],
		] );

		// Retrieve summary for a completed job.
		register_rest_route( self::NAMESPACE, '/job/(?P<job_id>[a-f0-9\-]{36})/summary', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'getJobSummary' ],
			'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
		] );

		// Rollback a batch of a previous job.
		register_rest_route( self::NAMESPACE, '/job/(?P<job_id>[a-f0-9\-]{36})/rollback/batch', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rollbackBatch' ],
			'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
			'args'                => [
				'page' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
			],
		] );

		// Recent job IDs (for rollback UI).
		register_rest_route( self::NAMESPACE, '/jobs', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'getRecentJobs' ],
			'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
		] );
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Initialise a job: validate settings, count products, return job metadata.
	 */
	public function startJob( WP_REST_Request $request ): WP_REST_Response {
		$settings = $this->settingsService->load();

		if ( $settings->amount <= 0 ) {
			return $this->error( 'invalid_amount', __( 'Amount must be greater than 0.', 'wc-bulk-price-manager' ) );
		}

		$total = $this->priceService->getTotalProductCount( $settings->excludedIds );

		if ( $total === 0 ) {
			return $this->error( 'no_products', __( 'No products found to process.', 'wc-bulk-price-manager' ) );
		}

		$jobId      = wp_generate_uuid4();
		$totalPages = (int) ceil( $total / PriceService::BATCH_SIZE );

		return $this->success( [
			'job_id'       => $jobId,
			'total'        => $total,
			'batch_size'   => PriceService::BATCH_SIZE,
			'total_pages'  => $totalPages,
		] );
	}

	/**
	 * Process one batch and return progress.
	 */
	public function processBatch( WP_REST_Request $request ): WP_REST_Response {
		$jobId    = (string) $request->get_param( 'job_id' );
		$page     = (int)    $request->get_param( 'page' );
		$settings = $this->settingsService->load();
		$userId   = get_current_user_id();

		$ids     = $this->priceService->getProductIdBatch( $page, $settings->excludedIds );
		$updated = $this->priceService->processBatch( $ids, $settings, $jobId, $userId );

		$total      = $this->priceService->getTotalProductCount( $settings->excludedIds );
		$totalPages = (int) ceil( $total / PriceService::BATCH_SIZE );
		$done       = $page >= $totalPages;

		return $this->success( [
			'page'          => $page,
			'updated'       => $updated,
			'is_done'       => $done,
			'total_pages'   => $totalPages,
		] );
	}

	/**
	 * Return the summary DTO as JSON.
	 */
	public function getJobSummary( WP_REST_Request $request ): WP_REST_Response {
		$jobId = (string) $request->get_param( 'job_id' );

		$total = $this->logRepository->getTotalLogCountForJob( $jobId );
		if ( $total === 0 ) {
			return $this->error( 'not_found', __( 'No job found with that ID.', 'wc-bulk-price-manager' ), 404 );
		}

		$summary = $this->priceService->buildSummary( $jobId );

		return $this->success( [ 'summary' => $summary->toArray() ] );
	}

	/**
	 * Rollback one batch for a given job.
	 */
	public function rollbackBatch( WP_REST_Request $request ): WP_REST_Response {
		$jobId = (string) $request->get_param( 'job_id' );
		$page  = (int)    $request->get_param( 'page' );

		$total      = $this->logRepository->getTotalLogCountForJob( $jobId );
		$totalPages = (int) ceil( $total / PriceService::BATCH_SIZE );

		$restored = $this->priceService->rollbackBatch( $jobId, $page );
		$done     = $page >= $totalPages;

		if ( $done ) {
			$this->logRepository->deleteLogsForJob( $jobId );
		}

		return $this->success( [
			'page'        => $page,
			'restored'    => $restored,
			'is_done'     => $done,
			'total_pages' => $totalPages,
		] );
	}

	/**
	 * Return recent job IDs for the rollback selector.
	 */
	public function getRecentJobs( WP_REST_Request $request ): WP_REST_Response {
		/** @var \WCBulkPriceManager\Repositories\LogRepository $repo */
		$repo = $this->logRepository;
		$jobs = method_exists( $repo, 'getRecentJobIds' ) ? $repo->getRecentJobIds() : [];

		return $this->success( [ 'jobs' => $jobs ] );
	}
}
