<?php
/**
 * Unit tests for the default category labels.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Guards the default labels against outgrowing the sidebar.
 *
 * This exists because of a defect found on a real site: labels taken verbatim
 * from the specification — "Design & Layout", "Security & Backup",
 * "Users & Access", "SEO & Marketing", "Tools & System" — all rendered clipped
 * as "DESIGN & LA…". The collapsed admin sidebar is 160px wide, and once the
 * icon, the chevron and a possible update badge have taken their share, roughly
 * 90px is left for the label. At 12px uppercase with letter-spacing that is
 * about 13 characters.
 *
 * Nothing in the code was wrong; the labels simply did not fit the UI they were
 * written for. Since `text-overflow: ellipsis` hides the problem rather than
 * announcing it, a test is the only thing that keeps it fixed.
 *
 * @since 1.0.0
 */
final class Test_Category_Labels extends TestCase {

	/**
	 * The widest a default label may be before the sidebar clips it.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const MAX_LABEL_LENGTH = 13;

	/**
	 * Loads the bundled category definitions.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function definitions(): array {
		return require dirname( __DIR__, 2 ) . '/includes/data/categories.php';
	}

	/**
	 * Every default label fits the sidebar without being clipped.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_every_default_label_fits_the_sidebar(): void {
		$too_long = array();

		foreach ( $this->definitions() as $definition ) {
			$length = mb_strlen( $definition['label'] );

			if ( $length > self::MAX_LABEL_LENGTH ) {
				$too_long[] = sprintf( '%s (%d chars)', $definition['label'], $length );
			}
		}

		$this->assertSame(
			array(),
			$too_long,
			'These labels will render clipped in the sidebar: ' . implode( ', ', $too_long )
		);
	}

	/**
	 * No default label contains an ampersand.
	 *
	 * Every label that was clipped on the real site was a compound one joined by
	 * "&". A single noun is both shorter and easier to scan down a sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_no_default_label_is_a_compound(): void {
		foreach ( $this->definitions() as $definition ) {
			$this->assertStringNotContainsString(
				'&',
				$definition['label'],
				"Compound label '{$definition['label']}' will not fit; use a single noun."
			);
		}
	}

	/**
	 * Labels are distinct, so two groups cannot look identical in the sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_labels_are_unique(): void {
		$labels = array_column( $this->definitions(), 'label' );

		$this->assertSame( array_unique( $labels ), $labels, 'Two groups share a label.' );
	}

	/**
	 * Group IDs are unique, lowercase and slug-safe, since they become CSS class
	 * names and body classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_ids_are_slug_safe_and_unique(): void {
		$ids = array_column( $this->definitions(), 'id' );

		$this->assertSame( array_unique( $ids ), $ids, 'Two groups share an ID.' );

		foreach ( $ids as $id ) {
			$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9-]*$/', $id );
		}
	}

	/**
	 * Every group declares an icon, so no header renders iconless.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_every_group_declares_a_dashicon(): void {
		foreach ( $this->definitions() as $definition ) {
			$this->assertStringStartsWith(
				'dashicons-',
				$definition['icon'],
				"Group '{$definition['id']}' has no dashicon."
			);
		}
	}
}
