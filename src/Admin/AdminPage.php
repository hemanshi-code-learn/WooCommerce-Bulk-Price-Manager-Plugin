<?php
/**
 * Admin Page
 *
 * Fully server-side rendered. No JavaScript UI — all form submissions
 * are handled here via PHP, which calls the plugin's own REST API
 * internally via wp_remote_post / wp_remote_get.
 *
 * @package WCBulkPriceManager\Admin
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Admin;

use WCBulkPriceManager\Services\SettingsService;
use WCBulkPriceManager\Services\PriceService;

class AdminPage {

	private const PAGE_SLUG = 'wc-bulk-price-manager';

	public function __construct( private readonly SettingsService $settingsService ) {}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function registerMenu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Bulk Price Manager', 'wc-bulk-price-manager' ),
			__( 'Bulk Price Manager', 'wc-bulk-price-manager' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			[ $this, 'renderPage' ]
		);
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing (CSS only — no JS)
	// -------------------------------------------------------------------------

	public function enqueueAssets( string $hook ): void {
		if ( ! str_ends_with( $hook, '_' . self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wc-bpm-admin',
			WC_BPM_URL . 'assets/css/admin.css',
			[],
			WC_BPM_VERSION
		);

		wp_enqueue_script(
			'wc-bpm-admin',
			WC_BPM_URL . 'assets/js/admin.js',
			[],
			WC_BPM_VERSION,
			true
		);

		// Pass nonce + REST root to JS (used by the batch-progress runner).
		wp_localize_script( 'wc-bpm-admin', 'wcBpm', [
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'restBase' => esc_url_raw( rest_url() ),
		] );
	}

	// -------------------------------------------------------------------------
	// REST API helpers (internal server-side calls via rest_do_request)
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a request to the plugin's own REST API internally.
	 *
	 * Uses rest_do_request() instead of wp_remote_request() so that the call
	 * stays in-process and is authenticated via current_user_can() rather than
	 * the cookie+nonce scheme (which only works in a real browser context and
	 * always fails for server-side HTTP calls, producing "Cookie check failed").
	 */
	private function restRequest( string $method, string $path, array $body = [] ): array {
		$request = new \WP_REST_Request( $method, '/wc-bpm/v1' . $path );

		if ( ! empty( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
			$request->set_body_params( $body );
		}

		$response = rest_do_request( $request );
		$server   = rest_get_server();
		$data     = $server->response_to_data( $response, false );

		return is_array( $data ) ? $data : [ 'success' => false, 'message' => 'Invalid response.' ];
	}

	private function restGet( string $path ): array {
		return $this->restRequest( 'GET', $path );
	}

	private function restPost( string $path, array $body = [] ): array {
		return $this->restRequest( 'POST', $path, $body );
	}

	// -------------------------------------------------------------------------
	// Handle form submissions BEFORE any output
	// -------------------------------------------------------------------------

	/**
	 * Process POST submissions. Called on admin_init so headers aren't sent yet.
	 */
	public function handlePost(): void {
		if ( ! isset( $_POST['wc_bpm_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-bulk-price-manager' ) );
		}

		check_admin_referer( 'wc_bpm_form' );

		$action = sanitize_key( $_POST['wc_bpm_action'] );

		if ( $action === 'save_settings' ) {
			$this->handleSaveSettings();
		} elseif ( $action === 'run_job' ) {
			$this->handleRunJob();
		} elseif ( $action === 'rollback_job' ) {
			$this->handleRollback();
		}
	}

	private function handleSaveSettings(): void {
		$operation    = sanitize_key( $_POST['operation']   ?? 'increase' );
		$amount_type  = sanitize_key( $_POST['amount_type'] ?? 'flat' );
		$amount       = (float) ( $_POST['amount'] ?? 0 );
		$excluded_ids = array_map( 'absint', (array) ( $_POST['excluded_ids'] ?? [] ) );

		$result = $this->restPost( '/settings', [
			'operation'    => $operation,
			'amount_type'  => $amount_type,
			'amount'       => $amount,
			'excluded_ids' => $excluded_ids,
		] );

		if ( ! empty( $result['success'] ) ) {
			$this->redirect( [ 'status' => 'saved' ] );
		} else {
			$this->redirect( [ 'status' => 'error', 'msg' => rawurlencode( $result['message'] ?? 'Save failed.' ) ] );
		}
	}

	private function handleRunJob(): void {
		// Start the job — get a job_id and the total number of batches.
		// The actual batch processing is done client-side in admin.js so the
		// user sees live per-batch progress instead of one silent PHP loop.
		$start = $this->restPost( '/job/start' );

		if ( empty( $start['success'] ) ) {
			$this->redirect( [ 'status' => 'run_error', 'msg' => rawurlencode( $start['message'] ?? 'Could not start job.' ) ] );
			return;
		}

		// Redirect to the progress screen — JS takes it from here.
		$this->redirect( [
			'status'      => 'running',
			'job_id'      => $start['job_id'],
			'total_pages' => (int) $start['total_pages'],
			'batch_size'  => (int) $start['batch_size'],
		] );
	}

	private function handleRollback(): void {
		$jobId = sanitize_text_field( $_POST['rollback_job_id'] ?? '' );
		if ( ! $jobId ) {
			$this->redirect( [ 'status' => 'error', 'msg' => rawurlencode( 'No job ID provided for rollback.' ) ] );
			return;
		}

		// Determine total batches from summary
		$summaryResp = $this->restGet( "/job/{$jobId}/summary" );
		$total       = (int) ( $summaryResp['summary']['total_processed'] ?? 0 );
		$totalPages  = max( 1, (int) ceil( $total / PriceService::BATCH_SIZE ) );
		$restored    = 0;

		for ( $page = 1; $page <= $totalPages; $page++ ) {
			$batch = $this->restPost( "/job/{$jobId}/rollback/batch", [ 'page' => $page ] );

			if ( empty( $batch['success'] ) ) {
				$this->redirect( [
					'status' => 'rollback_error',
					'msg'    => rawurlencode( $batch['message'] ?? 'Rollback batch failed.' ),
				] );
				return;
			}

			$restored += (int) ( $batch['restored'] ?? 0 );

			if ( ! empty( $batch['is_done'] ) ) {
				break;
			}
		}

		$this->redirect( [ 'status' => 'rolled_back', 'restored' => $restored ] );
	}

	private function redirect( array $params ): void {
		$base = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( $params, $base ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function renderPage(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wc-bulk-price-manager' ) );
        }

        $settings   = $this->settingsService->load();
        $status     = sanitize_key( $_GET['status'] ?? '' );
        $msg        = sanitize_text_field( rawurldecode( $_GET['msg'] ?? '' ) );
        $jobId      = sanitize_text_field( $_GET['job_id'] ?? '' );
        $updated    = (int) ( $_GET['updated'] ?? 0 );
        $restored   = (int) ( $_GET['restored'] ?? 0 );
        $totalPages = (int) ( $_GET['total_pages'] ?? 0 );
        $batchSize  = (int) ( $_GET['batch_size'] ?? PriceService::BATCH_SIZE );
        if ( $batchSize < 1 ) {
            $batchSize = PriceService::BATCH_SIZE;
        }

        // Skip expensive REST queries while the progress screen is showing.
        if ( $status === 'running' ) {
            $recentJobs  = [];
            $allProducts = [];
            $excludedIds = $settings->excludedIds;
            include WC_BPM_DIR . 'src/Views/settings-page.php';
            return;
        }

        $jobsResp    = $this->restGet( '/jobs' );
        $recentJobs  = $jobsResp['jobs'] ?? [];

        $productsResp = $this->restGet( '/products/search' );
        $allProducts  = $productsResp['products'] ?? [];

        $excludedIds = $settings->excludedIds;

        // Pass all variables to the view
        include WC_BPM_DIR . 'src/Views/settings-page.php';
    }
}