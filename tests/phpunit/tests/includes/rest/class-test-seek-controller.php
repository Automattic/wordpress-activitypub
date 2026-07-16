<?php
/**
 * Seek REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Followers;
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
		$this->assertStringContainsString( 'page=2', $location );
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
		$this->assertStringContainsString( 'page=1', $response->get_headers()['Location'] );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/1' );
		$request->set_param( 'per_page', 10 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 307, $response->get_status() );
		$this->assertStringContainsString( 'page=3', $response->get_headers()['Location'] );
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
		$this->assertStringContainsString( 'page=1', $response->get_headers()['Location'] );
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

		$this->assertEquals( 404, $response->get_status() );
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

		$this->assertEquals( 404, $response->get_status() );
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
		$this->assertStringContainsString( 'page=1', $response->get_headers()['Location'] );
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
		// A remote URL never dispatches.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', 'https://remote.example/actors/0/followers' );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );

		// A local REST URL outside the ActivityPub namespace never dispatches.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', \rest_url( 'wp/v2/posts' ) );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
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

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Seeking into a forced-signature route does not bypass its mandatory signature verification.
	 *
	 * FEP-8fcf's /followers/sync forces signatures even when Authorized Fetch is off. A seek defers
	 * signature verification for the internal dispatch, but must leave forced routes verifying, so an
	 * unsigned seek into /followers/sync fails verification and collapses to the uniform 404.
	 *
	 * @covers ::get_item
	 */
	public function test_seek_endpoint_does_not_bypass_forced_signature() {
		$sync_url = \add_query_arg( 'authority', 'https://example.org', get_rest_url_by_path( 'actors/0/followers/sync' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/seek' );
		$request->set_param( 'collection', $sync_url );
		$request->set_param( 'item', 'https://example.org/actor/13' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
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
	 * The seek of an outbox activity respects the visibility rules for unauthenticated requests.
	 *
	 * @covers \Activitypub\Rest\Outbox_Controller::get_item_index
	 */
	public function test_outbox_collection_seek_respects_visibility() {
		$public_id  = 'https://example.org/outbox/public-activity';
		$private_id = 'https://example.org/outbox/private-activity';

		$create = array(
			'post_type'    => Outbox::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => \wp_slash( \wp_json_encode( array( 'type' => 'Create' ) ) ),
			'meta_input'   => array(
				'_activitypub_activity_actor' => 'blog',
				'_activitypub_activity_type'  => 'Create',
			),
		);

		$public_post = self::factory()->post->create( \array_merge( $create, array( 'guid' => $public_id ) ) );

		$follow = $create;
		$follow['meta_input']['_activitypub_activity_type'] = 'Follow';
		self::factory()->post->create( \array_merge( $follow, array( 'guid' => $private_id ) ) );

		// The public activity is seekable anonymously.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $public_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 307, $response->get_status() );

		// The non-public activity type is invisible to anonymous seeks.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'item', $private_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );

		\wp_delete_post( $public_post, true );
	}
}
