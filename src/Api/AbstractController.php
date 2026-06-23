<?php
/**
 * Abstract REST Controller.
 *
 * Provides shared boilerplate for all plugin REST controllers:
 *  - Namespace / version constants
 *  - Common permission callback (manage_woocommerce)
 *  - JSON error / success helpers
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

abstract class AbstractController {

	protected const NAMESPACE = 'wc-bpm/v1';

	// -------------------------------------------------------------------------
	// Route registration (implemented by each controller)
	// -------------------------------------------------------------------------

	abstract public function registerRoutes(): void;

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Require manage_woocommerce capability (shop managers + admins).
	 */
	public function permissionManageWooCommerce(): bool|WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'wc_bpm_forbidden',
				__( 'You do not have permission to perform this action.', 'wc-bulk-price-manager' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Response helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a standardised success response.
	 */
	protected function success( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array_merge( [ 'success' => true ], $data ),
			$status
		);
	}

	/**
	 * Build a standardised error response.
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => false,
				'code'    => $code,
				'message' => $message,
			],
			$status
		);
	}
}
