<?php
class Test_Activitypub_Activity extends WP_UnitTestCase {
	public function test_activity_mentions() {
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => '@alex hello',
			)
		);

		add_filter(
			'activitypub_extract_mentions',
			function( $mentions ) {
				$mentions['@alex'] = 'https://example.com/alex';
				return $mentions;
			},
			10
		);

		$activitypub_post = new \Activitypub\Model\Post( $post );

		$activitypub_activity = new \Activitypub\Model\Activity( 'Create' );
		$activitypub_activity->from_post( $activitypub_post );

		$this->assertContains( \Activitypub\get_rest_url_by_path( 'users/1/followers' ), $activitypub_activity->get_to() );
		$this->assertContains( 'https://example.com/alex', $activitypub_activity->get_cc() );

		remove_all_filters( 'activitypub_extract_mentions' );
		\wp_trash_post( $post );
	}
}
