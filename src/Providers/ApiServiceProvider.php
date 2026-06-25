<?php
/**
 * API Service Provider.
 *
 * Registers all REST API route controllers on the rest_api_init hook
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Providers;

use WCBulkPriceManager\Api\SettingsController;
use WCBulkPriceManager\Api\JobController;
use WCBulkPriceManager\Api\ProductsController;
use WCBulkPriceManager\Application\Container;
use WCBulkPriceManager\Interfaces\ServiceProviderInterface;
use WCBulkPriceManager\Interfaces\LogRepositoryInterface;
use WCBulkPriceManager\Services\PriceService;
use WCBulkPriceManager\Services\SettingsService;

class ApiServiceProvider implements ServiceProviderInterface {

	public function __construct( private readonly Container $container ) {}

	public function register(): void {
		$this->container->singleton(
			SettingsController::class,
			fn( Container $c ) => new SettingsController( $c->get( SettingsService::class ) )
		);

		$this->container->singleton(
			JobController::class,
			fn( Container $c ) => new JobController(
				$c->get( PriceService::class ),
				$c->get( SettingsService::class ),
				$c->get( LogRepositoryInterface::class )
			)
		);

		$this->container->singleton(
			ProductsController::class,
			fn( Container $c ) => new ProductsController()
		);

		add_action( 'rest_api_init', function (): void {
			$this->container->get( SettingsController::class )->registerRoutes();
			$this->container->get( JobController::class )->registerRoutes();
			$this->container->get( ProductsController::class )->registerRoutes();
		} );
	}
}
