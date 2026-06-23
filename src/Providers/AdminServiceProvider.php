<?php
/**
 * Admin Service Provider.
 *
 * Registers the WooCommerce sub-menu page and enqueues admin assets
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Providers;

use WCBulkPriceManager\Admin\AdminPage;
use WCBulkPriceManager\Application\Container;
use WCBulkPriceManager\Interfaces\ServiceProviderInterface;
use WCBulkPriceManager\Services\SettingsService;

class AdminServiceProvider implements ServiceProviderInterface {

	public function __construct( private readonly Container $container ) {}

	public function register(): void {
		$this->container->singleton(
			AdminPage::class,
			fn( Container $c ) => new AdminPage( $c->get( SettingsService::class ) )
		);

		add_action( 'admin_init', function (): void {
			if ( isset( $_POST['wc_bpm_action'] ) ) {
				$this->container->get( AdminPage::class )->handlePost();
			}
		} );

		add_action( 'admin_menu', function (): void {
			$this->container->get( AdminPage::class )->registerMenu();
		} );

		add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
			$this->container->get( AdminPage::class )->enqueueAssets( $hook );
		} );
	}
}
