<?php
/**
 * Application Bootstrap.
 *
 * Instantiates the DI container and registers all service providers
 * in the correct order.
 */
declare(strict_types=1);

namespace WCBulkPriceManager\Application;

use WCBulkPriceManager\Interfaces\ServiceProviderInterface;
use WCBulkPriceManager\Providers\DatabaseServiceProvider;
use WCBulkPriceManager\Providers\AdminServiceProvider;
use WCBulkPriceManager\Providers\ApiServiceProvider;

class Bootstrap {

	/**
	 * Boot the application.
	 *
	 * Registers all service providers against the shared container.
	 */
    public static function boot(): void {
        $container = Container::getInstance();

        /** @var class-string<ServiceProviderInterface>[] $providers */
        $providers = [
            DatabaseServiceProvider::class,
            AdminServiceProvider::class,
            ApiServiceProvider::class,
        ];

        foreach ( $providers as $providerClass ) {
   			/** @var ServiceProviderInterface $provider */
            $provider = new $providerClass( $container );
            $provider->register();
        }
    }
}