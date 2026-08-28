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
	 * Every hand-applied exclusion stands down once core reads the set itself.
	 *
	 * The signal is `_wp_get_excluded_comment_types_clause()`, private to the core patch and left
	 * undefined by both the polyfill and the shim. Neither branch of the guard exercises the
	 * stand-down, so this test defines the sentinel itself and checks each hook returns its input
	 * untouched. If it ever failed, a hook would be pre-empting core's own query with a copy of it.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_hooks_stand_down_when_core_reads_the_set() {
		$this->assertFalse( \function_exists( '_wp_get_excluded_comment_types_clause' ), 'Only real core defines the sentinel.' );

		// Pretend core landed: define the private helper the patch routes every consumer through.
		eval( 'function _wp_get_excluded_comment_types_clause( $column = "comment_type" ) { return ""; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged

		$this->go_to( \get_permalink( self::factory()->post->create() ) );

		$query             = new \WP_Comment_Query();
		$query->query_vars = $query->query_var_defaults;
		\Activitypub\Comment::comment_query( $query );
		$this->assertEmpty( $query->query_vars['type__not_in'], 'comment_query() adds nothing when core reads the set.' );

		$this->assertSame( array( 'type' => '' ), \array_intersect_key( \Activitypub\Comment::rest_comment_query( array( 'type' => '' ) ), array( 'type' => 1 ) ), 'rest_comment_query() adds no type__not_in.' );
		$this->assertArrayNotHasKey( 'type__not_in', \Activitypub\Comment::rest_comment_query( array( 'type' => '' ) ) );

		$this->assertSame( ' WHERE 1=1', \Activitypub\Comment::comment_feed_where( ' WHERE 1=1' ), 'comment_feed_where() leaves the clause alone.' );

		$this->assertNull( \Activitypub\Comment::pre_wp_update_comment_count_now( null, 0, 1 ), 'The count hook returns null so core counts.' );
	}

	/**
	 * Which side of the guard is live, so a CI log shows it.
	 */
	public function test_reports_which_branch_is_live() {
		$branch = \defined( 'ACTIVITYPUB_COMMENT_TYPE_CORE_SHIM' ) ? 'core shim' : 'polyfill';

		$this->assertTrue( \function_exists( 'register_comment_type' ), "register_comment_type() is available via the $branch." );
	}
}
