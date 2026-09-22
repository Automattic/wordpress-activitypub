<?php
/**
 * Seek REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\get_rest_url_by_path;

/**
 * Tests for the Seek REST API endpoint and the seekItem collection extension.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Seek_Controller
 */
class Test_Seek_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Follower post IDs, in creation order (actor/1 … actor/25).
	 *
	 * @var int[]
	 */
	public static $follower_ids = array();

	/**
	 * Assert that a Location header points at a specific collection page.
	 *
	 * Parses the `page` query argument so the check cannot be satisfied by the `per_page`
	 * substring — `per_page=10` contains the literal text `page=1`.
	 *
	 * @param int    $expected The expected page number.
	 * @param string $location The Location header value.
	 */
	private function assert_location_page( $expected, $location ) {
		$params = array();
		\parse_str( (string) \wp_parse_url( $location, PHP_URL_QUERY ), $params );
		$this->assertArrayHasKey( 'page', $params, 'Location must carry a page argument.' );
		$this->assertSame( (string) $expected, (string) $params['page'] );
	}

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		for ( $i = 1; $i <= 25; $i++ ) {
			self::$follower_ids[] = self::factory()->post->create(
				array(
					'post_type'    => Remote_Actors::POST_TYPE,
					'guid'         => 'https://example.org/actor/' . $i,
					'post_content' => \wp_slash(
						\wp_json_encode(
							array(
								'id'                => 'https://example.org/actor/' . $i,
								'type'              => 'Person',
								'preferredUsername' => 'user' . $i,
							)
						)
					),
					'meta_input'   => array(
						Followers::FOLLOWER_META_KEY => '0',
					),
				)
			);
		}
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		\delete_option( 'activitypub_actor_mode' );

		// The actors above are created before the per-test transaction, so only this clears them.
		parent::tear_down_after_class();
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek', $routes );
	}

	/**
	 * A seek on the collection itself redirects to the page containing the item.
	 *
	 * Followers are ordered by post ID descending, so the last-created follower (actor/25) is
	 * the first item. With 10 items per page, actor/13 has twelve followers before it and lives
	 * on page two.
	 *
	 * @covers \Activitypub\Rest\Followers_Controller::get_item_index
	 */
	public function test_followers_collection_item_param_redirects_to_page() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/13' );
		$request->set_param( 'per_page', 10 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );

		$location = $response->get_headers()['Location'];
		$this->assert_location_page( 2, $location );
		$this->assertStringContainsString( 'per_page=10', $location );
		$this->assertStringNotContainsString( 'item=', $location );
	}

	/**
	 * The first item of the collection resolves to page one, the last one to the last page.
	 *
	 * @covers \Activitypub\Rest\Followers_Controller::get_item_index
	 */
	public function test_followers_collection_seek_page_boundaries() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/25' );
		$request->set_param( 'per_page', 10 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 307, $response->get_status() );
		$this->assert_location_page( 1, $response->get_headers()['Location'] );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/1' );
		$request->set_param( 'per_page', 10 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 307, $response->get_status() );
		$this->assert_location_page( 3, $response->get_headers()['Location'] );
	}

	/**
	 * Ascending order inverts the index math.
	 *
	 * @covers \Activitypub\Rest\Followers_Controller::get_item_index
	 */
	public function test_followers_collection_seek_respects_order() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/1' );
		$request->set_param( 'per_page', 10 );
		$request->set_param( 'order', 'asc' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 307, $response->get_status() );
		$this->assert_location_page( 1, $response->get_headers()['Location'] );
	}

	/**
	 * An unknown item produces a 404.
	 *
	 * @covers \Activitypub\Rest\Followers_Controller::get_item_index
	 */
	public function test_followers_collection_seek_unknown_item() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/does-not-exist' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'activitypub_item_not_found', $response, 404 );
	}

	/**
	 * A hidden social graph produces the same 404 as an unknown item.
	 *
	 * @covers \Activitypub\Rest\Followers_Controller::get_item_index
	 */
	public function test_followers_collection_seek_hidden_social_graph() {
		\update_option( 'activitypub_hide_social_graph', '1' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		\delete_option( 'activitypub_hide_social_graph' );

		$this->assertErrorResponse( 'activitypub_item_not_found', $response, 404 );
	}

	/**
	 * A collection that refuses every seek does not advertise one.
	 *
	 * @covers \Activitypub\Rest\Collection::prepare_collection_response
	 */
	public function test_collection_does_not_advertise_unusable_seek() {
		\update_option( 'activitypub_hide_social_graph', '1' );

		$response = rest_get_server()->dispatch( new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' ) )->get_data();

		\delete_option( 'activitypub_hide_social_graph' );

		$this->assertArrayNotHasKey( 'seekItem', $response, 'A hidden social graph must not advertise seekItem.' );
	}

	/**
	 * The collection advertises the seek endpoint with the seekItem property and context.
	 *
	 * @covers \Activitypub\Rest\Collection::prepare_collection_response
	 */
	public function test_collection_advertises_seek_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'seekItem', $response );

		\parse_str( (string) \wp_parse_url( $response['seekItem'], PHP_URL_QUERY ), $params );
		$this->assertStringContainsString( 'activitypub/1.0/seek', \rawurldecode( $response['seekItem'] ) );
		$this->assertSame( get_rest_url_by_path( 'actors/0/followers' ), \rawurldecode( $params['collection'] ) );
		$this->assertContains( 'https://purl.archive.org/socialweb/seekitem/1.0', $response['@context'] );
	}

	/**
	 * A collection with nothing to seek must not advertise the endpoint.
	 *
	 * @covers \Activitypub\Rest\Collection::prepare_collection_response
	 */
	public function test_empty_collection_does_not_advertise_seek() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$response = rest_get_server()->dispatch( new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/followers' ) )->get_data();

		$this->assertSame( 0, $response['totalItems'], 'This actor is expected to have no followers.' );
		$this->assertArrayNotHasKey( 'seekItem', $response, 'A collection with no items has nothing to seek.' );
	}

	/**
	 * The advertised collection must be the id the response itself carries, so a seek runs against
	 * the collection the client is actually reading.
	 *
	 * @covers \Activitypub\Rest\Collection::prepare_collection_response
	 */
	public function test_advertised_collection_matches_the_response_id() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'per_page', 5 );
		$request->set_param( 'order', 'asc' );

		$response = rest_get_server()->dispatch( $request )->get_data();

		// parse_str() decodes the value, so this is the collection URL the seek endpoint will receive.
		$params = array();
		\parse_str( (string) \wp_parse_url( $response['seekItem'], PHP_URL_QUERY ), $params );

		$this->assertSame( $response['id'], $params['collection'] );
	}

	/**
	 * The seek endpoint declares its collection and item arguments.
	 *
	 * @covers ::register_routes
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$args = $response['endpoints'][0]['args'];

		$this->assertArrayHasKey( 'collection', $args );
		$this->assertTrue( $args['collection']['required'] );
		$this->assertArrayHasKey( 'item', $args );
		$this->assertTrue( $args['item']['required'] );
	}

	/**
	 * The seek endpoint dispatches to the collection and passes the redirect through.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', get_rest_url_by_path( 'actors/0/followers' ) );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
		// No per_page is set, so the collection default applies and actor/13 (index 12) is on page one.
		$this->assert_location_page( 1, $response->get_headers()['Location'] );
	}

	/**
	 * The advertised seekItem URL round-trips: the collection parameter it carries resolves back
	 * to the collection.
	 *
	 * @covers ::get_item
	 */
	public function test_seek_endpoint_advertised_url_round_trips() {
		$collection_request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$collection_response = rest_get_server()->dispatch( $collection_request )->get_data();

		$query = \wp_parse_url( $collection_response['seekItem'], PHP_URL_QUERY );
		\parse_str( (string) $query, $params );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', $params['collection'] );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
	}

	/**
	 * Foreign and non-ActivityPub collection URLs produce a 404.
	 *
	 * @covers ::get_item
	 */
	public function test_seek_endpoint_rejects_unknown_collections() {
		$collections = array(
			'a remote URL'                   => 'https://remote.example/actors/0/followers',
			// from_url() reads `?rest_route=` without looking at the host, so the host is checked separately.
			'a remote URL with ?rest_route=' => 'https://remote.example/?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers',
			'a route outside the namespace'  => \rest_url( 'wp/v2/posts' ),
		);

		foreach ( $collections as $description => $collection ) {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
			$request->set_param( 'collection', $collection );
			$request->set_param( 'item', 'https://example.org/actor/13' );

			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 404, $response->get_status(), "Seeking $description must not dispatch." );
			$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'], "Seeking $description must return the uniform 404." );
		}
	}

	/**
	 * A seek pointed at the seek endpoint itself produces a 404, whatever the letter case.
	 *
	 * REST routes are matched case-insensitively, so a collection of `…/Seek` resolves back to this
	 * endpoint. Dispatching it would let a single request drive an arbitrarily deep chain of nested
	 * seeks, each one re-running the route matching for the whole site.
	 *
	 * @covers ::get_item
	 */
	public function test_seek_endpoint_rejects_seeking_itself() {
		foreach ( array( 'seek', 'Seek', 'SEEK' ) as $variant ) {
			$nested = \add_query_arg(
				'collection',
				\rawurlencode( get_rest_url_by_path( 'actors/0/followers' ) ),
				get_rest_url_by_path( $variant )
			);

			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
			$request->set_param( 'collection', $nested );
			$request->set_param( 'item', 'https://example.org/actor/13' );

			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 404, $response->get_status(), "A seek pointed at /{$variant} must not dispatch to the seek endpoint." );
			$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'], "A seek pointed at /{$variant} must return the uniform 404." );
		}
	}

	/**
	 * A collection without seek support produces a 404 instead of the collection body.
	 *
	 * @covers ::get_item
	 */
	public function test_seek_endpoint_collection_without_seek_support() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', get_rest_url_by_path( 'collections/moderators' ) );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'activitypub_item_not_found', $response, 404 );
	}

	/**
	 * Seeking into a forced-signature route does not bypass its mandatory signature verification.
	 *
	 * FEP-8fcf's /followers/sync is not a seekable collection: it does not declare an `item`
	 * argument, so the seek endpoint refuses to dispatch to it and returns the uniform 404. The
	 * forced-signature route is therefore never reached through a seek at all — a stronger guarantee
	 * than relying on its own 401 — and no membership is disclosed.
	 *
	 * @covers ::get_item
	 */
	public function test_seek_endpoint_does_not_bypass_forced_signature() {
		$sync_url = \add_query_arg( 'authority', 'https://example.org', get_rest_url_by_path( 'actors/0/followers/sync' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', $sync_url );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'activitypub_item_not_found', $response, 404 );
	}

	/**
	 * A global signature-defer filter cannot reopen a forced-signature route through a seek.
	 *
	 * Even with a broad defer filter installed (as a local-dev setup might), /followers/sync is not
	 * a seekable collection, so the seek endpoint rejects it with 404 before any dispatch — the
	 * forced route is never reached and the defer filter never runs against it.
	 *
	 * @covers ::get_item
	 */
	public function test_global_defer_filter_does_not_reopen_forced_signature() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true', 20 );

		$sync_url = \add_query_arg( 'authority', 'https://example.org', get_rest_url_by_path( 'actors/0/followers/sync' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', $sync_url );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true', 20 );

		$this->assertErrorResponse( 'activitypub_item_not_found', $response, 404 );
	}

	/**
	 * A HEAD seek is answered like the GET of the same URL.
	 *
	 * The HEAD short-circuit in verify_signature() lets caches probe public endpoints unsigned, but a
	 * seek answers with a Location that a bare probe does not, so it takes the read path instead:
	 * challenged under Authorized Fetch, and served anonymously without it, exactly as a GET is.
	 *
	 * @covers \Activitypub\Rest\Followers_Controller::verify_signature
	 */
	public function test_head_seek_follows_the_read_rules() {
		\update_option( 'activitypub_authorized_fetch', '1' );

		$request = new \WP_REST_Request( 'HEAD', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		\delete_option( 'activitypub_authorized_fetch' );

		$this->assertEquals( 401, $response->get_status(), 'A HEAD seek must be challenged under Authorized Fetch.' );

		// Without it, the GET of the same URL is public, so the HEAD must resolve to the same page.
		$head = rest_get_server()->dispatch( $request );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$get = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $head->get_status() );
		$this->assertSame( $get->get_headers()['Location'], $head->get_headers()['Location'] );
	}

	/**
	 * The advertised seekItem preserves the collection's filtering arguments.
	 *
	 * @covers \Activitypub\Rest\Collection::prepare_collection_response
	 */
	public function test_seek_item_preserves_query_arguments() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_query_params(
			array(
				'order'    => 'asc',
				'per_page' => '5',
			)
		);

		$response = rest_get_server()->dispatch( $request )->get_data();

		\parse_str( (string) \wp_parse_url( $response['seekItem'], PHP_URL_QUERY ), $params );
		\parse_str( (string) \wp_parse_url( \rawurldecode( $params['collection'] ), PHP_URL_QUERY ), $collection_params );

		$this->assertSame( 'asc', $collection_params['order'] );
		$this->assertSame( '5', $collection_params['per_page'] );
		$this->assertArrayNotHasKey( 'page', $collection_params );
	}

	/**
	 * An unregistered query argument must not be copied into the seek redirect or the collection id.
	 *
	 * `rest_route` is a public query var, so a link carrying one resolves to that route instead of to
	 * the collection page.
	 *
	 * @covers \Activitypub\Rest\Collection::maybe_seek_item
	 * @covers \Activitypub\Rest\Collection::prepare_collection_response
	 */
	public function test_unregistered_query_args_are_not_carried_over() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/13' );
		$request->set_param( 'per_page', 5 );
		$request->set_param( 'rest_route', '/wp/v2/users' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );

		/*
		 * On plain permalinks the collection URL is itself a `?rest_route=` link, so a carried-over
		 * value would not be appended but replace the route the redirect points at.
		 */
		$location = $response->get_headers()['Location'];
		$this->assertStringNotContainsString( 'wp%2Fv2%2Fusers', $location );
		$this->assertStringNotContainsString( 'wp/v2/users', $location );
		$this->assertStringContainsString( 'per_page=5', $location );

		// The collection id and its page links are built from the same arguments.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'rest_route', '/wp/v2/users' );

		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertStringNotContainsString( 'wp%2Fv2%2Fusers', $response['id'] );
		$this->assertStringNotContainsString( 'wp%2Fv2%2Fusers', $response['first'] );
	}

	/**
	 * Create a public and a private-visibility outbox activity.
	 *
	 * @param string $public_id The ActivityPub ID of the public activity.
	 * @param string $hidden_id The ActivityPub ID of the private-visibility activity.
	 * @param int    $user_id   Optional. Author of the activities. Default 0, the blog actor.
	 * @param string $date      Optional. Date of the public activity; the private one is a second newer.
	 */
	private function create_outbox_pair( $public_id, $hidden_id, $user_id = 0, $date = '2026-01-01 00:00:00' ) {
		$create = array(
			'post_type'    => Outbox::POST_TYPE,
			'post_status'  => 'publish',
			'post_author'  => $user_id,
			'post_date'    => $date,
			'post_content' => \wp_slash( \wp_json_encode( array( 'type' => 'Create' ) ) ),
			'meta_input'   => array(
				'_activitypub_activity_actor' => $user_id > 0 ? 'user' : 'blog',
				'_activitypub_activity_type'  => 'Create',
			),
		);

		self::factory()->post->create( \array_merge( $create, array( 'guid' => $public_id ) ) );

		$create['post_date']                                    = \gmdate( 'Y-m-d H:i:s', \strtotime( $date ) + 1 );
		$create['meta_input']['activitypub_content_visibility'] = ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
		self::factory()->post->create( \array_merge( $create, array( 'guid' => $hidden_id ) ) );
	}

	/**
	 * An activity whose type is not part of the public outbox is invisible to a non-owner, so it must
	 * answer like a missing one even though it exists.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_seek_hides_non_public_activity_types() {
		$item = 'https://example.org/outbox/a-follow';

		self::factory()->post->create(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'publish',
				'guid'         => $item,
				'post_content' => \wp_slash( \wp_json_encode( array( 'type' => 'Follow' ) ) ),
				'meta_input'   => array(
					'_activitypub_activity_actor' => 'blog',
					'_activitypub_activity_type'  => 'Follow',
				),
			)
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $item );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status(), 'A Follow is not part of the public outbox and must not be seekable.' );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * Every visibility that keeps an activity out of the public outbox must answer like a missing item.
	 *
	 * @dataProvider data_non_public_visibility
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 *
	 * @param string $visibility The content visibility to store.
	 */
	public function test_outbox_seek_hides_non_public_visibility( $visibility ) {
		$item = 'https://example.org/outbox/visibility-' . $visibility;

		self::factory()->post->create(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'publish',
				'guid'         => $item,
				'post_content' => \wp_slash( \wp_json_encode( array( 'type' => 'Create' ) ) ),
				'meta_input'   => array(
					'_activitypub_activity_actor'    => 'blog',
					'_activitypub_activity_type'     => 'Create',
					'activitypub_content_visibility' => $visibility,
				),
			)
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $item );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status(), "A $visibility activity must not be seekable by a non-owner." );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * Data provider for the visibilities that are not part of the public outbox.
	 *
	 * @return array[]
	 */
	public function data_non_public_visibility() {
		return array(
			'private'      => array( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ),
			'quiet public' => array( ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC ),
			'local'        => array( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ),
		);
	}

	/**
	 * An activity that belongs to another actor must not be seekable through this actor's outbox,
	 * even for a logged-in user and even when the activity itself is public.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_seek_does_not_cross_actors() {
		$alice = self::factory()->user->create( array( 'role' => 'author' ) );
		$bob   = self::factory()->user->create( array( 'role' => 'author' ) );
		$item  = 'https://example.org/outbox/alice-activity';

		$this->create_outbox_pair( $item, 'https://example.org/outbox/alice-hidden', $alice );

		// Bob is authenticated, and the activity is public, but it is not in his outbox.
		\wp_set_current_user( $bob );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $bob . '/outbox' );
		$request->set_param( 'item', $item );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status(), "Another actor's activity must not be seekable here." );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );

		// The blog outbox must not hold it either.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $item );

		$this->assertEquals( 404, rest_get_server()->dispatch( $request )->get_status(), "A user's activity must not be seekable through the blog outbox." );
	}

	/**
	 * A query filter that restricts the collection with `post__in` also restricts what can be sought,
	 * or a seek would resolve an activity the collection itself excludes.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_seek_honours_a_post_in_filter() {
		$wanted   = 'https://example.org/outbox/filtered-in';
		$excluded = 'https://example.org/outbox/filtered-out';

		$this->create_outbox_pair( $wanted, 'https://example.org/outbox/filtered-hidden' );
		$this->create_outbox_pair( $excluded, 'https://example.org/outbox/filtered-hidden-2', 0, '2026-01-01 00:00:10' );

		// Restrict the collection to the wanted activity, the way a site narrowing its outbox would.
		$restrict = static function ( $args ) use ( $wanted ) {
			$args['post__in'] = array( \url_to_postid( $wanted ) ?: \Activitypub\Collection\Outbox::get_by_guid( $wanted )->ID );

			return $args;
		};
		\add_filter( 'activitypub_rest_outbox_query', $restrict );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $wanted );

		$included = rest_get_server()->dispatch( $request );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $excluded );

		$refused = rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_rest_outbox_query', $restrict );

		$this->assertEquals( 307, $included->get_status(), 'An activity the filter keeps must still be seekable.' );
		$this->assertEquals( 404, $refused->get_status(), 'An activity the filter excludes must not be seekable.' );
		$this->assertEquals( 'activitypub_item_not_found', $refused->get_data()['code'] );
	}

	/**
	 * A query filter that restricts the inbox with `post__in` also restricts what can be sought.
	 *
	 * @covers \Activitypub\Rest\Actors_Inbox_Controller::get_item_index
	 */
	public function test_inbox_seek_honours_a_post_in_filter() {
		$user_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$wanted   = 'https://example.com/activity/kept';
		$excluded = 'https://example.com/activity/dropped';
		$ids      = array();

		foreach ( array( $wanted, $excluded ) as $offset => $activity ) {
			$ids[ $activity ] = self::factory()->post->create(
				array(
					'post_type'   => Inbox::POST_TYPE,
					'post_status' => 'publish',
					'guid'        => $activity,
					'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '2026-01-01 00:00:00' ) + $offset ),
					'meta_input'  => array( '_activitypub_user_id' => (string) $user_id ),
				)
			);
		}

		$restrict = static function ( $args ) use ( $ids, $wanted ) {
			$args['post__in'] = array( $ids[ $wanted ] );

			return $args;
		};

		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
		\add_filter( 'activitypub_rest_inbox_query', $restrict );
		\wp_set_current_user( $user_id );

		$route = '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/inbox';

		$request = new \WP_REST_Request( 'GET', $route );
		$request->set_param( 'item', $wanted );

		$included = rest_get_server()->dispatch( $request );

		$request = new \WP_REST_Request( 'GET', $route );
		$request->set_param( 'item', $excluded );

		$refused = rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_rest_inbox_query', $restrict );
		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );

		$this->assertEquals( 307, $included->get_status(), 'An activity the filter keeps must still be seekable.' );
		$this->assertEquals( 404, $refused->get_status(), 'An activity the filter excludes must not be seekable.' );
		$this->assertEquals( 'activitypub_item_not_found', $refused->get_data()['code'] );
	}

	/**
	 * A public activity in the outbox is seekable without credentials, because the same request can
	 * already page through it. A private-visibility activity and one that does not exist answer with
	 * the identical 404, so neither can be told from the other.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_seek_unauthenticated_resolves_public_and_hides_the_rest() {
		$public_id = 'https://example.org/outbox/public-activity';
		$hidden_id = 'https://example.org/outbox/hidden-activity';

		$this->create_outbox_pair( $public_id, $hidden_id );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $public_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status(), 'A public activity must be seekable without credentials.' );
		$this->assert_location_page( 1, $response->get_headers()['Location'] );

		$refusals = array();
		foreach ( array( $hidden_id, 'https://example.org/outbox/does-not-exist' ) as $item ) {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
			$request->set_param( 'item', $item );

			$response   = rest_get_server()->dispatch( $request );
			$refusals[] = array( $response->get_status(), $response->get_data() );
		}

		// A hidden activity and a missing one must be answered identically, down to the message.
		$this->assertSame( 404, $refusals[0][0] );
		$this->assertSame( $refusals[0], $refusals[1], 'A hidden activity must be indistinguishable from one that does not exist.' );
	}

	/**
	 * The page a non-owner is sent to counts only the activities they can see, so it cannot reveal
	 * that a private activity sits between them.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_seek_index_does_not_count_hidden_activities() {
		$newest_id = 'https://example.org/outbox/index-newest';
		$hidden_id = 'https://example.org/outbox/index-hidden';
		$oldest_id = 'https://example.org/outbox/index-oldest';
		$user_id   = self::factory()->user->create( array( 'role' => 'author' ) );

		// Oldest first, so the newest-first collection holds: newest, hidden, oldest.
		$this->create_outbox_pair( $oldest_id, $hidden_id, $user_id, '2026-01-01 00:00:00' );
		$this->create_outbox_pair( $newest_id, 'https://example.org/outbox/index-hidden-2', $user_id, '2026-01-01 00:00:10' );

		$route = '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox';

		$request = new \WP_REST_Request( 'GET', $route );
		$request->set_param( 'item', $oldest_id );
		$request->set_param( 'per_page', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
		// Only the newest public activity precedes it, so page 2 — not page 3, which would expose the hidden one.
		$this->assert_location_page( 2, $response->get_headers()['Location'] );

		// The owner sees the hidden activities too, so for them the same item sits one page further.
		\wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( 'GET', $route );
		$request->set_param( 'item', $oldest_id );
		$request->set_param( 'per_page', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
		$this->assert_location_page( 4, $response->get_headers()['Location'] );
	}

	/**
	 * The outbox owner can seek their own activities, including a private-visibility one, confirming
	 * the non-owner refusal is an access gate and not a broken lookup.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_owner_can_seek_own_activities() {
		$public_id = 'https://example.org/outbox/owner-public-activity';
		$hidden_id = 'https://example.org/outbox/owner-hidden-activity';
		$user_id   = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->create_outbox_pair( $public_id, $hidden_id, $user_id );

		\wp_set_current_user( $user_id );

		foreach ( array( $public_id, $hidden_id ) as $item ) {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );
			$request->set_param( 'item', $item );

			$response = rest_get_server()->dispatch( $request );
			$this->assertEquals( 307, $response->get_status(), "Owner seek of {$item} must resolve to a page." );
		}

		// The owner still gets a 404 for an activity that is genuinely not in their outbox.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );
		$request->set_param( 'item', 'https://example.org/outbox/never-existed' );

		$response = rest_get_server()->dispatch( $request );

		\wp_set_current_user( 0 );

		$this->assertErrorResponse( 'activitypub_item_not_found', $response, 404 );
	}

	/**
	 * Seeking the following collection resolves the page holding a followed actor.
	 *
	 * @covers \Activitypub\Rest\Following_Controller::get_item_index
	 */
	public function test_following_collection_seek_resolves_page() {
		$actors = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$uri      = 'https://example.com/followed/' . $i;
			$actors[] = $uri;

			self::factory()->post->create(
				array(
					'post_type'    => Remote_Actors::POST_TYPE,
					'guid'         => $uri,
					'post_content' => \wp_slash(
						\wp_json_encode(
							array(
								'id'   => $uri,
								'type' => 'Person',
							)
						)
					),
					'meta_input'   => array( Following::FOLLOWING_META_KEY => '0' ),
				)
			);
		}

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'item', $actors[0] );
		$request->set_param( 'per_page', 2 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
		// Newest first by ID, so the actor created first is the last of five: index 4, page 3.
		$this->assert_location_page( 3, $response->get_headers()['Location'] );

		// An actor this collection does not follow answers like any missing item.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'item', 'https://example.com/followed/does-not-exist' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * A follower is not a followed actor, so seeking one through the following collection must answer
	 * like a missing item even though the actor exists.
	 *
	 * @covers \Activitypub\Rest\Following_Controller::get_item_index
	 */
	public function test_following_collection_seek_does_not_find_followers() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status(), 'A follower must not be seekable through the following collection.' );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * Seeking the liked collection resolves the page holding a liked object.
	 *
	 * @covers \Activitypub\Rest\Liked_Controller::get_item_index
	 */
	public function test_liked_collection_seek_resolves_page() {
		$objects = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$object    = 'https://example.com/liked/' . $i;
			$objects[] = $object;

			$this->create_like( 'https://example.org/outbox/like-' . $i, $object, 'Like', $i );
		}

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/liked' );
		$request->set_param( 'item', $objects[0] );
		$request->set_param( 'per_page', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
		// Newest first, so the object liked first is third: index 2, page 3.
		$this->assert_location_page( 3, $response->get_headers()['Location'] );

		// An object that was never liked answers like any missing item.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/liked' );
		$request->set_param( 'item', 'https://example.com/liked/never' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * An undone like leaves the collection, so it must stop being seekable.
	 *
	 * @covers \Activitypub\Rest\Liked_Controller::get_item_index
	 */
	public function test_liked_collection_seek_skips_undone_likes() {
		$object = 'https://example.com/liked/undone';

		$this->create_like( 'https://example.org/outbox/undone-like', $object, 'Like', 1 );
		$this->create_like( 'https://example.org/outbox/undone-undo', $object, 'Undo', 2 );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/liked' );
		$request->set_param( 'item', $object );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status(), 'An undone like must not be seekable.' );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * Create a Like or Undo activity in the blog outbox.
	 *
	 * @param string $guid      The activity ID.
	 * @param string $object_id The ActivityPub ID of the liked object.
	 * @param string $type      The activity type, `Like` or `Undo`.
	 * @param int    $offset    Seconds to add to the fixture date, so the order is deterministic.
	 */
	private function create_like( $guid, $object_id, $type, $offset ) {
		self::factory()->post->create(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'publish',
				'guid'         => $guid,
				'post_date'    => \gmdate( 'Y-m-d H:i:s', \strtotime( '2026-01-01 00:00:00' ) + $offset ),
				'post_content' => \wp_slash( \wp_json_encode( array( 'type' => $type ) ) ),
				'meta_input'   => array(
					'_activitypub_activity_actor' => 'blog',
					'_activitypub_activity_type'  => $type,
					'_activitypub_object_id'      => $object_id,
				),
			)
		);
	}

	/**
	 * The inbox owner can seek their own inbox, and an activity delivered to another actor's inbox
	 * answers like a missing one.
	 *
	 * @covers \Activitypub\Rest\Actors_Inbox_Controller::get_item_index
	 */
	public function test_inbox_seek_resolves_page_for_the_owner() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$other   = self::factory()->user->create( array( 'role' => 'author' ) );

		$activities = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$activity     = 'https://example.com/activity/' . $i;
			$activities[] = $activity;

			self::factory()->post->create(
				array(
					'post_type'   => Inbox::POST_TYPE,
					'post_status' => 'publish',
					'guid'        => $activity,
					'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '2026-01-01 00:00:00' ) + $i ),
					'meta_input'  => array( '_activitypub_user_id' => (string) $user_id ),
				)
			);
		}

		/*
		 * The inbox route is OAuth-only, so stand in for a bearer token; the owner check that follows
		 * it still runs against the current user.
		 */
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
		\wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/inbox' );
		$request->set_param( 'item', $activities[0] );
		$request->set_param( 'per_page', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 307, $response->get_status() );
		// Newest first, so the activity delivered first is third: index 2, page 3.
		$this->assert_location_page( 3, $response->get_headers()['Location'] );

		// The same activity is not in another actor's inbox.
		\wp_set_current_user( $other );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $other . '/inbox' );
		$request->set_param( 'item', $activities[0] );

		$response = rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );

		$this->assertEquals( 404, $response->get_status(), "An activity in another actor's inbox must not be seekable." );
		$this->assertEquals( 'activitypub_item_not_found', $response->get_data()['code'] );
	}

	/**
	 * An anonymous seek into the owner-only inbox returns 401, not a misleading 404: the failure is a
	 * property of the request, not of any item, so surfacing it discloses no membership.
	 *
	 * @covers ::get_item
	 * @covers \Activitypub\Rest\Collection::maybe_seek_item
	 */
	public function test_inbox_seek_requires_authentication() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/inbox' );
		$request->set_param( 'item', 'https://example.org/activity/1' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'activitypub_oauth_required', $response->get_data()['code'] );
	}
}
