<?php
/**
 * Database Installer.
 *
 * Creates / upgrades the custom DB table used for job audit logs.
 * Uses dbDelta() so it is safe to run on plugin updates too.
 */
declare(strict_types=1);

namespace WCBulkPriceManager\Database;

class Installer {
    /** DB schema version stored in wp_options */
	private const SCHEMA_VERSION     = '1.0.0';
	private const SCHEMA_VERSION_KEY = 'wc_bpm_db_version';

	/**
	 * Run on plugin activation
	 *
	 * Creates the log table and stores the schema version
	 */
	public static function activate(): void {
		global $wpdb;

		$table          = $wpdb->prefix . 'wc_bulk_price_manager';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          bigint(20)     NOT NULL AUTO_INCREMENT,
			job_id      varchar(64)    NOT NULL,
			product_id  bigint(20)     NOT NULL,
			old_price   decimal(12,4)  NOT NULL,
			new_price   decimal(12,4)  NOT NULL,
			user_id     bigint(20)     NOT NULL,
			created_at  datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_job_id    (job_id),
			KEY idx_product   (product_id),
			KEY idx_user      (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION );
	}
}