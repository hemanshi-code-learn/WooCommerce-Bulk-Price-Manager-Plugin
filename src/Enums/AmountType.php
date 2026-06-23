<?php
/**
 * Amount Type Enum
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Enums;

enum AmountType: string {
	case FLAT       = 'flat';
	case PERCENTAGE = 'percentage';

	public function label(): string {
		return match ( $this ) {
			self::FLAT       => __( 'Flat Value', 'wc-bulk-price-manager' ),
			self::PERCENTAGE => __( 'Percentage (%)', 'wc-bulk-price-manager' ),
		};
	}
}
