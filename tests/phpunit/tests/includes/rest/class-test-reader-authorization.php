<?php
/**
 * Reader REST route authorization test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;

/**
 * Tests that the REST routes WordPress core generates for the reader's post types
 * and taxonomies are not readable by logged-out or unprivileged visitors.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Remote_Actors_Controller
 */
class Test_Reader_Authorization extends \WP_UnitTestCase {

	/**
	 * An actor that follows $user_id.
	 *
	 * @var int
	 */
	protected static $actor_id;

	/**
	 * A cached remote post delivered to $user_id.
	 *
	 * @var int
	 */
	protected static $remote_post_id;

	/**
	 * A user who can use ActivityPub.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Another user who can use ActivityPub, unrelated to the fixtures above.
	 *
	 * @var int
	 */
	protected static $other_user_id;

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Create fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_id       = $factory->user->create( array( 'role' => 'author' ) );
		self::$other_user_id = $factory->user->create( array( 'role' => 'author' ) );

		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
		\get_user_by( 'id', self::$other_user_id )->add_cap( 'activitypub' );

		self::$actor_id = $factory->post->create(
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => \wp_slash(
					\wp_json_encode(
						array(
							'id'                => 'https://example.org/users/bob',
							'type'              => 'Person',
							'preferredUsername' => 'bob',
						)
					)
				),
				'meta_input'   => array( Followers::FOLLOWER_META_KEY => (string) self::$user_id ),
			)
		);

		self::$remote_post_id = $factory->post->create(
			array(
				'post_type'    => Remote_Posts::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => 'Cached remote content.',
				'meta_input'   => array( '_activitypub_user_id' => (string) self::$user_id ),
			)
		);
	}

	/**
	 * Set up the REST server.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		\do_action( 'rest_api_init' );
	}

	/**
	 * Perform a REST request.
	 *
	 * @param string $route  Route to request.
	 * @param array  $params Optional. Query parameters. Default empty array.
	 * @return \WP_REST_Response Response object.
	 */
	protected function request( $route, $params = array() ) {
		$request = new \WP_REST_Request( 'GET', $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return \rest_do_request( $request );
	}

	/**
	 * Routes that must never answer a logged-out visitor.
	 *
	 * @return array[] Data provider.
	 */
	public function data_reader_routes() {
		return array(
			'actor collection'       => array( '/wp/v2/ap_actor', array() ),
			'actor by follower'      => array( '/wp/v2/ap_actor', array( 'follower_of' => 1 ) ),
			'post collection'        => array( '/wp/v2/ap_post', array() ),
			'post by user'           => array( '/wp/v2/ap_post', array( 'user_id' => 1 ) ),
			'tag collection'         => array( '/wp/v2/ap_tag', array() ),
			'object type collection' => array( '/wp/v2/ap_object_type', array() ),
		);
	}

	/**
	 * Logged-out visitors get no reader data at all.
	 *
	 * @covers \Activitypub\Rest\Reader_Permission::get_items_permissions_check
	 * @dataProvider data_reader_routes
	 *
	 * @param string $route  Route to request.
	 * @param array  $params Query parameters.
	 */
	public function test_reader_routes_are_closed_to_logged_out_visitors( $route, $params ) {
		\wp_set_current_user( 0 );

		$response = $this->request( $route, $params );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'activitypub_rest_forbidden', $response->get_data()['code'] );
	}

	/**
	 * A logged-out visitor cannot read a cached post by ID either.
	 *
	 * @covers \Activitypub\Rest\Remote_Posts_Controller::get_item_permissions_check
	 */
	public function test_single_post_is_closed_to_logged_out_visitors() {
		\wp_set_current_user( 0 );

		$response = $this->request( '/wp/v2/ap_post/' . self::$remote_post_id );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * A logged-out visitor cannot read a cached actor by ID either.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_single_actor_is_closed_to_logged_out_visitors() {
		\wp_set_current_user( 0 );

		$response = $this->request( '/wp/v2/ap_actor/' . self::$actor_id );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Users without the ActivityPub capability get nothing.
	 *
	 * @covers \Activitypub\Rest\Reader_Permission::get_items_permissions_check
	 */
	public function test_reader_routes_are_closed_to_users_without_the_capability() {
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->request( '/wp/v2/ap_actor' );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * A user cannot read another user's feed by asking for it.
	 *
	 * @covers \Activitypub\Post_Types::filter_ap_post_by_user
	 */
	public function test_user_id_is_clamped_to_the_current_user() {
		\wp_set_current_user( self::$other_user_id );

		$response = $this->request( '/wp/v2/ap_post', array( 'user_id' => self::$user_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data(), 'Another user\'s feed must not be returned.' );
	}

	/**
	 * A tag filter does not skip the per-user scoping.
	 *
	 * @covers \Activitypub\Post_Types::filter_ap_post_by_user
	 */
	public function test_tag_filter_does_not_bypass_user_scoping() {
		$term = \wp_insert_term( 'regression', 'ap_tag' );
		\wp_set_object_terms( self::$remote_post_id, array( (int) $term['term_id'] ), 'ap_tag' );

		\wp_set_current_user( self::$other_user_id );

		$response = $this->request( '/wp/v2/ap_post', array( 'ap_tag' => array( (int) $term['term_id'] ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data(), 'A tag filter must not return another user\'s feed.' );
	}

	/**
	 * A user cannot read another user's followers by asking for them.
	 *
	 * @covers \Activitypub\Post_Types::filter_ap_actor_query_by_follower
	 */
	public function test_follower_of_is_clamped_to_the_current_user() {
		\wp_set_current_user( self::$other_user_id );

		$response = $this->request( '/wp/v2/ap_actor', array( 'follower_of' => self::$user_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data(), 'Another user\'s followers must not be returned.' );
	}

	/**
	 * Cached replies are not readable through the comments route.
	 *
	 * `ap_post` supports comments and remote replies are stored on it, so
	 * `WP_REST_Comments_Controller` reaches these records through `check_read_permission()`
	 * rather than through this controller's own permission callbacks.
	 *
	 * @covers \Activitypub\Rest\Remote_Posts_Controller::check_read_permission
	 * @dataProvider data_users_without_access_to_the_feed
	 *
	 * @param string $user Property holding the user ID to test with.
	 */
	public function test_cached_replies_are_not_readable_through_the_comments_route( $user ) {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$remote_post_id,
				'comment_content'  => 'Cached remote reply.',
				'comment_approved' => '1',
				'comment_type'     => 'comment',
			)
		);

		\wp_set_current_user( 'none' === $user ? 0 : self::${$user} );

		$response = $this->request( '/wp/v2/comments/' . $comment_id );

		$this->assertNotSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'Cached remote reply.', \wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Data provider for users that must not reach another user's cached replies.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_users_without_access_to_the_feed() {
		return array(
			'logged out' => array( 'none' ),
			'other user' => array( 'other_user_id' ),
		);
	}

	/**
	 * The owner still reads the replies cached in their own feed.
	 *
	 * @covers \Activitypub\Rest\Remote_Posts_Controller::check_read_permission
	 */
	public function test_owner_can_read_cached_replies_through_the_comments_route() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$remote_post_id,
				'comment_content'  => 'Cached remote reply.',
				'comment_approved' => '1',
				'comment_type'     => 'comment',
			)
		);

		\wp_set_current_user( self::$user_id );

		$response = $this->request( '/wp/v2/comments/' . $comment_id );

		$this->assertSame( 200, $response->get_status(), 'The owner has to keep access, or this test proves nothing.' );
	}

	/**
	 * A user cannot read another user's cached post by ID.
	 *
	 * The collection filter never runs for a single item, so this is the only gate on that route.
	 *
	 * @covers \Activitypub\Rest\Remote_Posts_Controller::get_item_permissions_check
	 */
	public function test_unrelated_post_cannot_be_read_by_id() {
		\wp_set_current_user( self::$other_user_id );

		$response = $this->request( '/wp/v2/ap_post/' . self::$remote_post_id );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * The follower list of an actor is not disclosed in its REST response.
	 *
	 * The collection filter decides which actors a user may list. The record it hands back must
	 * not then name the other local users who follow that same actor.
	 *
	 * @covers \Activitypub\Post_Types::register_post_meta
	 */
	public function test_actor_response_does_not_disclose_other_followers() {
		\add_post_meta( self::$actor_id, Followers::FOLLOWER_META_KEY, (string) self::$other_user_id );

		\wp_set_current_user( self::$user_id );

		$response = $this->request( '/wp/v2/ap_actor/' . self::$actor_id );

		$this->assertSame( 200, $response->get_status(), 'The follower has to reach the actor, or this test proves nothing.' );
		$this->assertStringNotContainsString(
			Followers::FOLLOWER_META_KEY,
			\wp_json_encode( $response->get_data() ),
			'The follower list must not be serialized into the response.'
		);

		\delete_post_meta( self::$actor_id, Followers::FOLLOWER_META_KEY, (string) self::$other_user_id );
	}

	/**
	 * An actor whose post is in your feed is readable, even without a follow relationship.
	 *
	 * Boosts and replies cache the author too, and those records carry no follower, following or
	 * pending meta. The reader already shows that actor beside the post.
	 *
	 * @covers \Activitypub\Rest\Remote_Actors_Controller::get_item_permissions_check
	 */
	public function test_actor_of_a_post_in_the_feed_can_be_read() {
		$stranger_id = self::factory()->post->create(
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => \wp_slash(
					\wp_json_encode(
						array(
							'id'   => 'https://example.org/users/eve',
							'type' => 'Person',
						)
					)
				),
			)
		);
		$post_id     = self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'meta_input'  => array(
					'_activitypub_user_id'         => (string) self::$user_id,
					'_activitypub_remote_actor_id' => (string) $stranger_id,
				),
			)
		);

		\wp_set_current_user( self::$user_id );
		$mine = $this->request( '/wp/v2/ap_actor/' . $stranger_id );

		$this->assertSame( 200, $mine->get_status(), 'The feed owner has to reach the author of a post in their feed.' );

		\wp_set_current_user( self::$other_user_id );
		$theirs = $this->request( '/wp/v2/ap_actor/' . $stranger_id );

		$this->assertSame( 403, $theirs->get_status(), 'A user with no such post must still be refused.' );

		\wp_delete_post( $post_id, true );
		\wp_delete_post( $stranger_id, true );
	}

	/**
	 * Hashtags and object types only list terms from the caller's own feed.
	 *
	 * @covers \Activitypub\Post_Types::filter_tag_by_user
	 * @covers \Activitypub\Post_Types::filter_object_type_by_user
	 * @dataProvider data_reader_taxonomies
	 *
	 * @param string $route    Taxonomy route to request.
	 * @param string $taxonomy Taxonomy to attach the term to.
	 */
	public function test_reader_taxonomies_are_scoped_to_the_current_user( $route, $taxonomy ) {
		$term = \wp_insert_term( 'term-' . $taxonomy, $taxonomy );
		\wp_set_object_terms( self::$remote_post_id, array( $term['term_id'] ), $taxonomy );

		\wp_set_current_user( self::$user_id );
		$mine = $this->request( $route );

		$this->assertSame( 200, $mine->get_status() );
		$this->assertSame(
			array( $term['term_id'] ),
			\wp_list_pluck( $mine->get_data(), 'id' ),
			'The feed owner has to see the term, or this test proves nothing.'
		);

		\wp_set_current_user( self::$other_user_id );
		$theirs = $this->request( $route );

		$this->assertSame( 200, $theirs->get_status() );
		$this->assertSame( array(), $theirs->get_data(), 'Another user\'s terms must not be listed.' );

		\wp_delete_term( $term['term_id'], $taxonomy );
	}

	/**
	 * Data provider for the reader taxonomy routes.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_reader_taxonomies() {
		return array(
			'hashtags'     => array( '/wp/v2/ap_tag', 'ap_tag' ),
			'object types' => array( '/wp/v2/ap_object_type', 'ap_object_type' ),
		);
	}

	/**
	 * A user cannot read an unrelated actor by ID.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_unrelated_actor_cannot_be_read_by_id() {
		\wp_set_current_user( self::$other_user_id );

		$response = $this->request( '/wp/v2/ap_actor/' . self::$actor_id );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * The admin screens still get their data.
	 *
	 * @covers \Activitypub\Rest\Reader_Permission::get_items_permissions_check
	 */
	public function test_administrators_can_still_read_reader_data() {
		\wp_set_current_user( self::$admin_id );

		$actors = $this->request( '/wp/v2/ap_actor', array( 'follower_of' => self::$user_id ) );
		$this->assertSame( 200, $actors->get_status() );
		$this->assertCount( 1, $actors->get_data() );

		$posts = $this->request( '/wp/v2/ap_post', array( 'user_id' => self::$user_id ) );
		$this->assertSame( 200, $posts->get_status() );
		$this->assertCount( 1, $posts->get_data() );
	}

	/**
	 * A user still gets their own feed and followers.
	 *
	 * @covers \Activitypub\Rest\Reader_Permission::get_items_permissions_check
	 */
	public function test_users_can_still_read_their_own_data() {
		\wp_set_current_user( self::$user_id );

		$actors = $this->request( '/wp/v2/ap_actor' );
		$this->assertSame( 200, $actors->get_status() );
		$this->assertCount( 1, $actors->get_data() );

		$posts = $this->request( '/wp/v2/ap_post' );
		$this->assertSame( 200, $posts->get_status() );
		$this->assertCount( 1, $posts->get_data() );

		$actor = $this->request( '/wp/v2/ap_actor/' . self::$actor_id );
		$this->assertSame( 200, $actor->get_status() );
	}
}
