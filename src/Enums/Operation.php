<?php
/**
 * Operation Enum
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Enums;

enum Operation: string {
	case INCREASE = 'increase';
	case DECREASE = 'decrease';

	public function label(): string {
		return match ( $this ) {
			self::INCREASE => __( 'Increase', 'wc-bulk-price-manager' ),
			self::DECREASE => __( 'Decrease', 'wc-bulk-price-manager' ),
		};
	}
}
