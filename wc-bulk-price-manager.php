<?php
/**
 * Plugin Name: WooCommerce Bulk Price Manager
 * Description: High-performance bulk product price modifier using a modern Service Container, Domain Logic Isolation, and WP REST API.
 * Version:     1.0.0
 * Author:      Mervan Agency
 * License:     GPL2
 * Text Domain: wc-bulk-price-manager
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 */

if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define( 'WC_BPM_VERSION', '2.0.0' );
define( 'WC_BPM_FILE', __FILE__ );
define( 'WC_BPM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_BPM_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_BPM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 Autoloader.
 *
 * Maps the WCBulkPriceManager\ namespace to the /src/ directory.
 */
spl_autoload_register(function ($class) {
    $prefix = 'WCBulkPriceManager\\';
    $base_dir = WC_BPM_DIR . 'src/';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Activation hook - install DB tables.
 */
register_activation_hook( WC_BPM_FILE, static function ():void{
    require_once WC_BPM_DIR . 'src/Database/Installer.php';
    \WCBulkPriceManager\Database\Installer::activate();
});

/**
 * Boot the plugin after all plugins are loaded so WooCommerce is available.
 */
add_action( 'plugins_loaded', static function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'WooCommerce Bulk Price Manager requires WooCommerce to be installed and active.', 'wc-bulk-price-manager' )
				. '</p></div>';
		} );
		return;
	}

	load_plugin_textdomain( 'wc-bulk-price-manager', false, WC_BPM_DIR . 'languages' );

	\WCBulkPriceManager\Application\Bootstrap::boot();
} );