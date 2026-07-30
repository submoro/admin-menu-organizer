<?php
/**
 * Recursive whitelist sanitiser for layout arrays.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * Reduces an arbitrary array to a layout that is safe to store and render.
 *
 * This is a whitelist, not a filter. Every key in the output is one this class
 * put there. Unknown keys are dropped rather than passed through, unknown group
 * IDs are dropped rather than trusted, and every scalar is coerced to its
 * expected type. SPEC section 8 requires exactly this, and it is the only reason
 * the renderer can treat a stored layout as trustworthy.
 *
 * Two inputs bound what may survive:
 *
 * - $valid_groups, so a layout cannot name a category that does not exist.
 * - $live_slugs, so a layout cannot resurrect a menu item belonging to a plugin
 *   that has since been deactivated. SPEC section 8 requires stored slugs to be
 *   validated against the live menu before use, and this is where that happens.
 *
 * Pure. Calls no WordPress function, so the sanitiser is unit-testable in full.
 * That matters more here than anywhere else in the plugin: this class is the
 * boundary between untrusted stored data and everything that renders.
 *
 * @since 1.0.0
 */
final class Layout_Sanitizer {

	/**
	 * Longest accepted group label, in characters.
	 *
	 * Long enough for any reasonable translated label, short enough that a
	 * hostile payload cannot bloat the options table.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_LABEL_LENGTH = 60;

	/**
	 * Largest number of groups accepted in one layout.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_GROUPS = 40;

	/**
	 * Sanitises a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $raw          Untrusted value, typically freshly migrated.
	 * @param string[] $valid_groups Group IDs that may appear.
	 * @param string[] $live_slugs   Menu slugs present in the live menu.
	 * @return array Canonical layout. Always has schema and groups keys.
	 */
	public static function sanitize( $raw, array $valid_groups, array $live_slugs ): array {
		$raw = is_array( $raw ) ? $raw : array();

		$allowed_groups = array();
		foreach ( $valid_groups as $group_id ) {
			if ( is_string( $group_id ) && '' !== $group_id ) {
				$allowed_groups[ $group_id ] = true;
			}
		}

		$allowed_slugs = array();
		foreach ( $live_slugs as $slug ) {
			if ( is_string( $slug ) && '' !== $slug ) {
				$allowed_slugs[ $slug ] = true;
			}
		}

		$groups      = array();
		$seen_groups = array();
		$claimed     = array();

		$raw_groups = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? $raw['groups'] : array();

		foreach ( $raw_groups as $raw_group ) {
			if ( count( $groups ) >= self::MAX_GROUPS ) {
				break;
			}

			$group = self::sanitize_group( $raw_group, $allowed_groups, $allowed_slugs, $seen_groups, $claimed );

			if ( null === $group ) {
				continue;
			}

			$seen_groups[ $group['id'] ] = true;
			$groups[]                    = $group;
		}

		$layout = array(
			'schema' => Migrations::CURRENT,
			'groups' => $groups,
		);

		if ( isset( $raw['ungrouped_label'] ) ) {
			$label = self::sanitize_label( $raw['ungrouped_label'] );

			if ( '' !== $label ) {
				$layout['ungrouped_label'] = $label;
			}
		}

		return $layout;
	}

	/**
	 * Sanitises one group entry.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw            Untrusted group entry.
	 * @param array $allowed_groups Lookup of permitted group IDs.
	 * @param array $allowed_slugs  Lookup of live menu slugs.
	 * @param array $seen_groups    Lookup of group IDs already accepted.
	 * @param array $claimed        Lookup of slugs already claimed, by reference.
	 * @return array|null Sanitised group, or null when the entry is unusable.
	 */
	private static function sanitize_group(
		$raw,
		array $allowed_groups,
		array $allowed_slugs,
		array $seen_groups,
		array &$claimed
	): ?array {
		if ( ! is_array( $raw ) || ! isset( $raw['id'] ) || ! is_string( $raw['id'] ) ) {
			return null;
		}

		$id = self::sanitize_key( $raw['id'] );

		// A group must be one the plugin knows about, and may appear once.
		if ( '' === $id || ! isset( $allowed_groups[ $id ] ) || isset( $seen_groups[ $id ] ) ) {
			return null;
		}

		$items = array();

		if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
			foreach ( $raw['items'] as $slug ) {
				if ( ! is_string( $slug ) || '' === $slug ) {
					continue;
				}

				/*
				 * Two rejections, both required. A slug absent from the live menu
				 * is stale, and honouring it would let a deactivated plugin's
				 * item linger in the layout. A slug already claimed by an earlier
				 * group would otherwise appear twice, which corrupts core's
				 * array_flip() in the menu_order pipeline.
				 */
				if ( ! isset( $allowed_slugs[ $slug ] ) || isset( $claimed[ $slug ] ) ) {
					continue;
				}

				$claimed[ $slug ] = true;
				$items[]          = $slug;
			}
		}

