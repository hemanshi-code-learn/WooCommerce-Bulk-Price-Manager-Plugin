<?php
/**
 * Service Provider Interface
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Interfaces;

interface ServiceProviderInterface {

	/**
	 * Register bindings and WordPress hooks.
	 */
	public function register(): void;
}
