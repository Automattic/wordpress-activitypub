<?php
/**
 * Test file for the comment type polyfill functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Polyfill;

/**
 * Test class for the comment type polyfill functions.
 *
 * Runs on both sides of the function_exists() guard: with the core shim installed the
 * assertions exercise the "core has it" branch, without it the polyfill. Both must agree.
 *
 * @coversDefaultClass \Activitypub\Comment
 */
class Test_Comment_Types extends \WP_UnitTestCase {

	/**
	 * The plugin's types are registered through the core API and read back unchanged.
	 *
	 * @covers ::get_comment_types
	 */
	public function test_plugin_types_round_trip_through_the_core_registry() {
		$types = \get_comment_types( array( 'reaction' => true ), 'objects' );

		$this->assertSame( array( 'repost', 'like', 'quote' ), array_keys( $types ) );

		foreach ( $types as $name => $type ) {
			$object = \get_comment_type_object( $name );

			$this->assertInstanceOf( \WP_Comment_Type::class, $object );
			$this->assertFalse( $object->internal, "$name is a reaction, not an internal type." );
			$this->assertTrue( $object->public, 'A reaction is shown on the page; it is public, just not a comment.' );
			$this->assertSame( $type, $object, 'The filtered list and the direct lookup are the same object.' );
		}
	}

	/**
	 * A type that is not a reaction stays a regular comment.
	 */
	public function test_only_reactions_are_excluded() {
		\Activitypub\register_comment_type(
			'probe',
			array(
				'label'          => 'Probes',
				'activity_types' => array( 'probe' ),
			)
		);

		$object = \get_comment_type_object( 'probe' );

		$this->assertFalse( $object->internal );
		$this->assertTrue( $object->public );
		$this->assertNotContains( 'probe', \wp_get_default_excluded_comment_types(), 'It stays in the comment list and the count.' );
		$this->assertContains( 'like', \wp_get_default_excluded_comment_types(), 'A reaction is excluded.' );

		\unregister_comment_type( 'probe' );
	}

	/**
	 * Internal types feed the default-excluded set that core's consumers will read.
	 */
	public function test_reactions_are_excluded_through_the_core_filter() {
		$excluded = \wp_get_default_excluded_comment_types();

		foreach ( array( 'repost', 'like', 'quote', 'note' ) as $name ) {
			$this->assertContains( $name, $excluded );
		}

		$this->assertNotContains( 'comment', $excluded, 'The alias tokens are stripped.' );
	}

	/**
	 * Core's built-ins cannot be overwritten by a plugin.
	 */
	public function test_built_in_types_cannot_be_re_registered() {
		if ( ! \get_comment_type_object( 'comment' ) ) {
			$this->markTestSkipped( 'Built-in types are only registered when core, or the shim, provides them.' );
		}

		$this->setExpectedIncorrectUsage( 'register_comment_type' );

		$this->assertWPError( \register_comment_type( 'comment', array( 'label' => 'Hijacked' ) ) );
	}

	/**
	 * Which side of the guard is live, so a CI log shows it.
	 */
	public function test_reports_which_branch_is_live() {
		$branch = \defined( 'ACTIVITYPUB_COMMENT_TYPE_CORE_SHIM' ) ? 'core shim' : 'polyfill';

		$this->assertTrue( \function_exists( 'register_comment_type' ), "register_comment_type() is available via the $branch." );
	}
}
