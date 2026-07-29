<?php
/**
 * Unit tests for the layout sanitiser.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use WPAMO\Layout_Sanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Covers the whitelist behaviour that lets the renderer trust stored data.
 *
 * @since 1.0.0
 */
final class Test_Layout_Sanitizer extends TestCase {

	/**
	 * Group IDs treated as valid in these tests.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const GROUPS = array( 'content', 'commerce', 'tools', 'ungrouped' );

	/**
	 * Slugs treated as present in the live menu.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const LIVE = array( 'edit.php', 'upload.php', 'tools.php', 'woocommerce' );

	/**
	 * Sanitises with the fixtures above.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw Untrusted input.
	 * @return array
	 */
	private function clean( $raw ): array {
		return Layout_Sanitizer::sanitize( $raw, self::GROUPS, self::LIVE );
	}

	// ------------------------------------------------------------- Shape.

	/**
	 * The result always has the canonical top-level shape, whatever went in.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_output_shape_is_always_canonical(): void {
		foreach ( array( null, false, 0, 'string', array(), array( 'junk' => 1 ) ) as $input ) {
			$out = $this->clean( $input );

			$this->assertArrayHasKey( 'schema', $out );
			$this->assertArrayHasKey( 'groups', $out );
			$this->assertSame( 1, $out['schema'] );
			$this->assertIsArray( $out['groups'] );
		}
	}

	/**
	 * Unknown top-level keys are dropped, not passed through.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unknown_top_level_keys_are_dropped(): void {
		$out = $this->clean(
			array(
				'groups'    => array(),
				'evil'      => '<script>alert(1)</script>',
				'__proto__' => 'x',
				'callback'  => 'system',
				'schema'    => 99,
			)
		);

		$this->assertSame( array( 'schema', 'groups' ), array_keys( $out ) );
		$this->assertSame( 1, $out['schema'], 'A stored schema must not override the current one.' );
	}

	/**
	 * Unknown keys inside a group are dropped too.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unknown_group_keys_are_dropped(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'       => 'content',
						'items'    => array( 'edit.php' ),
						'onclick'  => 'alert(1)',
						'callback' => 'exec',
						'nested'   => array( 'deep' => array( 'deeper' => true ) ),
					),
				),
			)
		);

		$this->assertSame( array( 'id', 'items' ), array_keys( $out['groups'][0] ) );
	}

	// ------------------------------------------------------------ Groups.

	/**
	 * A group naming an undefined category is rejected outright.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unknown_group_ids_are_rejected(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'not-a-group',
						'items' => array( 'edit.php' ),
					),
					array(
						'id'    => 'content',
						'items' => array( 'upload.php' ),
					),
				),
			)
		);

		$this->assertCount( 1, $out['groups'] );
		$this->assertSame( 'content', $out['groups'][0]['id'] );
	}

	/**
	 * A duplicated group appears once.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_duplicate_groups_are_collapsed(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php' ),
					),
					array(
						'id'    => 'content',
						'items' => array( 'upload.php' ),
					),
				),
			)
		);

		$this->assertCount( 1, $out['groups'] );
		$this->assertSame( array( 'edit.php' ), $out['groups'][0]['items'] );
	}

	/**
	 * The number of groups is bounded, so a payload cannot bloat the option.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_group_count_is_bounded(): void {
		$groups = array();

		for ( $i = 0; $i < 200; $i++ ) {
			$groups[] = array(
				'id'    => 'content',
				'items' => array(),
			);
			$groups[] = array(
				'id'    => 'tools',
				'items' => array(),
			);
		}

		$out = $this->clean( array( 'groups' => $groups ) );

		$this->assertLessThanOrEqual( Layout_Sanitizer::MAX_GROUPS, count( $out['groups'] ) );
	}

	// ------------------------------------------------------------- Items.

	/**
	 * A slug absent from the live menu is rejected, so a deactivated plugin's
	 * item cannot linger in a stored layout. SPEC section 8.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_stale_slugs_are_rejected(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php', 'plugin-since-deactivated', 'upload.php' ),
					),
				),
			)
		);

		$this->assertSame( array( 'edit.php', 'upload.php' ), $out['groups'][0]['items'] );
	}

	/**
	 * A slug claimed by two groups is kept only by the first, since a duplicate
	 * would corrupt core's array_flip() in the menu_order pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_slug_cannot_be_claimed_twice(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php', 'upload.php' ),
					),
					array(
						'id'    => 'tools',
						'items' => array( 'edit.php', 'tools.php' ),
					),
				),
			)
		);

		$this->assertSame( array( 'edit.php', 'upload.php' ), $out['groups'][0]['items'] );
		$this->assertSame( array( 'tools.php' ), $out['groups'][1]['items'] );
	}

	/**
	 * Non-string items are discarded rather than coerced.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_non_string_items_are_discarded(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php', null, 42, true, array( 'edit.php' ), '', 3.14 ),
					),
				),
			)
		);

		$this->assertSame( array( 'edit.php' ), $out['groups'][0]['items'] );
	}

	/**
	 * A deeply nested payload does not exhaust the stack or leak through.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deeply_nested_input_is_survived(): void {
		$deep = array( 'x' );

		for ( $i = 0; $i < 2000; $i++ ) {
			$deep = array( $deep );
		}

		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( $deep, 'edit.php' ),
					),
				),
			)
		);

		$this->assertSame( array( 'edit.php' ), $out['groups'][0]['items'] );
	}

	// ------------------------------------------------------------ Labels.

	/**
	 * Labels are reduced to plain text, since core emits menu titles unescaped.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_labels_are_stripped_to_plain_text(): void {
		$cases = array(
			'<b>Bold</b>'                   => 'Bold',
			'<script>alert(1)</script>Safe' => 'Safe',
			'<style>.a{}</style>Styled'     => 'Styled',
			'<img src=x onerror=alert(1)>'  => '',
			'Content'                       => 'Content',
			"Line\nBreak"                   => 'Line Break',
			"Null\x00Byte"                  => 'NullByte',
			'  Trimmed  '                   => 'Trimmed',
			'Multiple    spaces'            => 'Multiple spaces',
			'Ampersand & more'              => 'Ampersand & more',
			'Arabic نص عربي'                => 'Arabic نص عربي',
		);

		foreach ( $cases as $input => $expected ) {
			$this->assertSame( $expected, Layout_Sanitizer::sanitize_label( $input ), "Input: {$input}" );
		}
	}

	/**
	 * Label length is bounded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_label_length_is_bounded(): void {
		$label = Layout_Sanitizer::sanitize_label( str_repeat( 'a', 500 ) );

		$this->assertSame( Layout_Sanitizer::MAX_LABEL_LENGTH, strlen( $label ) );
	}

	/**
	 * A non-scalar label is rejected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_non_scalar_labels_are_rejected(): void {
		$this->assertSame( '', Layout_Sanitizer::sanitize_label( array( 'x' ) ) );
		$this->assertSame( '', Layout_Sanitizer::sanitize_label( null ) );
	}

	/**
	 * An empty label is omitted rather than stored blank, so the default shows.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_empty_labels_are_omitted(): void {
		$out = $this->clean(
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'label' => '<b></b>',
					),
				),
			)
		);

		$this->assertArrayNotHasKey( 'label', $out['groups'][0] );
	}

	// ------------------------------------------------------------- Icons.

	/**
	 * Only Dashicons are accepted, so a layout cannot point the sidebar at a
	 * remote image and cause an outbound request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_only_dashicons_are_accepted(): void {
		$this->assertSame( 'dashicons-cart', Layout_Sanitizer::sanitize_icon( 'dashicons-cart' ) );
		$this->assertSame( 'dashicons-cart', Layout_Sanitizer::sanitize_icon( '  DASHICONS-CART  ' ) );

		foreach (
			array(
				'https://evil.example/x.png',
				'//evil.example/x.png',
				'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
				'javascript:alert(1)',
				'dashicons-',
				'cart',
				'<script>',
				'',
			) as $bad
		) {
			$this->assertSame( '', Layout_Sanitizer::sanitize_icon( $bad ), "Should reject: {$bad}" );
		}
	}

	/**
	 * A malformed icon is rejected outright rather than stripped to something
	 * harmless but meaningless, so the group falls back to its default.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_malformed_icons_are_rejected_not_mangled(): void {
		foreach (
			array(
				'dashicons-cart"onload="x',
				'dashicons-c a r t',
				'dashicons-cart;',
				'dashicons--cart',
				'dashicons-cart-',
				'dashicons_cart',
			) as $bad
		) {
			$this->assertSame( '', Layout_Sanitizer::sanitize_icon( $bad ), "Should reject: {$bad}" );
		}

		// Multi-segment Dashicons remain valid.
		$this->assertSame( 'dashicons-admin-post', Layout_Sanitizer::sanitize_icon( 'dashicons-admin-post' ) );
		$this->assertSame( 'dashicons-images-alt2', Layout_Sanitizer::sanitize_icon( 'dashicons-images-alt2' ) );
	}

	// ------------------------------------------------------------- Keys.

	/**
	 * Keys are reduced to lowercase safe characters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_key_sanitisation(): void {
		$this->assertSame( 'content', Layout_Sanitizer::sanitize_key( 'Content' ) );
		$this->assertSame( 'my-group_1', Layout_Sanitizer::sanitize_key( 'My-Group_1' ) );
		$this->assertSame( 'contentscript', Layout_Sanitizer::sanitize_key( 'content<script>' ) );
		$this->assertSame( '', Layout_Sanitizer::sanitize_key( '<>!@#' ) );
		$this->assertSame( '', Layout_Sanitizer::sanitize_key( array() ) );
	}

	// ----------------------------------------------------------- Booleans.

	/**
	 * Booleans are read the way a checkbox payload should be.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_boolean_coercion(): void {
		foreach ( array( true, 1, '1', 'true', 'TRUE', 'yes', 'on', 42 ) as $truthy ) {
			$this->assertTrue( Layout_Sanitizer::sanitize_bool( $truthy ), 'Should be true: ' . var_export( $truthy, true ) );
		}

		foreach ( array( false, 0, '0', 'false', 'no', 'off', '', 'anything', array(), null ) as $falsy ) {
			$this->assertFalse( Layout_Sanitizer::sanitize_bool( $falsy ), 'Should be false: ' . var_export( $falsy, true ) );
		}
	}

	// ---------------------------------------------------------- Collapsed.

	/**
	 * Collapsed state accepts only known group IDs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_collapsed_state_is_whitelisted(): void {
		$out = Layout_Sanitizer::sanitize_collapsed(
			array( 'content', 'not-a-group', 'tools', 42, null, array( 'x' ), '', 'content' ),
			self::GROUPS
		);

		$this->assertSame( array( 'content', 'tools' ), $out );
	}

	/**
	 * The catch-all can never be collapsed, so a crafted request cannot hide the
	 * one group guaranteed to hold anything unrecognised. SPEC section 5.1.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_ungrouped_can_never_be_collapsed(): void {
		$out = Layout_Sanitizer::sanitize_collapsed(
			array( 'ungrouped', 'content', 'UNGROUPED' ),
			self::GROUPS
		);

		$this->assertSame( array( 'content' ), $out );
		$this->assertNotContains( 'ungrouped', $out );
	}

	/**
	 * A non-array collapsed payload yields an empty list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_non_array_collapsed_payload_is_empty(): void {
		foreach ( array( null, 'content', 42, false ) as $input ) {
			$this->assertSame( array(), Layout_Sanitizer::sanitize_collapsed( $input, self::GROUPS ) );
		}
	}

	// ------------------------------------------------------- Idempotence.

	/**
	 * Sanitising an already sanitised layout changes nothing.
	 *
	 * A sanitiser that is not idempotent will eventually corrupt data, because
	 * stored values pass through it again on every read.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitisation_is_idempotent(): void {
		$raw = array(
			'groups'          => array(
				array(
					'id'           => 'content',
					'label'        => 'My Content',
					'icon'         => 'dashicons-admin-post',
					'default_open' => '1',
					'items'        => array( 'edit.php', 'upload.php' ),
				),
				array(
					'id'    => 'commerce',
					'items' => array( 'woocommerce' ),
				),
			),
			'ungrouped_label' => 'Everything Else',
		);

		$once  = $this->clean( $raw );
		$twice = $this->clean( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * A well-formed layout survives intact.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_valid_layout_survives(): void {
		$out = $this->clean(
			array(
				'schema'          => 1,
				'groups'          => array(
					array(
						'id'           => 'content',
						'label'        => 'Content',
						'icon'         => 'dashicons-admin-post',
						'default_open' => true,
						'items'        => array( 'edit.php', 'upload.php' ),
					),
				),
				'ungrouped_label' => 'Other',
			)
		);

		$this->assertSame( 'content', $out['groups'][0]['id'] );
		$this->assertSame( 'Content', $out['groups'][0]['label'] );
		$this->assertSame( 'dashicons-admin-post', $out['groups'][0]['icon'] );
		$this->assertTrue( $out['groups'][0]['default_open'] );
		$this->assertSame( array( 'edit.php', 'upload.php' ), $out['groups'][0]['items'] );
		$this->assertSame( 'Other', $out['ungrouped_label'] );
	}
}
