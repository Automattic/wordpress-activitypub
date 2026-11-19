<?php
/**
 * Tests for ActivityPub Search Enhancement
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Comment;
use Activitypub\Moderation;
use Activitypub\Search;

/**
 * Test class for ActivityPub Search Enhancement
 */
class Test_Search extends \WP_UnitTestCase {
	/**
	 * Test search enhancement doesn't interfere with regular searches.
	 */
	public function test_regular_search_unchanged() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post About Cats',
				'post_content' => 'This is a test post about cats and dogs.',
				'post_status'  => 'publish',
			)
		);

		// Perform a regular search.
		$query = new \WP_Query(
			array(
				's'         => 'cats',
				'post_type' => 'post',
			)
		);

		// Should find the test post.
		$this->assertTrue( $query->have_posts() );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( $post_id, $query->posts[0]->ID );
	}

	/**
	 * Test search enhancement initialization.
	 */
	public function test_search_init() {
		$this->assertEquals( 10, has_filter( 'pre_get_posts', array( Search::class, 'enhance_public_search' ) ) );
	}

	/**
	 * Test search enhancement only affects main search queries.
	 */
	public function test_search_enhancement_conditions() {
		// Test admin query (should not be enhanced).
		$admin_query                = new \WP_Query();
		$admin_query->is_search     = true;
		$admin_query->is_main_query = true;

		// Simulate admin context.
		\set_current_screen( 'edit-post' );

		$result = Search::enhance_public_search( $admin_query );
		$this->assertEquals( $admin_query, $result );

		// Reset screen.
		\set_current_screen( 'front' );

		// Test non-search query (should not be enhanced).
		$non_search_query                = new \WP_Query();
		$non_search_query->is_search     = false;
		$non_search_query->is_main_query = true;

		$result = Search::enhance_public_search( $non_search_query );
		$this->assertEquals( $non_search_query, $result );

		// Test non-main query (should not be enhanced).
		$non_main_query                = new \WP_Query();
		$non_main_query->is_search     = true;
		$non_main_query->is_main_query = false;

		$result = Search::enhance_public_search( $non_main_query );
		$this->assertEquals( $non_main_query, $result );
	}

	/**
	 * Searching for a comment's URL leads to it, but leaves its moderation status alone.
	 *
	 * A pending reply does not render on the front end, so a moderator goes to its moderation view.
	 *
	 * @dataProvider data_search_keeps_comment_status
	 * @covers \Activitypub\Search::enhance_admin_comment_search
	 *
	 * @param string $role       The searching user's role.
	 * @param string $status     The comment's approval status.
	 * @param bool   $moderation Whether the search leads to the moderation view.
	 */
	public function test_search_keeps_comment_status( $role, $status, $moderation ) {
		$user = \get_user_by( 'id', self::factory()->user->create( array( 'role' => $role ) ) );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );

		// A public IP, so wp_http_validate_url() accepts the URL without a DNS lookup.
		$url        = 'https://1.1.1.1/notes/1';
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::factory()->post->create(),
				'comment_approved' => $status,
			)
		);
		\add_comment_meta( $comment_id, 'source_id', $url );

		$redirect = function ( $location ) {
			throw new \Exception( \esc_url_raw( $location ) );
		};
		\add_filter( 'wp_redirect', $redirect );

		$_GET['s']  = $url;
		$redirected = '';
		try {
			Search::enhance_admin_comment_search();
		} catch ( \Exception $e ) {
			$redirected = $e->getMessage();
		}
		unset( $_GET['s'] );

		\remove_filter( 'wp_redirect', $redirect );

		$this->assertNotEmpty( $redirected, 'The search should still redirect after finding the comment.' );
		$this->assertSame( $status, \get_comment( $comment_id )->comment_approved, 'The search must not change the comment status.' );
		$this->assertSame( $moderation, \admin_url( 'comment.php?action=editcomment&c=' . $comment_id ) === $redirected );
	}

	/**
	 * Roles for the comment status test.
	 *
	 * @return array[] Role, comment status and whether the search leads to the moderation view.
	 */
	public function data_search_keeps_comment_status() {
		return array(
			'author, pending'     => array( 'author', '0', false ),
			'moderator, pending'  => array( 'editor', '0', true ),
			'moderator, approved' => array( 'editor', '1', false ),
		);
	}

	/**
	 * Set up a remote reply to a local post, served by the fetch filter.
	 *
	 * @param string $url   The reply's URL.
	 * @param array  $extra Properties that replace the reply's defaults.
	 *
	 * @return callable[] The filters to remove again, keyed by hook name.
	 */
	private function serve_reply( $url, $extra = array() ) {
		$actor = 'https://1.1.1.1/users/test';
		$reply = \array_merge(
			array(
				'id'           => $url,
				'type'         => 'Note',
				'attributedTo' => $actor,
				'content'      => 'Hello',
				'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				'inReplyTo'    => \get_permalink( self::factory()->post->create() ),
			),
			$extra
		);

		$filters = array(
			'activitypub_pre_http_get_remote_object' => static function ( $pre, $url_or_object ) use ( $url, $reply, $actor ) {
				if ( $actor === $url_or_object ) {
					return array(
						'id'                => $actor,
						'type'              => 'Person',
						'name'              => 'Test',
						'preferredUsername' => 'test',
						'inbox'             => \sprintf( '%s/inbox', $actor ),
					);
				}

				return $url === $url_or_object ? $reply : $pre;
			},
			'pre_get_remote_metadata_by_actor'       => static function () use ( $actor ) {
				return array(
					'id'                => $actor,
					'name'              => 'Test',
					'preferredUsername' => 'test',
					'url'               => $actor,
				);
			},
		);

		\add_filter( 'activitypub_pre_http_get_remote_object', $filters['activitypub_pre_http_get_remote_object'], 10, 2 );
		\add_filter( 'pre_get_remote_metadata_by_actor', $filters['pre_get_remote_metadata_by_actor'] );

		return $filters;
	}

	/**
	 * Remove the filters of serve_reply().
	 *
	 * @param callable[] $filters The filters, keyed by hook name.
	 */
	private function stop_serving( $filters ) {
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filters['activitypub_pre_http_get_remote_object'], 10 );
		\remove_filter( 'pre_get_remote_metadata_by_actor', $filters['pre_get_remote_metadata_by_actor'] );
	}

	/**
	 * Searching the front end for a URL leads to the comments screen, without fetching anything.
	 *
	 * @covers \Activitypub\Search::enhance_public_search
	 */
	public function test_public_search_leads_to_the_comments_screen() {
		$user = \get_user_by( 'id', self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );

		// A public IP, so wp_http_validate_url() accepts the URL without a DNS lookup.
		$url     = 'https://1.1.1.1/notes/3';
		$fetched = 0;
		$fetch   = static function ( $pre ) use ( &$fetched ) {
			++$fetched;
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch );

		$redirect = static function ( $location ) {
			throw new \Exception( \esc_url_raw( $location ) );
		};
		\add_filter( 'wp_redirect', $redirect );

		// The front end runs the search as the main query.
		$redirected = '';
		try {
			$this->go_to( \add_query_arg( 's', \rawurlencode( $url ), \home_url( '/' ) ) );
		} catch ( \Exception $e ) {
			$redirected = $e->getMessage();
		}

		\remove_filter( 'wp_redirect', $redirect );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch );

		$this->assertSame( 0, $fetched, 'A search must not fetch.' );
		$this->assertSame( \add_query_arg( 's', \rawurlencode( $url ), \admin_url( 'edit-comments.php' ) ), $redirected );
	}

	/**
	 * The comments screen checks a reply without storing it, and offers the import with a nonce.
	 *
	 * @covers \Activitypub\Search::enhance_admin_comment_search
	 * @covers \Activitypub\Search::is_importable_reply
	 */
	public function test_admin_search_offers_an_importable_reply() {
		$url     = 'https://1.1.1.1/notes/8';
		$filters = $this->serve_reply( $url );

		$notice = $this->admin_search_notice( $url );

		$this->stop_serving( $filters );

		$this->assertEmpty( Comment::url_to_commentid( $url ), 'A search must not store the reply.' );
		$this->assertStringContainsString( 'https://1.1.1.1/users/test', $notice, 'The notice names the author.' );
		$this->assertStringContainsString( 'action=activitypub_import_reply', $notice );
		$this->assertStringContainsString( '_wpnonce=', $notice );
		$this->assertStringContainsString( \rawurlencode( $url ), \html_entity_decode( $notice ) );
	}

	/**
	 * The comments screen says why a reply cannot be imported, and offers no import.
	 *
	 * @covers \Activitypub\Search::enhance_admin_comment_search
	 * @covers \Activitypub\Search::is_importable_reply
	 */
	public function test_admin_search_says_why_a_reply_cannot_be_imported() {
		$url     = 'https://1.1.1.1/notes/9';
		$filters = $this->serve_reply( $url, array( 'inReplyTo' => 'https://1.1.1.1/notes/elsewhere' ) );

		$notice = $this->admin_search_notice( $url );

		$this->stop_serving( $filters );

		$this->assertStringContainsString( 'This is not a reply to one of your posts.', $notice );
		$this->assertStringNotContainsString( 'action=activitypub_import_reply', $notice );
	}

	/**
	 * After a failed import the comments screen says why storing the reply failed.
	 *
	 * @covers \Activitypub\Search::enhance_admin_comment_search
	 */
	public function test_admin_search_says_why_storing_failed() {
		$url     = 'https://1.1.1.1/notes/10';
		$filters = $this->serve_reply( $url );

		$_GET['activitypub_import'] = 'comment_duplicate';
		$notice                     = $this->admin_search_notice( $url );
		unset( $_GET['activitypub_import'] );

		$this->stop_serving( $filters );

		$this->assertStringContainsString( 'The same comment from this author is already stored', $notice );
		$this->assertStringContainsString( 'comment_status=moderated', $notice );
	}

	/**
	 * A URL that leads to nothing from the Fediverse stays a plain search, without a notice.
	 *
	 * @covers \Activitypub\Search::enhance_admin_comment_search
	 */
	public function test_admin_search_stays_quiet_for_other_urls() {
		$nothing = static function () {
			return new \WP_Error( 'activitypub_not_found', 'Not found.' );
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $nothing );

		$notice = $this->admin_search_notice( 'https://1.1.1.1/a-page' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $nothing );

		$this->assertSame( '', $notice );
	}

	/**
	 * Search the comments screen for a URL as an editor and return the notices it shows.
	 *
	 * @param string $url The URL to search for.
	 *
	 * @return string The notices.
	 */
	private function admin_search_notice( $url ) {
		$user = \get_user_by( 'id', self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );

		$_GET['s'] = $url;
		Search::enhance_admin_comment_search();
		unset( $_GET['s'] );

		\ob_start();
		\do_action( 'admin_notices' );
		return \ob_get_clean();
	}

	/**
	 * The import needs the nonce of its link.
	 *
	 * @covers \Activitypub\Search::import_reply
	 */
	public function test_import_reply_requires_nonce() {
		$user = \get_user_by( 'id', self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );

		$url     = 'https://1.1.1.1/notes/4';
		$filters = $this->serve_reply( $url );

		$_GET['url']          = $url;
		$_REQUEST['_wpnonce'] = 'invalid';

		$died = false;
		try {
			Search::import_reply();
		} catch ( \WPDieException $e ) {
			$died = true;
		}

		unset( $_GET['url'], $_REQUEST['_wpnonce'] );
		$this->stop_serving( $filters );

		$this->assertTrue( $died, 'An import without a valid nonce must stop.' );
		$this->assertEmpty( Comment::url_to_commentid( $url ), 'Nothing may be stored without a valid nonce.' );
	}

	/**
	 * A reply that cannot be stored leads back to the search with a notice, instead of breaking.
	 *
	 * @covers \Activitypub\Search::import_reply
	 */
	public function test_import_reply_with_failed_import() {
		$user = \get_user_by( 'id', self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );

		$url     = 'https://1.1.1.1/notes/2';
		$filters = $this->serve_reply( $url );
		$fail    = static function () {
			return new \WP_Error( 'comment_duplicate', 'Duplicate comment.' );
		};
		\add_filter( 'pre_comment_approved', $fail, 99 );

		$redirect = static function ( $location ) {
			throw new \Exception( \esc_url_raw( $location ) );
		};
		\add_filter( 'wp_redirect', $redirect );

		$_GET['url']          = $url;
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'activitypub_import_reply' );

		$redirected = '';
		try {
			Search::import_reply();
		} catch ( \Exception $e ) {
			$redirected = $e->getMessage();
		}

		unset( $_GET['url'], $_REQUEST['_wpnonce'] );
		\remove_filter( 'wp_redirect', $redirect );
		\remove_filter( 'pre_comment_approved', $fail, 99 );
		$this->stop_serving( $filters );

		$this->assertStringStartsWith( \admin_url( 'edit-comments.php' ), $redirected );
		$this->assertStringContainsString( 'activitypub_import=comment_duplicate', $redirected );
	}

	/**
	 * The import refuses what the inbox refuses, and stores what the inbox stores.
	 *
	 * @dataProvider data_import_refusals
	 * @covers \Activitypub\Search::try_import_activitypub_object
	 *
	 * @param string $block  The site block as "type value", or an empty string.
	 * @param array  $extra  Properties that replace the reply's defaults.
	 * @param bool   $stored Whether the reply is stored.
	 */
	public function test_import_runs_the_inbox_checks( $block, $extra, $stored ) {
		$url = 'https://1.1.1.1/notes/5';
		if ( isset( $extra['id'] ) ) {
			$url = \home_url( $extra['id'] );
		}
		if ( isset( $extra['attributedTo'] ) ) {
			$extra['attributedTo'] = \get_author_posts_url( 1 );
		}
		$extra   = \array_merge( $extra, array( 'id' => $url ) );
		$filters = $this->serve_reply( $url, $extra );

		if ( $block ) {
			list( $type, $value ) = \explode( ' ', $block, 2 );
			$this->assertTrue( Moderation::add_site_block( $type, $value ), 'The block has to be in place.' );
		}

		$result = $this->try_import_activitypub_object_accessible( $url );

		$this->stop_serving( $filters );

		if ( $stored ) {
			$this->assertIsInt( $result, 'An allowed reply has to be stored.' );
		} else {
			$this->assertFalse( $result );
			$this->assertEmpty( Comment::url_to_commentid( $url ), 'A refused reply must not be stored.' );
		}
	}

	/**
	 * The disallowed list is checked against the reply, not against the admin who imports it.
	 *
	 * @covers \Activitypub\Search::try_import_activitypub_object
	 */
	public function test_import_ignores_the_address_of_the_importing_admin() {
		$url     = 'https://1.1.1.1/notes/6';
		$filters = $this->serve_reply( $url );

		$server                     = $_SERVER;
		$_SERVER['REMOTE_ADDR']     = '192.0.2.10';
		$_SERVER['HTTP_USER_AGENT'] = 'Admin Browser';
		\update_option( 'disallowed_keys', "192.0.2.10\nAdmin Browser" );

		$result = $this->try_import_activitypub_object_accessible( $url );

		\delete_option( 'disallowed_keys' );
		$_SERVER = $server;
		$this->stop_serving( $filters );

		$this->assertIsInt( $result, 'The admin\'s address must not block the reply.' );

		$comment = \get_comment( $result );
		$this->assertNotContains( $comment->comment_approved, array( 'spam', 'trash' ), 'The admin\'s address must not mark the reply as spam.' );
		$this->assertSame( '', $comment->comment_author_IP, 'The admin\'s address must not be stored with the reply.' );
		$this->assertSame( '', $comment->comment_agent, 'The admin\'s user agent must not be stored with the reply.' );
	}

	/**
	 * The disallowed list is checked against the whole reply, as in the inbox.
	 *
	 * @covers \Activitypub\Search::try_import_activitypub_object
	 */
	public function test_import_checks_the_disallowed_list_against_the_reply() {
		$url     = 'https://1.1.1.1/notes/disallowed-word';
		$filters = $this->serve_reply( $url );

		\update_option( 'disallowed_keys', 'disallowed-word' );

		$result = $this->try_import_activitypub_object_accessible( $url );

		\delete_option( 'disallowed_keys' );
		$this->stop_serving( $filters );

		$this->assertFalse( $result );
		$this->assertEmpty( Comment::url_to_commentid( $url ), 'A refused reply must not be stored.' );
	}

	/**
	 * A reply without an author is not imported.
	 *
	 * @covers \Activitypub\Search::try_import_activitypub_object
	 */
	public function test_import_requires_an_author() {
		$url     = 'https://1.1.1.1/notes/7';
		$filters = $this->serve_reply( $url, array( 'attributedTo' => array() ) );

		$result = $this->try_import_activitypub_object_accessible( $url );

		$this->stop_serving( $filters );

		$this->assertFalse( $result );
		$this->assertEmpty( Comment::url_to_commentid( $url ) );
	}

	/**
	 * The cases of the inbox checks.
	 *
	 * @return array[] Site block, reply properties, whether the reply is stored.
	 */
	public function data_import_refusals() {
		return array(
			'allowed'        => array( '', array(), true ),
			'blocked actor'  => array( 'actor https://1.1.1.1/users/test', array(), false ),
			'blocked domain' => array( 'domain 1.1.1.1', array(), false ),
			'keyword'        => array( 'keyword forbidden', array( 'content' => 'A forbidden word' ), false ),
			'private'        => array( '', array( 'to' => array( 'https://1.1.1.1/users/someone' ) ), false ),
			// One of the site's own comments: object and actor on the site's host.
			'self ping'      => array(
				'',
				array(
					'id'           => '/?c=1',
					'attributedTo' => 'local',
				),
				false,
			),
		);
	}

	/**
	 * Helper method to access private try_import_activitypub_object method via reflection.
	 *
	 * @param string $url The URL to import.
	 *
	 * @return int|false The imported comment ID or false on failure.
	 */
	private function try_import_activitypub_object_accessible( $url ) {
		$reflection = new \ReflectionClass( 'Activitypub\Search' );
		$method     = $reflection->getMethod( 'try_import_activitypub_object' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null, $url );
	}
}
