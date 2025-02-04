<?php
/**
 * Test file for Activitypub Replies.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Replies;

/**
 * Test class for Activitypub Replies.
 *
 * @coversDefaultClass \Activitypub\Collection\Replies
 */
class Test_Replies extends \WP_UnitTestCase {

	/**
	 * Test the replies collection of a post.
	 *
	 * @covers ::get_collection
	 */
	public function test_replies_collection_of_post_with_federated_comments() {
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);

		$source_id = 'https://example.instance/notes/123';

		$comment = array(
			'user_id'              => 1,
			'comment_type'         => 'comment',
			'comment_content'      => 'This is a comment.',
			'comment_author_url'   => 'https://example.com',
			'comment_author_email' => '',
			'comment_meta'         => array(
				'protocol'  => 'activitypub',
				'source_id' => $source_id,
			),
			'comment_post_ID'      => $post_id,
		);

		$comment_id = wp_insert_comment( $comment );

		wp_set_comment_status( $comment_id, 'hold' );
		$replies = Replies::get_collection( get_post( $post_id ) );
		$this->assertEquals( $replies['id'], sprintf( 'http://example.org/index.php?rest_route=/activitypub/1.0/posts/%d/replies', $post_id ) );
		$this->assertCount( 0, $replies['first']['items'] );

		wp_set_comment_status( $comment_id, 'approve' );
		$replies = Replies::get_collection( get_post( $post_id ) );
		$this->assertCount( 1, $replies['first']['items'] );
		$this->assertEquals( $replies['first']['items'][0], $source_id );
	}

	/**
	 * Test get_context_collection method.
	 *
	 * @covers ::get_context_collection
	 */
	public function test_get_context_collection() {
		// Erstelle einen Test-Post.
		$context_post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);

		// Test mit deaktiviertem Post.
		add_post_meta( $context_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		$this->assertFalse( Replies::get_context_collection( $context_post_id ), 'Sollte false für deaktivierte Posts zurückgeben' );
		delete_post_meta( $context_post_id, 'activitypub_content_visibility' );

		// Test mit ungültigem Post.
		$this->assertFalse( Replies::get_context_collection( 999999 ), 'Sollte false für nicht existierende Posts zurückgeben' );

		// Test ohne Kommentare.
		$context = Replies::get_context_collection( $context_post_id );
		$this->assertIsArray( $context, 'Sollte ein leeres Array für Posts ohne Kommentare zurückgeben' );
		$this->assertEmpty( $context, 'Array sollte leer sein für Posts ohne Kommentare' );

		// Erstelle Test-Kommentare.
		$comments = array();

		// Lokaler Kommentar.
		$comments[] = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $context_post_id,
				'comment_content'  => 'Local comment',
				'comment_approved' => '1',
				'comment_meta'     => array(
					'activitypub_status' => 'federated',
				),
			)
		);

		// ActivityPub Kommentar.
		$comments[] = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $context_post_id,
				'comment_content'  => 'ActivityPub comment',
				'comment_approved' => '1',
				'comment_meta'     => array(
					'protocol'  => 'activitypub',
					'source_id' => 'https://example.com/comment/1',
				),
			)
		);

		// Test mit Kommentaren.
		$context = Replies::get_context_collection( $context_post_id );

		$this->assertIsArray( $context, 'Sollte ein Array zurückgeben' );
		$this->assertEquals( 'OrderedCollection', $context['type'], 'Sollte vom Typ OrderedCollection sein' );
		$this->assertEquals( get_permalink( $context_post_id ), $context['url'], 'Sollte die Post-URL enthalten' );
		$this->assertArrayHasKey( 'attributedTo', $context, 'Sollte attributedTo enthalten' );
		$this->assertArrayHasKey( 'totalItems', $context, 'Sollte totalItems enthalten' );
		$this->assertArrayHasKey( 'items', $context, 'Sollte items enthalten' );

		// Überprüfe die Anzahl der Items (Post + alle Kommentare).
		$this->assertEquals( 3, $context['totalItems'], 'Sollte Post + alle Kommentare zählen' );
		$this->assertCount( 3, $context['items'], 'Items sollte Post + alle Kommentare enthalten' );

		// Überprüfe, dass der Post-URI das erste Element ist.
		$this->assertStringContainsString( (string) $context_post_id, $context['items'][0], 'Erstes Item sollte Post-URI sein' );

		// Überprüfe, dass der ActivityPub Kommentar enthalten ist.
		$this->assertContains( 'https://example.com/comment/1', $context['items'], 'Sollte ActivityPub Kommentar-ID enthalten' );

		// Clean up.
		wp_delete_post( $context_post_id, true );
		foreach ( $comments as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}
	}
}
