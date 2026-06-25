<?php
/**
 * Settings Service.
 *
 * Abstracts WordPress option storage so the rest of the codebase
 * never calls get_option / update_option directly.
 *
 * @package WCBulkPriceManager\Services
 */

declare( strict_types=1 );

namespace WCBulkPriceManager\Services;

use WCBulkPriceManager\DTO\SettingsDTO;

final class SettingsService {

	private const OPTION_KEY = 'wc_bpm_settings';

	/**
	 * Persist the settings DTO to the database.
	 */
	public function save( SettingsDTO $dto ): bool {
		return update_option(
			self::OPTION_KEY,
			[
				'operation'    => $dto->operation->value,
				'amount_type'  => $dto->amountType->value,
				'amount'       => $dto->amount,
				'excluded_ids' => $dto->excludedIds,
			]
		);
	}

	/**
	 * Delete saved settings from the database.
	 */
	public function delete(): bool {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Load settings from DB, returning a DTO with safe defaults.
	 */
	public function load(): SettingsDTO {
		$raw = get_option( self::OPTION_KEY, [] );

		return SettingsDTO::fromArray( is_array( $raw ) ? $raw : [] );
	}
}
