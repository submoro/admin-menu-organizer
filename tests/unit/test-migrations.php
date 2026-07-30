<?php
/**
 * Unit tests for the schema migration chain.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use AMORG\Migrations;
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
			'schema' => Migrations::CURRENT,
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

		$this->assertSame( Migrations::CURRENT, $out['schema'] );
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

		$this->assertSame( Migrations::CURRENT, $out['schema'] );
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
	public function test_schema_1_to_2_shortens_the_labels_that_clipped(): void {
		$out = Migrations::migrate(
			array(
				'schema' => 1,
				'groups' => array(
					array(
						'id'    => 'design',
						'label' => 'Design & Layout',
						'items' => array( 'themes.php' ),
					),
					array(
						'id'    => 'seo',
						'label' => 'SEO & Marketing',
						'items' => array(),
					),
					array(
						'id'    => 'security',
						'label' => 'Security & Backup',
						'items' => array(),
					),
					array(
						'id'    => 'users',
						'label' => 'Users & Access',
						'items' => array(),
					),
					array(
						'id'    => 'tools',
						'label' => 'Tools & System',
						'items' => array( 'tools.php' ),
					),
				),
			)
		);

		$this->assertSame( 2, $out['schema'] );
		$this->assertSame(
			array( 'Design', 'Marketing', 'Security', 'Users', 'Tools' ),
			array_column( $out['groups'], 'label' )
		);
	}

	/**
	 * A group the administrator renamed is left exactly as they set it, even if
	 * their name is long. Their choice outranks our default.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_schema_1_to_2_respects_a_renamed_group(): void {
		$out = Migrations::migrate(
			array(
				'schema' => 1,
				'groups' => array(
					array(
						'id'    => 'design',
						'label' => 'Look and feel, mostly',
						'items' => array(),
					),
					array(
						'id'    => 'seo',
						'label' => 'SEO & Marketing',
						'items' => array(),
					),
				),
			)
		);

		$this->assertSame( 'Look and feel, mostly', $out['groups'][0]['label'] );
		$this->assertSame( 'Marketing', $out['groups'][1]['label'] );
	}

	/**
	 * The label migration never touches membership or order, so it cannot move
	 * anybody's menu items.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_schema_1_to_2_preserves_membership_and_order(): void {
		$before = array(
			'schema' => 1,
			'groups' => array(
				array(
					'id'    => 'design',
					'label' => 'Design & Layout',
					'items' => array( 'themes.php', 'elementor' ),
				),
				array(
					'id'    => 'content',
					'label' => 'Content',
					'items' => array( 'edit.php' ),
				),
			),
		);

		$out = Migrations::migrate( $before );

		$this->assertSame( array( 'design', 'content' ), array_column( $out['groups'], 'id' ) );
		$this->assertSame( array( 'themes.php', 'elementor' ), $out['groups'][0]['items'] );
		$this->assertSame( array( 'edit.php' ), $out['groups'][1]['items'] );
	}

	/**
	 * A schema 0 payload passes through both steps and lands at the current
	 * schema with the short labels, not stalling at 1.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_migration_chain_runs_end_to_end(): void {
		$out = Migrations::migrate(
			array(
				'design' => array( 'themes.php' ),
				'seo'    => array( 'wpseo_dashboard' ),
			)
		);

		$this->assertSame( Migrations::CURRENT, $out['schema'] );

		foreach ( $out['groups'] as $group ) {
			if ( isset( $group['label'] ) && is_string( $group['label'] ) ) {
				$this->assertStringNotContainsString( '&', $group['label'] );
			}
		}
	}

	/**
	 * A layout carrying the CURRENT schema but a superseded label is still healed.
	 *
	 * This is the case the schema migration cannot reach, and it is not
	 * hypothetical: saving from the Groups tab immediately after an update writes
	 * exactly this, because the form field was populated before the default
	 * changed. On a live site it left every group label clipped even though the
	 * migration and its test both passed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_labels_are_normalised_even_at_the_current_schema(): void {
		$defaults = array(
			'design'   => 'Design',
			'seo'      => 'Marketing',
			'security' => 'Security',
			'tools'    => 'Tools',
		);

		$out = Migrations::normalise_labels(
			array(
				'schema' => Migrations::CURRENT,
				'groups' => array(
					array(
						'id'    => 'design',
						'label' => 'Design & Layout',
					),
					array(
						'id'    => 'seo',
						'label' => 'SEO & Marketing',
					),
					array(
						'id'    => 'security',
						'label' => 'Security & Backup',
					),
					array(
						'id'    => 'tools',
						'label' => 'Tools & System',
					),
				),
			),
			$defaults
		);

		$this->assertSame(
			array( 'Design', 'Marketing', 'Security', 'Tools' ),
			array_column( $out['groups'], 'label' )
		);
	}

	/**
	 * Normalisation leaves a label the administrator chose completely alone.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_normalisation_never_overwrites_a_custom_label(): void {
		$out = Migrations::normalise_labels(
			array(
				'groups' => array(
					array(
						'id'    => 'design',
						'label' => 'Look and feel',
					),
					array(
						'id'    => 'seo',
						'label' => 'Growth',
					),
					array(
						'id'    => 'tools',
						'label' => '',
					),
				),
			),
			array(
				'design' => 'Design',
				'seo'    => 'Marketing',
				'tools'  => 'Tools',
			)
		);

		$this->assertSame( 'Look and feel', $out['groups'][0]['label'] );
		$this->assertSame( 'Growth', $out['groups'][1]['label'] );
		$this->assertSame( '', $out['groups'][2]['label'] );
	}

	/**
	 * An old default belonging to a different group is not applied across.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_normalisation_is_scoped_to_the_owning_group(): void {
		$out = Migrations::normalise_labels(
			array(
				'groups' => array(
					// A user who named their content group after the old design one.
					array(
						'id'    => 'content',
						'label' => 'Design & Layout',
					),
				),
			),
			array(
				'content' => 'Content',
				'design'  => 'Design',
			)
		);

		$this->assertSame( 'Design & Layout', $out['groups'][0]['label'] );
	}

	/**
	 * Normalisation is idempotent and survives junk without warnings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_normalisation_is_idempotent_and_defensive(): void {
		$defaults = array( 'design' => 'Design' );

		$once  = Migrations::normalise_labels(
			array(
				'groups' => array(
					array(
						'id'    => 'design',
						'label' => 'Design & Layout',
					),
				),
			),
			$defaults
		);
		$twice = Migrations::normalise_labels( $once, $defaults );

		$this->assertSame( $once, $twice );

		// No groups, wrong types, missing keys.
		$this->assertSame( array(), Migrations::normalise_labels( array(), $defaults ) );
		$this->assertSame(
			array( 'groups' => 'nope' ),
			Migrations::normalise_labels( array( 'groups' => 'nope' ), $defaults )
		);
		$this->assertSame(
			array( 'groups' => array( array( 'id' => 'design' ), 'junk', 5 ) ),
			Migrations::normalise_labels( array( 'groups' => array( array( 'id' => 'design' ), 'junk', 5 ) ), $defaults )
		);
	}

	/**
	 * Whether a stored value needs migrating.
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
		$this->assertTrue( Migrations::needs_migration( array( 'schema' => 1 ) ) );
		$this->assertTrue( Migrations::needs_migration( array( 'schema' => 99 ) ) );
		$this->assertFalse( Migrations::needs_migration( array( 'schema' => Migrations::CURRENT ) ) );
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

		$this->assertSame( Migrations::CURRENT, $out['schema'] );
		$this->assertCount( 1, $out['groups'] );
	}
}
