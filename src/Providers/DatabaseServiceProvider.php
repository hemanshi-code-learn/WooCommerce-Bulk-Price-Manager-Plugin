<?php
/**
 * Database Service Provider.
 *
 * Registers repository and database-related bindings in the container
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Providers;

use WCBulkPriceManager\Application\Container;
use WCBulkPriceManager\Interfaces\LogRepositoryInterface;
use WCBulkPriceManager\Interfaces\ServiceProviderInterface;
use WCBulkPriceManager\Repositories\LogRepository;
use WCBulkPriceManager\Services\PriceService;
use WCBulkPriceManager\Services\SettingsService;

class DatabaseServiceProvider implements ServiceProviderInterface {

	public function __construct( private readonly Container $container ) {}

	public function register(): void {
		// Bind the interface to the concrete implementation (singleton).
		$this->container->singleton(
			LogRepositoryInterface::class,
			fn( Container $c ) => new LogRepository()
		);

		// Bind concrete services.
		$this->container->singleton(
			LogRepository::class,
			fn( Container $c ) => $c->get( LogRepositoryInterface::class )
		);

		$this->container->singleton(
			PriceService::class,
			fn( Container $c ) => new PriceService( $c->get( LogRepositoryInterface::class ) )
		);

		$this->container->singleton(
			SettingsService::class,
			fn( Container $c ) => new SettingsService()
		);
	}
}
