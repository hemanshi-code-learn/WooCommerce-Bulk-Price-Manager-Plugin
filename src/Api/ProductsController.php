<?php
/**
 * Products REST Controller.
 *
 * GET /wc-bpm/v1/products/search?term=foo
 *
 * Powers the exclude-products multi-select / search box in the admin UI.
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Api;

use WP_REST_Request;
use WP_REST_Response;

class ProductsController extends AbstractController {

	public function registerRoutes(): void {
		register_rest_route( self::NAMESPACE, '/products/search', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'search' ],
			'permission_callback' => [ $this, 'permissionManageWooCommerce' ],
			'args'                => [
				'term' => [
					'required'          => false,
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	public function search( WP_REST_Request $request ): WP_REST_Response {
		$term = trim( (string) $request->get_param( 'term' ) );

		$args = [
			'status'   => 'publish',
			'type'     => [ 'simple', 'variable' ],
			'limit'    => 50,
			'return'   => 'objects',
			'orderby'  => 'title',
			'order'    => 'ASC',
		];

		if ( $term !== '' ) {
			$args['s'] = $term;
		}

		$products = wc_get_products( $args );

		$results = array_map( static fn( \WC_Product $p ) => [
			'id'    => $p->get_id(),
			'label' => sprintf( '#%d — %s', $p->get_id(), $p->get_name() ),
		], $products );

		return $this->success( [ 'products' => array_values( $results ) ] );
	}
}
