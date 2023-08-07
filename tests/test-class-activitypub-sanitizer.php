<?php
class Test_Activitypub_Sanitizer extends WP_UnitTestCase {
	/**
	 * @dataProvider the_data_provider
	 */
	public function test_sanitize_array( $source, $target ) {
		$sanitizer = new Activitypub\Sanitizer();
		$this->assertEquals( $target, $sanitizer->sanitize_array( $source ) );
	}

	public function the_data_provider() {
		return array(
			array(
				array(
					'type"§$' => '<p>Create</p>',
					'content' => '<p>Content</p><script>content</script>',
					'contentMap' => array(
						'en' => '<p>Content</p><script>content</script>',
					),
					'nameMap' => array(
						'en' => '<div>Content</div><script>content</script>',
					),
					'inbox' => 'https://example.org/inbox',
					'outbox' => 'example.org/outbox',
					'name' => 'Gifts\'+OR+1=1--',
				),
				array(
					'type' => 'Create',
					'content' => '<p>Content</p>',
					'contentMap' => array(
						'en' => '<p>Content</p>',
					),
					'nameMap' => array(
						'en' => 'Content',
					),
					'inbox' => 'https://example.org/inbox',
					'outbox' => 'http://example.org/outbox',
					'name' => 'Gifts\'+OR+1=1--',
				),
			),
		);
	}
}
