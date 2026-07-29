<?php
/**
 * Schema migrations for stored layouts.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

namespace WPAMO;

defined( 'ABSPATH' ) || exit;

/**
 * Brings a stored layout up to the current schema.
 *
 * SPEC section 4.6 requires a migration chain and forbids assuming a shape.
 * Every stored payload carries a schema number, and anything without one is
 * treated as schema 0, which covers both a hand-edited option and the flat
 * group-to-slugs map an early version might plausibly have written.
 *
 * Migrations run before sanitisation, not after. Their job is to reshape, not to
 * validate: whatever they produce still passes through Layout_Sanitizer, so a
 * migration cannot introduce an unsafe value.
 *
 * Pure. Calls no WordPress function.
 *
 * @since 1.0.0
 */
final class Migrations {

	/**
	 * The schema version this release writes.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CURRENT = 1;

	/**
	 * Migrates a raw stored value to the current schema.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw Whatever was found in the option or user meta.
	 * @return array A layout array at schema CURRENT. Never partially migrated.
	 */
	public static function migrate( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::empty_layout();
		}

		$schema = isset( $raw['schema'] ) ? (int) $raw['schema'] : 0;

		// A payload from a future version cannot be understood. Refusing it and
		// falling back to auto-detection is safer than half-reading it, which is
		// what happens if someone restores a newer database over older code.
		if ( $schema > self::CURRENT ) {
			return self::empty_layout();
		}

		if ( 0 === $schema ) {
			$raw    = self::migrate_0_to_1( $raw );
			$schema = 1;
		}

		$raw['schema'] = $schema;

		return $raw;
	}

	/**
	 * Whether a stored value needs migrating.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw Whatever was found in the option or user meta.
	 * @return bool
	 */
	public static function needs_migration( $raw ): bool {
		if ( ! is_array( $raw ) ) {
			return true;
		}

		return ( isset( $raw['schema'] ) ? (int) $raw['schema'] : 0 ) !== self::CURRENT;
	}

	/**
	 * Schema 0 to 1.
	 *
	 * Schema 0 is anything without a schema key. Two shapes are recognised. The
	 * first is a payload that already looks like schema 1 but was written before
	 * the key existed, in which case only the key is missing. The second is a
	 * flat map of group ID to a list of slugs, which is the obvious shape an
	 * early implementation would have reached for.
	 *
	 * A human's group assignments are preserved in both cases. That is the whole
	 * point of the migration: SPEC section 12.1 requires it explicitly.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw Stored value at schema 0.
	 * @return array Layout at schema 1.
	 */
	private static function migrate_0_to_1( array $raw ): array {
		// Already the right shape, just unversioned.
		if ( isset( $raw['groups'] ) && is_array( $raw['groups'] ) ) {
			return $raw;
		}

		// Flat map of group ID to slugs.
		$groups = array();

		foreach ( $raw as $group_id => $items ) {
			if ( 'schema' === $group_id || 'ungrouped_label' === $group_id ) {
				continue;
			}

			if ( ! is_string( $group_id ) || '' === $group_id || ! is_array( $items ) ) {
				continue;
			}

			$groups[] = array(
				'id'    => $group_id,
				'items' => array_values( $items ),
			);
		}

		$layout = array( 'groups' => $groups );

		if ( isset( $raw['ungrouped_label'] ) ) {
			$layout['ungrouped_label'] = $raw['ungrouped_label'];
		}

		return $layout;
	}

	/**
	 * An empty layout at the current schema.
	 *
	 * Signals "nothing usable stored", which the repository reads as an
	 * instruction to fall back to auto-detection.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function empty_layout(): array {
		return array(
			'schema' => self::CURRENT,
			'groups' => array(),
		);
	}
}
