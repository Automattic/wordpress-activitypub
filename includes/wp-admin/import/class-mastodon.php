<?php
/**
 * Mastodon importer file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

use Activitypub\Attachments;

use function Activitypub\is_activity_public;

/**
 * Mastodon importer class.
 */
class Mastodon {

	/**
	 * Import file attachment ID.
	 *
	 * @var int
	 */
	private static $import_id;

	/**
	 * Archive folder.
	 *
	 * @var string
	 */
	private static $archive;

	/**
	 * Outbox file.
	 *
	 * @var array
	 */
	private static $outbox;

	/**
	 * Author ID.
	 *
	 * @var int
	 */
	private static $author;

	/**
	 * Whether to fetch attachments.
	 *
	 * @var bool
	 */
	private static $fetch_attachments;

	/**
	 * Dispatch
	 */
	public static function dispatch() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = \absint( $_GET['step'] ?? 0 );

		self::header();

		switch ( $step ) {
			case 0:
				self::greet();
				break;

			case 1:
				\check_admin_referer( 'import-upload' );
				if ( self::handle_upload() ) {
					self::import_options();
				}
				break;

			case 2:
				\check_admin_referer( 'import-mastodon' );
				self::$import_id         = \absint( $_POST['import_id'] ?? 0 );
				self::$author            = \absint( $_POST['author'] ?? \get_current_user_id() );
				self::$fetch_attachments = ! empty( $_POST['fetch_attachments'] );

				\set_time_limit( 0 );
				self::import();
				break;
		}

