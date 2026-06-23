<?php
/**
 * Settings REST Controller.
 *
 * GET  /wp-json/wc-bpm/v1/settings  — retrieve current settings
 * POST /wp-json/wc-bpm/v1/settings  — save settings
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Api;

use WCBulkPriceManager\DTO\SettingsDTO;
use WCBulkPriceManager\Services\SettingsService;
use WP_REST_Request;
use WP_REST_Response;

class SettingsController extends AbstractController {

	public function __construct( private readonly SettingsService $settingsService ) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'getSettings' ],
					'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'saveSettings' ],
					'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
					'args'                => $this->getSettingsArgs(),
				],
			]
		);
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	public function getSettings( WP_REST_Request $request ): WP_REST_Response {
		$dto = $this->settingsService->load();

		return $this->success( [
			'settings' => [
				'operation'    => $dto->operation->value,
				'amount_type'  => $dto->amountType->value,
				'amount'       => $dto->amount,
				'excluded_ids' => $dto->excludedIds,
			],
		] );
	}

	public function saveSettings( WP_REST_Request $request ): WP_REST_Response {
		$dto = SettingsDTO::fromArray( [
			'operation'    => $request->get_param( 'operation' ),
			'amount_type'  => $request->get_param( 'amount_type' ),
			'amount'       => $request->get_param( 'amount' ),
			'excluded_ids' => $request->get_param( 'excluded_ids' ) ?? [],
		] );

		$this->settingsService->save( $dto );

		return $this->success( [ 'message' => __( 'Settings saved.', 'wc-bulk-price-manager' ) ] );
	}

	// -------------------------------------------------------------------------
	// Argument schema
	// -------------------------------------------------------------------------
	private function getSettingsArgs(): array {
		return [
			'operation'    => [
				'required'          => true,
				'type'              => 'string',
				'enum'              => [ 'increase', 'decrease' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'amount_type'  => [
				'required'          => true,
				'type'              => 'string',
				'enum'              => [ 'flat', 'percentage' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'amount'       => [
				'required'          => true,
				'type'              => 'number',
				'minimum'           => 0,
				'sanitize_callback' => static fn( $v ) => (float) $v, 
			],
			'excluded_ids' => [
				'required'          => false,
				'type'              => 'array',
				'default'           => [],
				'items'             => [ 'type' => 'integer' ],
				'sanitize_callback' => static fn( $v ) => array_map( 'absint', (array) $v ),
			],
		];
	}
}