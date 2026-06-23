=== WooCommerce Bulk Price Manager ===
Contributors: studio613
Tags: woocommerce, bulk, price, products
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance bulk product price modifier for WooCommerce with batch processing, live progress bar, and full rollback support.

== Description ==

WooCommerce Bulk Price Manager lets you increase or decrease the price of all (or a subset of) your WooCommerce products in one click, with:

* **REST API architecture** — no legacy AJAX, no nonce hacks
* **Batch processing** — 20 products per batch to avoid PHP timeouts
* **Live progress bar** — real-time feedback during processing
* **Flat or percentage** adjustment types
* **Product exclusion** — searchable multi-select to skip specific products
* **Full audit log** — every price change is stored in a custom DB table
* **One-click rollback** — revert any previous job completely
* **Modern PHP 8.1+** — strict types, enums, DTOs, PSR-4 autoloading, service container

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.
3. Navigate to **WooCommerce → Bulk Price Manager**.

== Changelog ==

= 2.0.0 =
* Complete rewrite: OOP, REST API, batch processing, progress bar, rollback, PSR-4 autoloading, service container, PHP 8.1 enums and DTOs.
