<?php
/**
 * Test Collection Trait.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Collection;

/**
 * Test Collection Trait.
 *
 * @group rest
 * @coversDefaultClass Collection
 */
class Test_Trait_Collection extends \WP_UnitTestCase {

	/**
	 * Test class instance.
	 *
	 * @var object
	 */
	protected $instance;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		// Create a test class that uses the trait.
		$this->instance = new class() {
			use Collection;
		};
	}

	/**
	 * Test adding collection links when there's only one page.
	 *
	 * @covers ::prepare_collection_response
	 */
	public function test_prepare_collection_response_single_page() {
		$request = new \WP_REST_Request();
		$request->set_param( 'per_page', 10 );

		$response = array(
			'type'       => 'Collection',
			'id'         => 'https://example.org/collection',
			'totalItems' => 5,
			'items'      => array( 'item1', 'item2', 'item3', 'item4', 'item5' ),
		);

		$result = $this->instance->prepare_collection_response( $response, $request );

		$this->assertEquals( $response, $result );
		$this->assertArrayNotHasKey( 'first', $result );
		$this->assertArrayNotHasKey( 'last', $result );
		$this->assertArrayNotHasKey( 'next', $result );
		$this->assertArrayNotHasKey( 'prev', $result );
	}

	/**
	 * Test adding collection links for a Collection (not a page).
	 *
	 * @covers ::prepare_collection_response
	 */
	public function test_prepare_collection_response_collection() {
		$request = new \WP_REST_Request();
		$request->set_param( 'per_page', 10 );

		$response = array(
			'type'       => 'Collection',
			'id'         => 'https://example.org/collection',
			'totalItems' => 25,
			'items'      => array( 'item1', 'item2', 'item3' ),
		);

		$result = $this->instance->prepare_collection_response( $response, $request );

		$this->assertEquals( 'Collection', $result['type'] );
		$this->assertEquals( 'https://example.org/collection?page=1', $result['first'] );
		$this->assertEquals( 'https://example.org/collection?page=3', $result['last'] );
		$this->assertArrayNotHasKey( 'items', $result );
		$this->assertArrayNotHasKey( 'orderedItems', $result );
	}

	/**
	 * Test adding collection links for a CollectionPage.
	 *
	 * @covers ::prepare_collection_response
	 */
	public function test_prepare_collection_response_collection_page() {
		$request = new \WP_REST_Request();
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 10 );

		$response = array(
			'type'       => 'Collection',
			'id'         => 'https://example.org/collection',
			'totalItems' => 25,
			'items'      => array( 'item11', 'item12', 'item13' ),
		);

		$result = $this->instance->prepare_collection_response( $response, $request );

		$this->assertEquals( 'CollectionPage', $result['type'] );
		$this->assertEquals( 'https://example.org/collection', $result['partOf'] );
		$this->assertEquals( 'https://example.org/collection?page=2', $result['id'] );
		$this->assertEquals( 'https://example.org/collection?page=1', $result['first'] );
		$this->assertEquals( 'https://example.org/collection?page=3', $result['last'] );
		$this->assertEquals( 'https://example.org/collection?page=3', $result['next'] );
		$this->assertEquals( 'https://example.org/collection?page=1', $result['prev'] );
	}

	/**
	 * Test adding collection links for the first page.
	 *
	 * @covers ::prepare_collection_response
	 */
	public function test_prepare_collection_response_first_page() {
		$request = new \WP_REST_Request();
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10 );

		$response = array(
			'type'       => 'OrderedCollection',
			'id'         => 'https://example.org/collection',
			'totalItems' => 25,
			'items'      => array( 'item1', 'item2', 'item3' ),
		);

		$result = $this->instance->prepare_collection_response( $response, $request );

		$this->assertEquals( 'OrderedCollectionPage', $result['type'] );
		$this->assertEquals( 'https://example.org/collection?page=1', $result['id'] );
		$this->assertEquals( 'https://example.org/collection?page=2', $result['next'] );
		$this->assertArrayNotHasKey( 'prev', $result );
	}

	/**
	 * Test adding collection links for the last page.
	 *
	 * @covers ::prepare_collection_response
	 */
	public function test_prepare_collection_response_last_page() {
		$request = new \WP_REST_Request();
		$request->set_param( 'page', 3 );
		$request->set_param( 'per_page', 10 );

		$response = array(
			'type'       => 'Collection',
			'id'         => 'https://example.org/collection',
			'totalItems' => 25,
			'items'      => array( 'item21', 'item22', 'item23', 'item24', 'item25' ),
		);

		$result = $this->instance->prepare_collection_response( $response, $request );

		$this->assertEquals( 'CollectionPage', $result['type'] );
		$this->assertEquals( 'https://example.org/collection?page=3', $result['id'] );
		$this->assertEquals( 'https://example.org/collection?page=2', $result['prev'] );
		$this->assertArrayNotHasKey( 'next', $result );
	}

	/**
	 * Test invalid page number.
	 *
	 * @covers ::prepare_collection_response
	 */
	public function test_prepare_collection_response_invalid_page() {
		$request = new \WP_REST_Request();
		$request->set_param( 'page', 5 );
		$request->set_param( 'per_page', 10 );

		$response = array(
			'type'       => 'Collection',
			'id'         => 'https://example.org/collection',
			'totalItems' => 25,
			'items'      => array(),
		);

		$result = $this->instance->prepare_collection_response( $response, $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'rest_post_invalid_page_number', $result->get_error_code() );
		$this->assertEquals( 400, $result->get_error_data()['status'] );

		// No items.
		$request->set_param( 'page', 1 );
		$response['totalItems'] = 0;

		$result = $this->instance->prepare_collection_response( $response, $request );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'rest_post_invalid_page_number', $result->get_error_code() );
		$this->assertEquals( 400, $result->get_error_data()['status'] );
	}
}