		self::footer();
	}

	/**
	 * Handle upload.
	 *
	 * @return bool
	 */
	public static function handle_upload() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		\check_admin_referer( 'import-upload' );

		if ( ! isset( $_FILES['import']['name'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			\printf(
				/* translators: 1: php.ini, 2: post_max_size, 3: upload_max_filesize */
				\esc_html__( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.', 'activitypub' ),
				'php.ini',
				'post_max_size',
				'upload_max_filesize'
			);
			echo '</p>';
			return false;
		}

		$file_info = \wp_check_filetype( sanitize_file_name( $_FILES['import']['name'] ), array( 'zip' => 'application/zip' ) );
		if ( 'application/zip' !== $file_info['type'] ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			\esc_html_e( 'The uploaded file must be a ZIP archive. Please try again with the correct file format.', 'activitypub' );
			echo '</p>';
			return false;
		}

		$overrides = array(
			'test_form' => false,
			'test_type' => false,
		);

		$upload = wp_handle_upload( $_FILES['import'], $overrides );

		if ( isset( $upload['error'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html( $upload['error'] ) . '</p>';
			return false;
		}

		// Construct the attachment array.
		$attachment = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => $upload['url'],
			'post_mime_type' => $upload['type'],
			'guid'           => $upload['url'],
			'context'        => 'import',
			'post_status'    => 'private',
		);

		// Save the data.
		self::$import_id = wp_insert_attachment( $attachment, $upload['file'] );

		// Schedule a cleanup for one day from now in case of failed import or missing wp_import_cleanup() call.
		wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( self::$import_id ) );

		return true;
	}

	/**
	 * Import options.
	 */
	public static function import_options() {
		$author = 0;
		if ( isset( self::$outbox['orderedItems'][0] ) ) {
			$users = \get_users(
				array(
					'fields'     => 'ID',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'     => $GLOBALS['wpdb']->get_blog_prefix() . 'activitypub_also_known_as',
							'value'   => self::$outbox['orderedItems'][0]['actor'],
							'compare' => 'LIKE',
						),
					),
				)
			);

			if ( ! empty( $users ) ) {
				$author = $users[0];
			}
		}

		?>
		<form action="<?php echo \esc_url( \admin_url( 'admin.php?import=mastodon&amp;step=2' ) ); ?>" method="post">
			<?php \wp_nonce_field( 'import-mastodon' ); ?>
			<input type="hidden" name="import_id" value="<?php echo esc_attr( self::$import_id ); ?>" />
			<h3><?php \esc_html_e( 'Assign Author', 'activitypub' ); ?></h3>
			<p>
				<label for="author"><?php \esc_html_e( 'Author:', 'activitypub' ); ?></label>
				<?php
				\wp_dropdown_users(
					array(
						'name'       => 'author',
						'id'         => 'author',
						'show'       => 'display_name_with_login',
						'selected'   => $author,
						'capability' => 'activitypub',
					)
				);
				?>
			</p>
			<h3><?php \esc_html_e( 'Import Attachments', 'activitypub' ); ?></h3>
			<p>
				<input type="checkbox" value="1" name="fetch_attachments" id="import-attachments" checked />
				<label for="import-attachments"><?php \esc_html_e( 'Download and import file attachments', 'activitypub' ); ?></label>
			</p>
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Import', 'activitypub' ); ?>" />
			</p>
		</form>
		<?php
	}

	/**
	 * Import.
	 */
	public static function import() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );
		$file          = \get_attached_file( self::$import_id );

		\WP_Filesystem();

		global $wp_filesystem;
		$import_folder = $wp_filesystem->wp_content_dir() . 'import/';
		self::$archive = $import_folder . \basename( \basename( $file, '.txt' ), '.zip' );

		// Clean up working directory.
		if ( $wp_filesystem->is_dir( self::$archive ) ) {
			$wp_filesystem->delete( self::$archive, true );
		}

		// Unzip package to working directory.
		\unzip_file( $file, self::$archive );
		self::maybe_unwrap_archive();

		if ( ! $wp_filesystem->exists( self::$archive . '/outbox.json' ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html__( 'The archive does not contain an Outbox file, please try again.', 'activitypub' ) . '</p>';
			return;
		}

		self::$outbox = \json_decode( $wp_filesystem->get_contents( self::$archive . '/outbox.json' ), true );

		\wp_suspend_cache_invalidation();
		\wp_defer_term_counting( true );
		\wp_defer_comment_counting( true );

		/**
		 * Fires when the Mastodon import starts.
		 */
		\do_action( 'import_start' );

		$result = self::import_posts();

		\wp_suspend_cache_invalidation( false );
		\wp_defer_term_counting( false );
		\wp_defer_comment_counting( false );

		$wp_filesystem->delete( $import_folder, true );
		\wp_import_cleanup( self::$import_id );

		if ( \is_wp_error( $result ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html( $result->get_error_message() ) . '</p>';
		} else {
			echo '<p>';
			/* translators: Home URL */
			\printf( \wp_kses_post( \__( 'All done. <a href="%s">Have fun!</a>', 'activitypub' ) ), \esc_url( \admin_url() ) );
			echo '</p>';
		}

		/**
		 * Fires when the Mastodon import ends.
		 */
		\do_action( 'import_end' );
	}

	/**
	 * Process posts.
	 *
	 * Uses a multi-pass approach:
	 * 1. Categorize posts into regular posts and self-replies.
	 * 2. Import regular posts (root posts and external replies) as WordPress posts.
	 * 3. Import self-replies as comments on their parent posts.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function import_posts() {
		$skipped  = array();
		$imported = 0;

		// Pass 1: Categorize posts.
		$posts_to_import = array();
		$self_replies    = array();

		foreach ( self::$outbox['orderedItems'] as $post ) {
			// Skip boosts.
			if ( 'Announce' === $post['type'] ) {
				continue;
			}

			if ( ! is_activity_public( $post ) ) {
				continue;
			}

			if ( self::is_self_reply( $post ) ) {
				$self_replies[] = $post;
			} else {
				// Root posts and external replies are imported as WordPress posts.
				$posts_to_import[] = $post;
			}
		}

		// Pass 2: Import regular posts as WordPress posts.
		$source_to_post_id = array();
		foreach ( $posts_to_import as $post ) {
			$result = self::import_as_post( $post );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			if ( $result ) {
				$source_to_post_id[ $post['object']['id'] ] = $result;
				++$imported;
			} else {
				$skipped[] = $post['object']['id'];
			}
		}

		// Pass 3: Import self-replies as comments (sorted by date for correct threading).
		\usort(
			$self_replies,
			static function ( $a, $b ) {
				return \strtotime( $a['published'] ) <=> \strtotime( $b['published'] );
			}
		);

		$source_to_comment_id = array();
		$comments_skipped     = array();
		$comments_imported    = 0;

		foreach ( $self_replies as $post ) {
			$result = self::import_as_comment( $post, $source_to_post_id, $source_to_comment_id );

			if ( $result ) {
				++$comments_imported;
			} else {
				$comments_skipped[] = $post['object']['id'];
			}
		}

		// Output results.
		if ( ! empty( $skipped ) ) {
			echo '<p>' . \esc_html__( 'Skipped posts:', 'activitypub' ) . '<br>';
			echo \wp_kses( \implode( '<br>', $skipped ), array( 'br' => array() ) );
			echo '</p>';
		}

		if ( ! empty( $comments_skipped ) ) {
			echo '<p>' . \esc_html__( 'Skipped comments:', 'activitypub' ) . '<br>';
			echo \wp_kses( \implode( '<br>', $comments_skipped ), array( 'br' => array() ) );
			echo '</p>';
		}

		/* translators: %s: Number of posts */
		echo '<p>' . \esc_html( \sprintf( \_n( 'Imported %s post.', 'Imported %s posts.', $imported, 'activitypub' ), \number_format_i18n( $imported ) ) ) . '</p>';

		if ( $comments_imported > 0 ) {
			/* translators: %s: Number of comments */
			echo '<p>' . \esc_html( \sprintf( \_n( 'Imported %s comment from self-reply threads.', 'Imported %s comments from self-reply threads.', $comments_imported, 'activitypub' ), \number_format_i18n( $comments_imported ) ) ) . '</p>';
		}

		return true;
	}

	/**
	 * Check if a post is a self-reply (thread continuation).
	 *
	 * A self-reply is when a user replies to their own post, creating a thread.
	 *
	 * @param array $post The Mastodon activity.
	 *
	 * @return bool True if replying to own post.
	 */
	private static function is_self_reply( $post ) {
		if ( empty( $post['object']['inReplyTo'] ) ) {
			return false;
		}

		/*
		 * Compare base URLs (actor URL should be a prefix of inReplyTo for self-replies).
		 *
		 * Example:
		 * - actor: https://mastodon.social/users/example
		 * - inReplyTo: https://mastodon.social/users/example/statuses/123
		 *
		 * Adding a trailing slash ensures we don't match partial usernames
		 * (e.g., "example" shouldn't match "example2").
		 */
		return \str_starts_with( $post['object']['inReplyTo'], \rtrim( $post['actor'], '/' ) . '/' );
	}

	/**
	 * Import a single activity as a WordPress post.
	 *
	 * @param array $post The Mastodon activity.
	 *
	 * @return int|false|\WP_Error Post ID on success, false if skipped, WP_Error on failure.
	 */
	private static function import_as_post( $post ) {
		$post_data = array(
			'post_author'  => self::$author,
			'post_date'    => $post['published'],
			'post_excerpt' => $post['object']['summary'] ?? '',
			'post_content' => $post['object']['content'],
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'meta_input'   => array( '_source_id' => $post['object']['id'] ),
			'tags_input'   => \array_map(
				static function ( $tag ) {
					if ( 'Hashtag' === $tag['type'] ) {
						return \ltrim( $tag['name'], '#' );
					}

					return '';
				},
				$post['object']['tag'] ?? array()
			),
		);

		/**
		 * Filter the post data before inserting it into the database.
		 *
		 * @param array $post_data The post data to be inserted.
		 * @param array $post      The Mastodon Create activity.
		 */
		$post_data = \apply_filters( 'activitypub_import_mastodon_post_data', $post_data, $post );

		$post_exists = \post_exists( '', $post_data['post_content'], $post_data['post_date'], $post_data['post_type'] );

		/**
		 * Filter ID of the existing post corresponding to post currently importing.
		 *
		 * Return 0 to force the post to be imported. Filter the ID to be something else
		 * to override which existing post is mapped to the imported post.
		 *
		 * @see post_exists()
		 *
		 * @param int   $post_exists  Post ID, or 0 if post did not exist.
		 * @param array $post_data    The post array to be inserted.
		 */
		$post_exists = \apply_filters( 'wp_import_existing_post', $post_exists, $post_data );

		if ( $post_exists ) {
			return false;
		}

		$post_id = \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		\set_post_format( $post_id, 'status' );

		// Process attachments if enabled.
		if ( self::$fetch_attachments && ! empty( $post['object']['attachment'] ) ) {
			// Prepend archive path to attachment URLs for local files.
			$attachments = \array_map( array( self::class, 'prepend_archive_path' ), $post['object']['attachment'] );

			Attachments::import( $attachments, $post_id, self::$author );
		}

		return $post_id;
	}

	/**
	 * Import a self-reply as a comment on its parent post.
	 *
	 * @param array $post                 The Mastodon activity.
	 * @param array $source_to_post_id    Mapping of source IDs to WordPress post IDs.
	 * @param array $source_to_comment_id Mapping of source IDs to WordPress comment IDs (passed by reference).
	 *
	 * @return int|false Comment ID on success, false if parent not found or skipped.
	 */
	private static function import_as_comment( $post, $source_to_post_id, &$source_to_comment_id ) {
		$in_reply_to = $post['object']['inReplyTo'];

		// Find parent - could be a post or another comment.
		$parent_post_id    = null;
		$parent_comment_id = 0;

		if ( isset( $source_to_post_id[ $in_reply_to ] ) ) {
			// Replying to a root post or external reply.
			$parent_post_id = $source_to_post_id[ $in_reply_to ];
		} elseif ( isset( $source_to_comment_id[ $in_reply_to ] ) ) {
			// Replying to another comment (nested thread).
			$parent_comment_id = $source_to_comment_id[ $in_reply_to ];
			$parent_comment    = \get_comment( $parent_comment_id );

			if ( $parent_comment ) {
				$parent_post_id = $parent_comment->comment_post_ID;
			}
		}

		// If we couldn't find the parent, skip this comment.
		if ( ! $parent_post_id ) {
			return false;
		}

		// Check for duplicate.
		$existing_comments = \get_comments(
			array(
				'post_id'    => $parent_post_id,
				'meta_key'   => 'source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $post['object']['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);

		if ( ! empty( $existing_comments ) ) {
			// Already imported, add to mapping and skip.
			$source_to_comment_id[ $post['object']['id'] ] = $existing_comments[0]->comment_ID;

			return false;
		}

		$comment_data = array(
			'comment_post_ID'  => $parent_post_id,
			'comment_parent'   => $parent_comment_id,
			'comment_author'   => \get_the_author_meta( 'display_name', self::$author ),
			'comment_content'  => $post['object']['content'],
			'comment_date'     => $post['published'],
			'user_id'          => self::$author,
			'comment_approved' => 1,
		);

		$comment_id = \wp_insert_comment( $comment_data );

		if ( $comment_id ) {
			\update_comment_meta( $comment_id, 'source_id', $post['object']['id'] );

			$source_to_comment_id[ $post['object']['id'] ] = $comment_id;
		}

		return $comment_id;
	}

	/**
	 * Header.
	 */
	public static function header() {
		echo '<div class="wrap">';
		echo '<h2>' . \esc_html__( 'Import from Mastodon (Beta)', 'activitypub' ) . '</h2>';
	}

	/**
	 * Footer.
	 */
	public static function footer() {
		echo '</div>';
	}

	/**
	 * Intro.
	 */
	public static function greet() {
		echo '<div class="narrow">';
		echo '<p>' . \wp_kses(
			\sprintf(
				/* translators: %s: URL to Mastodon export documentation */
				\__( 'This importer allows you to bring your Mastodon posts into your WordPress site. For a smooth import experience, check out the <a href="%s" target="_blank">Mastodon documentation</a>.', 'activitypub' ),
				'https://docs.joinmastodon.org/user/moving/#export'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
				),
			)
		) . '</p>';
		echo '<p>' . \esc_html__( 'Here&#8217;s how to get started:', 'activitypub' ) . '</p>';

		echo '<ol>';
		echo '<li>' . \wp_kses( \__( 'Log in to your Mastodon account and go to <strong>Preferences > Import and Export</strong>.', 'activitypub' ), array( 'strong' => array() ) ) . '</li>';
		echo '<li>' . \esc_html__( 'Request a new archive of your data and wait for the email notification.', 'activitypub' ) . '</li>';
		echo '<li>' . \wp_kses( \__( 'Download the archive file (it will be a <code>.zip</code> file).', 'activitypub' ), array( 'code' => array() ) ) . '</li>';
		echo '<li>' . \esc_html__( 'Upload that file below to begin the import process.', 'activitypub' ) . '</li>';
		echo '</ol>';

		\wp_import_upload_form( 'admin.php?import=mastodon&amp;step=1' );
		echo '</div>';
	}

	/**
	 * Prepend archive path to local attachment URLs.
	 *
	 * @param array $attachment The attachment array.
	 *
	 * @return array The attachment array with updated URL.
	 */
	private static function prepend_archive_path( $attachment ) {
		if ( ! empty( $attachment['url'] ) && ! preg_match( '#^https?://#i', $attachment['url'] ) ) {
			$attachment['url'] = self::$archive . $attachment['url'];
		}

		return $attachment;
	}

	/**
	 * Detect and unwrap single nested directory in archive.
	 *
	 * Some Mastodon exports wrap all files in a root folder. This method
	 * detects this pattern and updates the archive path to point inside it.
	 */
	private static function maybe_unwrap_archive() {
		global $wp_filesystem;

		$files = $wp_filesystem->dirlist( self::$archive );

		// Check if there's exactly one directory at root level.
		if ( count( $files ) !== 1 ) {
			return;
		}

		$first = reset( $files );
		if ( 'd' !== $first['type'] ) {
			return;
		}

		// Check if outbox.json exists inside the nested directory.
		$nested_path = self::$archive . '/' . $first['name'];
		if ( $wp_filesystem->exists( $nested_path . '/outbox.json' ) ) {
			self::$archive = $nested_path;
		}
	}
}
