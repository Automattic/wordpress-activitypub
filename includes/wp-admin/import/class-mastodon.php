<?php
/**
 * Mastodon importer file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

/**
 * Mastodon importer class.
 */
class Mastodon {

	/**
	 * Import file attachment ID.
	 *
	 * @var int
	 */
	public static $import_id;

	/**
	 * Outbox file.
	 *
	 * @var object
	 */
	public static $outbox;

	/**
	 * Author ID.
	 *
	 * @var int
	 */
	public static $author;

	/**
	 * Whether to fetch attachments.
	 *
	 * @var bool
	 */
	public static $fetch_attachments;

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
		$file          = \wp_import_handle_upload();
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html( $file['error'] ) . '</p>';
			return false;
		} elseif ( ! \file_exists( $file['file'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			/* translators: File path. */
			\printf( \wp_kses_post( \__( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permission problem.', 'activitypub' ) ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}

		self::$import_id = $file['id'];

		return true;
	}

	/**
	 * Import options.
	 */
	public static function import_options() {
		$author = 0;
		if ( isset( self::$outbox->{'orderedItems'}[0] ) ) {
			$users = \get_users(
				array(
					'fields'     => 'ID',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'     => $GLOBALS['wpdb']->get_blog_prefix() . 'activitypub_also_known_as',
							'value'   => self::$outbox->{'orderedItems'}[0]->actor,
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
		$working_dir   = $import_folder . \basename( \basename( $file, '.txt' ), '.zip' );

		// Clean up working directory.
		if ( $wp_filesystem->is_dir( $working_dir ) ) {
			$wp_filesystem->delete( $working_dir, true );
		}

		// Unzip package to working directory.
		\unzip_file( $file, $working_dir );
		$files = $wp_filesystem->dirlist( $working_dir );

		if ( ! isset( $files['outbox.json'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html__( 'The archive does not contain an Outbox file, please try again.', 'activitypub' ) . '</p>';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		self::$outbox = \json_decode( \file_get_contents( $working_dir . '/outbox.json' ) );

		$wp_filesystem->delete( $import_folder, true );

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
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function import_posts() {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		foreach ( self::$outbox->orderedItems as $post ) {
			// Skip boosts.
			if ( 'Announce' === $post->type ) {
				continue;
			}

			if ( ! \Activitypub\is_activity_public( \get_object_vars( $post ) ) ) {
				continue;
			}

			// @todo: Skip replies to comments and import them as comments.

			$post_data = array(
				'post_author'  => self::$author,
				'post_date'    => $post->published,
				'post_excerpt' => $post->object->summary ?? '',
				'post_content' => $post->object->content,
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'tags_input'   => \array_map(
					function ( $tag ) {
						if ( 'Hashtag' === $tag->type ) {
							return \ltrim( $tag->name, '#' );
						}

						return '';
					},
					$post->object->tag
				),
			);

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
				/* translators: 1: Post type name */
				\printf( \esc_html__( '%1$s already exists.', 'activitypub' ), \esc_html( \get_post_type_object( $post_data['post_type'] )->labels->singular_name ) );
				echo '<br />';
				continue;
			}

			$post_id = \wp_insert_post( $post_data, true );

			if ( \is_wp_error( $post_id ) ) {
				return $post_id;
			}

			\set_post_format( $post_id, 'status' );

			// phpcs:ignore
			if ( $post_id && isset( $post->object->replies->first->next ) ) {
				// @todo: Import replies as comments.
			}
		}

		return true;
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
		echo '<p>' . \esc_html__( 'Howdy! This importer allows you to extract posts from Mastodon exports into your site. Pick a Mastodon archive to upload and click Import.', 'activitypub' ) . '</p>';
		\wp_import_upload_form( 'admin.php?import=mastodon&amp;step=1' );
		echo '</div>';
	}
}
