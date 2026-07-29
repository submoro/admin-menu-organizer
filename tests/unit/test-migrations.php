<?php
/**
 * Unit tests for the schema migration chain.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use WPAMO\Migrations;
use PHPUnit\Framework\TestCase;

/**
 * Covers SPEC section 12.1's requirement that migrating from schema 0 to 1
 * preserves a human's group assignments.
 *
 * @since 1.0.0
 */
final class Test_Migrations extends TestCase {

	/**
	 * Anything unusable becomes an empty layout at the current schema, which the
	 * repository reads as "fall back to auto-detection".
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unusable_input_becomes_an_empty_layout(): void {
		foreach ( array( null, false, 0, '', 'string', 3.14 ) as $input ) {
			$out = Migrations::migrate( $input );

			$this->assertSame( Migrations::CURRENT, $out['schema'] );
			$this->assertSame( array(), $out['groups'] );
		}
	}

	/**
	 * A payload already at the current schema passes through unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_current_schema_passes_through(): void {
		$layout = array(
			'schema' => 1,
			'groups' => array(
				array(
					'id'    => 'content',
					'items' => array( 'edit.php' ),
				),
			),
		);

		$this->assertSame( $layout, Migrations::migrate( $layout ) );
		$this->assertFalse( Migrations::needs_migration( $layout ) );
	}

	/**
	 * A schema 1 shaped payload missing only its version key gains it, and keeps
	 * every assignment.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unversioned_but_correctly_shaped_payload_gains_a_schema(): void {
		$out = Migrations::migrate(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php', 'upload.php' ),
					),
					array(
						'id'    => 'commerce',
						'items' => array( 'woocommerce' ),
					),
				),
			)
		);

		$this->assertSame( 1, $out['schema'] );
		$this->assertCount( 2, $out['groups'] );
		$this->assertSame( array( 'edit.php', 'upload.php' ), $out['groups'][0]['items'] );
		$this->assertSame( array( 'woocommerce' ), $out['groups'][1]['items'] );
	}

	/**
	 * A flat group-to-slugs map is reshaped, preserving every assignment and the
	 * order the groups appeared in. This is the assignment-preservation guarantee
	 * SPEC section 12.1 asks for explicitly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_flat_map_migrates_and_preserves_assignments(): void {
		$out = Migrations::migrate(
			array(
				'content'  => array( 'edit.php', 'upload.php' ),
				'commerce' => array( 'woocommerce', 'edit.php?post_type=product' ),
				'tools'    => array( 'tools.php' ),
			)
		);

		$this->assertSame( 1, $out['schema'] );
		$this->assertCount( 3, $out['groups'] );

		$by_id = array();
		foreach ( $out['groups'] as $group ) {
			$by_id[ $group['id'] ] = $group['items'];
		}

		$this->assertSame( array( 'edit.php', 'upload.php' ), $by_id['content'] );
		$this->assertSame( array( 'woocommerce', 'edit.php?post_type=product' ), $by_id['commerce'] );
		$this->assertSame( array( 'tools.php' ), $by_id['tools'] );

		// Group order is preserved, since it determines sidebar order.
		$this->assertSame( array( 'content', 'commerce', 'tools' ), array_column( $out['groups'], 'id' ) );
	}

	/**
	 * A flat map's ungrouped label is carried across.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_flat_map_carries_the_ungrouped_label(): void {
		$out = Migrations::migrate(
			array(
				'content'         => array( 'edit.php' ),
				'ungrouped_label' => 'Everything Else',
			)
		);

		$this->assertSame( 'Everything Else', $out['ungrouped_label'] );
		$this->assertSame( array( 'content' ), array_column( $out['groups'], 'id' ) );
	}

	/**
	 * Junk entries in a flat map are skipped without taking the rest with them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_flat_map_skips_junk_entries(): void {
		$out = Migrations::migrate(
			array(
				'content' => array( 'edit.php' ),
				''        => array( 'orphan.php' ),
				5         => array( 'numeric-key.php' ),
				'tools'   => 'not an array',
				'design'  => array( 'themes.php' ),
			)
		);

		$this->assertSame( array( 'content', 'design' ), array_column( $out['groups'], 'id' ) );
	}

	/**
	 * A payload from a future schema is refused rather than half-read.
	 *
	 * This matters when a newer database is restored over older code. Reading a
	 * shape we do not understand would produce a layout that looks valid and is
	 * subtly wrong; falling back to auto-detection is recoverable.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_future_schema_is_refused(): void {
		$out = Migrations::migrate(
			array(
				'schema'    => 99,
				'groups'    => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php' ),
					),
				),
				'new_thing' => 'from the future',
			)
		);

		$this->assertSame( Migrations::CURRENT, $out['schema'] );
		$this->assertSame( array(), $out['groups'] );
	}

	/**
	 * Migration detection is right about what needs work.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_needs_migration(): void {
		$this->assertTrue( Migrations::needs_migration( null ) );
		$this->assertTrue( Migrations::needs_migration( array() ) );
		$this->assertTrue( Migrations::needs_migration( array( 'content' => array( 'edit.php' ) ) ) );
		$this->assertTrue( Migrations::needs_migration( array( 'schema' => 0 ) ) );
		$this->assertTrue( Migrations::needs_migration( array( 'schema' => 99 ) ) );
		$this->assertFalse( Migrations::needs_migration( array( 'schema' => 1 ) ) );
	}

	/**
	 * Migration is idempotent, since a stored value passes through it on read.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_migration_is_idempotent(): void {
		$raw = array(
			'content'  => array( 'edit.php', 'upload.php' ),
			'commerce' => array( 'woocommerce' ),
		);

		$once  = Migrations::migrate( $raw );
		$twice = Migrations::migrate( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * A schema stored as a string is read as a number, so "1" is not mistaken
	 * for schema 0 and needlessly re-migrated.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_schema_is_read_numerically(): void {
		$out = Migrations::migrate(
			array(
				'schema' => '1',
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php' ),
					),
				),
			)
		);

		$this->assertSame( 1, $out['schema'] );
		$this->assertCount( 1, $out['groups'] );
	}
}
