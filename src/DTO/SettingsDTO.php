<?php
/**
 * Settings DTO
 *
 * Immutable value object carrying validated settings from the admin form
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\DTO;

use WCBulkPriceManager\Enums\AmountType;
use WCBulkPriceManager\Enums\Operation;

class SettingsDTO {

	public function __construct(
		public readonly Operation  $operation,
		public readonly AmountType $amountType,
		public readonly float      $amount,
		/** @var int[] */
		public readonly array      $excludedIds,
	) {}

	/**
	 * Construct from a raw associative array (e.g. from get_option())
	 *
	 * Unknown / missing keys receive safe defaults
	 */
	public static function fromArray( array $data ): self {
		return new self(
			operation:   Operation::from( (string) ( $data['operation'] ?? 'increase' ) ),
			amountType:  AmountType::from( (string) ( $data['amount_type'] ?? 'flat' ) ),
			amount:      max( 0.0, (float) ( $data['amount'] ?? 0 ) ),
			excludedIds: array_map( 'intval', (array) ( $data['excluded_ids'] ?? [] ) ),
		);
	}
}
