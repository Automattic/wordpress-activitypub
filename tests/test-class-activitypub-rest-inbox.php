<?php
class Test_Activitypub_Rest_Inbox extends WP_UnitTestCase {
	/**
	 * @dataProvider the_data_provider
	 */
	public function test_is_activity_public( $data, $check ) {

		$this->assertEquals( $check, Activitypub\is_activity_public( $data ) );
	}

	public function the_data_provider() {
		return array(
			array(
				array(
					'cc' => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to' => 'https://www.w3.org/ns/activitystreams#Public',
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc' => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to' => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc' => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(),
				),
				false,
			),
			array(
				array(
					'cc' => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'cc' => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => array(
							'https://www.w3.org/ns/activitystreams#Public',
						),
					),
				),
				true,
			),
		);
	}
}