		$group = array(
			'id'    => $id,
			'items' => $items,
		);

		if ( isset( $raw['label'] ) ) {
			$label = self::sanitize_label( $raw['label'] );

			if ( '' !== $label ) {
				$group['label'] = $label;
			}
		}

		if ( isset( $raw['icon'] ) ) {
			$icon = self::sanitize_icon( $raw['icon'] );

			if ( '' !== $icon ) {
				$group['icon'] = $icon;
			}
		}

		if ( isset( $raw['default_open'] ) ) {
			$group['default_open'] = self::sanitize_bool( $raw['default_open'] );
		}

		return $group;
	}

	/**
	 * Reduces a value to a lowercase key of safe characters.
	 *
	 * Mirrors WordPress's sanitize_key() without depending on it, so this class
	 * stays pure.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Untrusted value.
	 * @return string
	 */
	public static function sanitize_key( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}

	/**
	 * Reduces a value to a plain-text group label.
	 *
	 * Group labels are user input. SPEC section 8 requires them stored
	 * raw-sanitised with no HTML permitted, and escaped again at the point of
	 * output. Tags are removed rather than encoded, so that a label cannot smuggle
	 * markup into the menu title, which core emits unescaped.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Untrusted value.
	 * @return string
	 */
	public static function sanitize_label( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$label = (string) $value;

		// Remove script and style bodies outright, then all remaining tags.
		$label = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $label );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- wp_strip_all_tags() would couple this pure class to WordPress; script and style bodies are already removed above, which is the only behavioural difference.
		$label = strip_tags( $label );

		/*
		 * Control characters fall into two groups and must be handled
		 * differently. Tabs, newlines and friends are word separators, so they
		 * become spaces: deleting them would turn "Line\nBreak" into
		 * "LineBreak" and silently corrupt a label. Everything else, null bytes
		 * included, is noise and is removed outright.
		 */
		$label = (string) preg_replace( '/[\t\n\v\f\r]+/', ' ', $label );
		$label = (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', $label );
		$label = (string) preg_replace( '/\s+/u', ' ', $label );
		$label = trim( $label );

		if ( '' === $label ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $label, 0, self::MAX_LABEL_LENGTH );
		}

		return substr( $label, 0, self::MAX_LABEL_LENGTH );
	}

	/**
	 * Reduces a value to a Dashicon class name.
	 *
	 * Only Dashicons are accepted. Allowing an arbitrary URL would mean a layout
	 * could point the sidebar at a remote image, which would both break SPEC
	 * section 8's no-outbound-requests rule and hand an attacker a way to make
	 * the admin issue requests.
	 *
	 * A malformed value is rejected outright rather than stripped down to its
	 * safe characters. Stripping would turn `dashicons-cart"onload="x` into the
	 * harmless but meaningless class `dashicons-cartonloadx`; rejecting it lets
	 * the group fall back to its default icon, which is both predictable and
	 * what the caller actually wants.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Untrusted value.
	 * @return string Valid Dashicon class, or an empty string.
	 */
	public static function sanitize_icon( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$icon = strtolower( trim( (string) $value ) );

		if ( 1 !== preg_match( '/^dashicons-[a-z0-9]+(?:-[a-z0-9]+)*$/', $icon ) ) {
			return '';
		}

		return $icon;
	}

	/**
	 * Coerces a value to a boolean the way a checkbox payload should be read.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Untrusted value.
	 * @return bool
	 */
	public static function sanitize_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (bool) (int) $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}

	/**
	 * Sanitises a list of collapsed group IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $raw          Untrusted value, typically a REST payload.
	 * @param string[] $valid_groups Group IDs that may appear.
	 * @return string[] Unique valid group IDs, order preserved.
	 */
	public static function sanitize_collapsed( $raw, array $valid_groups ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$allowed = array();
		foreach ( $valid_groups as $group_id ) {
			if ( is_string( $group_id ) && '' !== $group_id ) {
				$allowed[ $group_id ] = true;
			}
		}

		$collapsed = array();

		foreach ( $raw as $group_id ) {
			if ( ! is_string( $group_id ) ) {
				continue;
			}

			$group_id = self::sanitize_key( $group_id );

			/*
			 * The catch-all is never collapsible. SPEC section 5.1 requires it to
			 * be always visible, so accepting it here would let a crafted request
			 * hide the one group guaranteed to contain anything unrecognised.
			 */
			if ( '' === $group_id || Categories::UNGROUPED === $group_id ) {
				continue;
			}

			if ( isset( $allowed[ $group_id ] ) && ! in_array( $group_id, $collapsed, true ) ) {
				$collapsed[] = $group_id;
			}
		}

		return $collapsed;
	}
}
